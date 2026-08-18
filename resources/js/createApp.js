import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';

import { createEmitter } from './emitter.js';
import { resolveCabinetKitPage } from './resolvePage.js';
import '../scss/cabinet-kit.scss';

export function createCabinetKitApp({ overrides = {}, title, progress, setup: hostSetup } = {}) {
    return createInertiaApp({
        title,
        progress: progress ?? { color: '#4B5563' },
        resolve: (name) => resolveCabinetKitPage(
            name,
            overrides,
            import.meta.glob('./pages/**/*.vue', { eager: true }),
        ),
        setup({ el, App, props, plugin }) {
            const app = createApp({ render: () => h(App, props) });

            app.use(plugin);
            app.use(ZiggyVue);
            app.config.globalProperties.$emitter = createEmitter();

            hostSetup?.(app);
            app.mount(el);
        },
    });
}
