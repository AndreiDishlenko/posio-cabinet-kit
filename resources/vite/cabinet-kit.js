import fs from 'fs';
import path from 'path';

const PACKAGE_DIR = 'vendor/posio/cabinet-kit';

export default function cabinetKit(options = {}) {
    const root = options.root ?? process.cwd();
    const packageDir = path.resolve(root, PACKAGE_DIR);
    const aliases = createAliases(packageDir);

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

function createAliases(packageDir) {
    return [
        { find: '@cabinet-kit', replacement: path.join(packageDir, 'resources/js') },
        { find: '@/_admin', replacement: path.join(packageDir, 'resources/_admin') },
        { find: '@/scss', replacement: path.join(packageDir, 'resources/scss') },
        { find: '@/js/Components', replacement: path.join(packageDir, 'resources/js/Components') },
        { find: '@/js', replacement: path.join(packageDir, 'resources/js') },
    ];
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
