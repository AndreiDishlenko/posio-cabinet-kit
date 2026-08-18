import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';

import { createEmitter } from './emitter.js';
import { resolveCabinetKitPage } from './resolvePage.js';
import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/inter/800.css';
import '@fontsource/inter/900.css';
import '@fontsource/roboto';
import '@fontsource/open-sans';
import '@fontsource/inter-tight';
import '@fontsource/pt-sans/700.css';
import '@vuepic/vue-datepicker/dist/main.css';
import '../scss/cabinet-kit.scss';

export function createCabinetKitApp({ overrides = {}, title, progress, setup: hostSetup } = {}) {
    return createInertiaApp({
        title,
        progress: progress ?? { color: '#4B5563' },
        resolve: (name) => resolveCabinetKitPage(
            name,
            overrides,
            {
                ...import.meta.glob('../_admin/js/pages/**/*.vue', { eager: true }),
                ...import.meta.glob('./pages/**/*.vue', { eager: true }),
            },
        ),
        setup({ el, App, props, plugin }) {
            applyDefaultTheme();
            installOriginalRouteAliases();

            const app = createApp({ render: () => h(App, props) });

            app.use(plugin);
            app.use(ZiggyVue);
            app.config.globalProperties.$emitter = createEmitter();
            app.config.globalProperties.$t = translate;
            app.config.globalProperties.$i18n = createI18nContext(app);
            app.config.globalProperties.$apiClient = createApiClient();
            app.config.globalProperties.$toast = createToast();
            app.config.globalProperties.$accountService = createAccountService();
            app.config.globalProperties.$settings = createSettingsService();
            app.config.globalProperties.$inprogress = { value: false };

            hostSetup?.(app);
            app.mount(el);
        },
    });
}

function installOriginalRouteAliases() {
    if (typeof window === 'undefined' || typeof window.route !== 'function') {
        return;
    }

    const baseRoute = window.route;
    const aliases = {
        'cabinet.settings': 'cabinet-kit.settings',
        'cabinet.settings.update': 'cabinet-kit.profile.update.post',
        'cabinet.settings.avatar': 'cabinet-kit.profile.avatar',
        'cabinet.account.set': 'cabinet-kit.account.set',
        'cabinet.account.member.invite': 'cabinet-kit.account.member.invite',
        'cabinet.account.member.role': 'cabinet-kit.account.member.role',
        'cabinet.account.member.remove': 'cabinet-kit.account.member.remove',
        'admin.user.update': 'cabinet-kit.users.update.post',
        'admin.role.togglepermission': 'cabinet-kit.permissions.toggle',
        'admin.permission.store': 'cabinet-kit.permissions.store',
        'admin.permission.update': 'cabinet-kit.permissions.rename.post',
    };

    window.route = (name, params, absolute, config) => {
        const routeName = aliases[name] ?? name;
        return baseRoute(routeName, params, absolute, config);
    };
}

function createApiClient() {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const request = async (method, url, data) => {
        if (method === 'GET' && data && Object.keys(data).length) {
            const query = new URLSearchParams(data).toString();
            url = `${url}${url.includes('?') ? '&' : '?'}${query}`;
        }

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(csrf() ? { 'X-CSRF-TOKEN': csrf() } : {}),
            },
            body: method === 'GET' || data === undefined ? undefined : JSON.stringify(data),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw payload;

        return { data: payload };
    };

    return {
        get: (url, data) => request('GET', url, data),
        post: (url, data) => request('POST', url, data),
        setCustomHeader: () => {},
    };
}

function createToast() {
    return {
        success: (message) => console.info(message),
        error: (message) => console.error(message),
    };
}

function createAccountService() {
    return {
        setAccount: () => {},
    };
}

function createSettingsService() {
    const storageKey = (key) => `cabinet:${key}`;
    const read = (key, fallback = null) => {
        if (typeof localStorage === 'undefined') return fallback;

        const value = localStorage.getItem(storageKey(key));
        if (value === null) return fallback;

        try {
            return JSON.parse(value);
        } catch {
            return fallback; 
        }
    };
    const write = (key, value) => {
        if (typeof localStorage === 'undefined') return;

        localStorage.setItem(storageKey(key), JSON.stringify(value));
    };
    const pageStateKey = (pageKey, accountId = null) => {
        const account = String(accountId ?? 'default');

        return `page_state:${account}:${pageKey}`;
    };

    return {
        getSetting: read,
        setSetting: write,
        removeItem: (key) => {
            if (typeof localStorage === 'undefined') return;

            localStorage.removeItem(storageKey(key));
        },
        getPageState: (pageKey, accountId = null) => read(pageStateKey(pageKey, accountId), {}),
        mergePageState: (pageKey, accountId = null, partial = {}) => {
            const current = read(pageStateKey(pageKey, accountId), {});

            write(pageStateKey(pageKey, accountId), {
                ...current,
                ...partial,
            });
        },
    };
}

function applyDefaultTheme() {
    const root = document.documentElement;

    if (!root.classList.contains('dark') && !root.classList.contains('light')) {
        root.classList.add('dark');
    }
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
