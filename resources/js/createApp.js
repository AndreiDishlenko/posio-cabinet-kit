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
            app.config.globalProperties.$t = translate;
            app.config.globalProperties.$i18n = createI18nContext(app);

            hostSetup?.(app);
            app.mount(el);
        },
    });
}

function translate(key, replacements = {}) {
    const messages = this?.$page?.props?.cabinetKitI18n?.messages ?? {};
    const translated = messages[key] ?? key;

    return interpolate(translated, replacements);
}

function interpolate(message, replacements) {
    return Object.entries(replacements ?? {}).reduce((value, [key, replacement]) => (
        value
            .replaceAll(`:${key}`, replacement)
            .replaceAll(`{${key}}`, replacement)
    ), String(message));
}

function createI18nContext(app) {
    return {
        get locale() {
            return app.config.globalProperties.$page?.props?.cabinetKitI18n?.locale;
        },
        get fallbackLocale() {
            return app.config.globalProperties.$page?.props?.cabinetKitI18n?.fallbackLocale;
        },
        get messages() {
            return app.config.globalProperties.$page?.props?.cabinetKitI18n?.messages ?? {};
        },
        get locales() {
            return app.config.globalProperties.$page?.props?.cabinetKitI18n?.locales ?? [];
        },
    };
}
