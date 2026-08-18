import fs from 'fs';
import path from 'path';

const PACKAGE_DIR = 'vendor/posio/cabinet-kit';

export default function cabinetKit(options = {}) {
    const root = options.root ?? process.cwd();
    const packageDir = path.resolve(root, PACKAGE_DIR);

    return {
        name: 'cabinet-kit',
        config() {
            const config = {
                resolve: {
                    alias: {
                        '@cabinet-kit': path.join(packageDir, 'resources/js'),
                        '@/_admin': path.join(packageDir, 'resources/_admin'),
                        '@/js/Components': path.join(packageDir, 'resources/js/Components'),
                        '@/js': path.join(packageDir, 'resources/js'),
                    },
                },
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
