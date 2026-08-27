import fs from 'fs';
import path from 'path';

const PACKAGE_DIR = 'vendor/posio/cabinet-kit';

// Чем достраивается путь без расширения, прежде чем считать файл отсутствующим.
const RESOLVED_SUFFIXES = ['', '.vue', '.ts', '.js', '.mjs', '.json', '/index.vue', '/index.ts', '/index.js'];

export default function cabinetKit(options = {}) {
    const root = options.root ?? process.cwd();
    const packageDir = path.resolve(root, PACKAGE_DIR);
    const aliases = createAliases(packageDir, root);

    return {
        name: 'cabinet-kit',
        config(userConfig) {
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
                server: { fs: { allow: [root, packageDir] } },
            };

            const https = resolveHttps(options.https, root);
            if (https) {
                config.server.https = https;
                config.server.hmr = { protocol: 'wss', ...(options.hmr ?? {}) };
            }

            return config;
        },
    };
}

function createAliases(packageDir, root) {
    return [
        { find: '@cabinet-kit', replacement: path.join(packageDir, 'resources/js') },
        sharedAlias('@/_admin', packageDir, 'resources/_admin', root),
        { find: '@/scss', replacement: path.join(packageDir, 'resources/scss') },
        sharedAlias('@/js', packageDir, 'resources/js', root),
    ];
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

            if (! isInside(importer, packageDir)) sides.reverse();

            const target = (sides.find(storesFile) ?? sides[0]).id;

            return this.resolve(target, importer, { skipSelf: true, ...options })
                .then((resolved) => resolved ?? { id: target });
        },
    };
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
