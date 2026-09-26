import fs from 'fs';
import path from 'path';

const PACKAGE_DIR = 'vendor/posio/cabinet-kit';
const INSTALLED_JSON = 'vendor/composer/installed.json';

// Страницы кабинета установленных модулей; вход кабинета импортирует его сам.
const MODULES_ID = 'virtual:cabinet-kit-modules';
const RESOLVED_MODULES_ID = `\u0000${MODULES_ID}`;

// Чем достраивается путь без расширения, прежде чем считать файл отсутствующим.
const RESOLVED_SUFFIXES = ['', '.vue', '.ts', '.js', '.mjs', '.json', '/index.vue', '/index.ts', '/index.js'];

export default function cabinetKit(options = {}) {
    const root = options.root ?? process.cwd();
    const packageDir = path.resolve(root, PACKAGE_DIR);
    const modules = discoverModules(root);
    const aliases = [
        ...modules.map((module) => packageAlias(module.alias, module.dir, 'resources')),
        ...createAliases(packageDir, root),
    ];

    const packageDirs = withRealPaths([packageDir]);
    const hostAnchor = path.join(root, 'package.json');

    return {
        name: 'cabinet-kit',
        resolveId(id, importer, options) {
            if (id === MODULES_ID) return RESOLVED_MODULES_ID;

            // Пакет, подключённый junction'ом, отдаёт файлы по настоящему пути вне
            // проекта: зависимости (vue, dayjs) от них не находятся — у пакета своих
            // node_modules нет. Недонайденное ищется от корня проекта, как у пакета,
            // установленного в vendor.
            if (! isBareImport(id) || ! importer || isInside(importer, root) || ! isInsideAny(importer, packageDirs)) {
                return null;
            }

            return this.resolve(id, hostAnchor, { skipSelf: true, ...options });
        },
        load(id) {
            return id === RESOLVED_MODULES_ID ? modulePagesSource(modules, root) : null;
        },
        config(userConfig, { command }) {
            userConfig.resolve ??= {};
            userConfig.resolve.alias = [
                ...aliases,
                ...normalizeAliases(userConfig.resolve.alias),
            ];

            const config = {
                css: {
                    preprocessorOptions: {
                        scss: {
                            additionalData: `@use "${path.join(packageDir, 'resources/scss/_flexgap_shared.scss').replace(/\\/g, '/')}" as *;`,
                        },
                    },
                },
                server: { fs: { allow: withRealPaths([root, packageDir, ...modules.map((module) => module.dir)]) } },
            };

            // Сертификат нужен только dev-серверу: сборка на сервере его не ищет и не предупреждает.
            const https = command === 'serve' ? resolveHttps(options.https, root) : null;
            if (https) {
                config.server.https = https;
                config.server.hmr = { protocol: 'wss', ...(options.hmr ?? {}) };
            }

            return config;
        },
    };
}

// Модули — composer-пакеты с `extra.cabinet-kit`; тот же список читает сервер,
// поэтому страница модуля есть либо и там и там, либо нигде.
function discoverModules(root) {
    const installed = path.resolve(root, INSTALLED_JSON);
    let json;

    try {
        json = JSON.parse(fs.readFileSync(installed, 'utf8'));
    } catch {
        return [];
    }

    const packages = Array.isArray(json) ? json : (json.packages ?? []);

    return packages
        .filter((pkg) => pkg?.name && pkg.extra?.['cabinet-kit']?.module)
        .map((pkg) => {
            const extra = pkg.extra['cabinet-kit'];

            return {
                module: extra.module,
                dir: path.resolve(path.dirname(installed), pkg['install-path'] ?? `../${pkg.name}`),
                admin: extra.admin ?? null,
                alias: extra.alias ?? `@${pkg.name.split('/').pop()}`,
            };
        });
}

// Имя страницы Inertia (`pages/catalog/Products`) ищется по окончанию пути,
// поэтому ключи глоба годятся как есть: страницы модуля лежат в `pages/<модуль>/`.
function modulePagesSource(modules, root) {
    const globs = modules
        .filter((module) => module.admin)
        .map((module) => {
            const pages = toPosix(path.relative(root, path.join(module.dir, module.admin, 'pages')));

            return `    ...import.meta.glob(${JSON.stringify(`/${pages}/**/*.vue`)}, { eager: true }),`;
        });

    return ['export default {', ...globs, '};', ''].join('\n');
}

// Пакет в разработке подменяется junction'ом, и файлы приходят уже по
// настоящему пути — вне корня проекта. Dev-сервер должен пускать и туда.
function withRealPaths(dirs) {
    const result = new Set();

    for (const dir of dirs) {
        result.add(dir);

        try {
            result.add(fs.realpathSync.native(dir));
        } catch {
            // Папки нет — пакет не установлен, пускать некуда.
        }
    }

    return [...result];
}

function createAliases(packageDir, root) {
    return [
        packageAlias('@cabinet-kit', packageDir, 'resources/js'),
        sharedAlias('@/_admin', packageDir, 'resources/_admin', root),
        packageAlias('@/scss', packageDir, 'resources/scss'),
        sharedAlias('@/js', packageDir, 'resources/js', root),
    ];
}

// Префикс, который всегда ведёт в пакет. Регистр пути проверяется на месте:
// иначе опечатка проходит на Windows и всплывает только на сервере сборки.
function packageAlias(find, packageDir, packagePath) {
    const base = path.join(packageDir, packagePath);

    return {
        find,
        replacement: base,
        customResolver(id, importer, options) {
            assertStoredCase(base, id, importer);

            return this.resolve(id, importer, { skipSelf: true, ...options })
                .then((resolved) => resolved ?? { id });
        },
    };
}

// Файл, найденный на Windows под чужим регистром, на Linux не существует. Такой
// импорт обрывается сразу и с понятной причиной, вместо «нет такого файла» на
// сервере: там путь выглядит правильным, и виноватой кажется установка пакета.
function assertStoredCase(base, id, importer) {
    if (process.platform !== 'win32') return;

    const target = id.split('?')[0];

    for (const suffix of RESOLVED_SUFFIXES) {
        const candidate = target + suffix;

        try {
            if (! fs.statSync(candidate).isFile()) continue;
        } catch {
            continue;
        }

        const asked = path.relative(base, candidate);
        const stored = path.relative(fs.realpathSync.native(base), fs.realpathSync.native(candidate));

        if (asked === stored) return;

        throw new Error(
            `[cabinet-kit] Регистр пути расходится с файлом на диске: запрошено "${toPosix(asked)}", `
            + `на диске "${toPosix(stored)}"${importer ? ` (импорт из ${toPosix(importer.split('?')[0])})` : ''}. `
            + 'На Windows такой импорт работает, на сервере сборки — нет.',
        );
    }
}

function toPosix(filePath) {
    return filePath.replace(/\\/g, '/');
}

// Папки с одинаковыми именами есть и у пакета, и у проекта, поэтому префикс импорта
// сам по себе не говорит, чей файл нужен: сторону задаёт импортирующий файл (свой
// берёт своё), а недостающее ищется на другой стороне. Иначе проект вынужден
// перехватывать префикс своими алиасами и ломает импорты внутри пакета.
function sharedAlias(find, packageDir, sharedPath, root) {
    const packageBase = path.join(packageDir, sharedPath);
    const hostBase = path.join(root, sharedPath);

    return {
        find,
        replacement: packageBase,
        customResolver(id, importer, options) {
            const relative = path.relative(packageBase, id);
            const sides = [
                { base: packageBase, id: path.join(packageBase, relative) },
                { base: hostBase, id: path.join(hostBase, relative) },
            ];

            if (! isInsideAny(importer, withRealPaths([packageDir]))) sides.reverse();

            const target = (sides.find(storesFile) ?? sides[0]).id;

            return this.resolve(target, importer, { skipSelf: true, ...options })
                .then((resolved) => resolved ?? { id: target });
        },
    };
}

function isBareImport(id) {
    return /^[\w@]/.test(id) && ! id.includes(':') && ! id.startsWith('@/');
}

function isInsideAny(filePath, directories) {
    return directories.some((directory) => isInside(filePath, directory));
}

function isInside(filePath, directory) {
    if (! filePath) return false;

    const relative = path.relative(directory, path.resolve(filePath.split('?')[0]));

    return relative !== '' && ! relative.startsWith('..') && ! path.isAbsolute(relative);
}

function storesFile({ base, id }) {
    return RESOLVED_SUFFIXES.some((suffix) => isFileStoredAs(base, id + suffix));
}

// Windows находит файл при любом регистре, сборка на Linux — нет. Путь, чей регистр
// расходится с записью на диске, считается отсутствующим: иначе выбор стороны зависит
// от платформы и проходит локально, а на сервере падает. Сравнивается только хвост
// под базовой папкой — сама она у проекта с локальной установкой пакета симлинк.
function isFileStoredAs(base, candidate) {
    try {
        if (! fs.statSync(candidate).isFile()) return false;
        if (process.platform !== 'win32') return true;

        const stored = path.relative(fs.realpathSync.native(base), fs.realpathSync.native(candidate));

        return stored === path.relative(base, candidate);
    } catch {
        return false;
    }
}

function normalizeAliases(alias) {
    if (! alias) return [];

    if (Array.isArray(alias)) {
        return alias;
    }

    return Object.entries(alias).map(([find, replacement]) => ({ find, replacement }));
}

function resolveHttps(option, root) {
    if (! option) return null;

    if (typeof option === 'object' && option.key && option.cert) {
        return { key: fs.readFileSync(option.key), cert: fs.readFileSync(option.cert) };
    }

    const domain = typeof option === 'string' ? option : path.basename(root);
    const dir = option?.certDir ?? `F:/OpenServer/data/ssl/projects/${domain}`;
    const key = `${dir}/cert.key`;
    const cert = `${dir}/cert.crt`;

    if (! fs.existsSync(key) || ! fs.existsSync(cert)) {
        console.warn(`[cabinet-kit] Certificate for "${domain}" was not found; Vite will stay on http.`);
        return null;
    }

    return { key: fs.readFileSync(key), cert: fs.readFileSync(cert) };
}
