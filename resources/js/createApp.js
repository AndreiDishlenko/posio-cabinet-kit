import { createInertiaApp } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { createApp, h } from 'vue';
import { route as ziggyRoute } from 'ziggy-js';

import { createEmitter } from './emitter.js';
import { i18n } from './i18n.config.js';
import { applyLocale, browserLocale, storedLocale } from './localeSync.js';
import { $inprogress, $modal_inprogress, $pauseApplication } from './pauseApplication.js';
import Helpers from './posio/helpers.js';
import { resolveCabinetKitPage } from './resolvePage.js';
import './vee-validator.js';
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
            hydrateI18n(props.initialPage?.props?.cabinetKitI18n);

            const app = createApp({ render: () => h(App, props) });
            const emitter = createEmitter();

            app.use(plugin);
            installRouteHelper(app);
            app.use(i18n);
            app.use(Helpers);
            app.config.globalProperties.$emitter = emitter;
            app.config.globalProperties.$dayjs = dayjs;
            app.config.globalProperties.$locRoute = localizedRoute;
            app.config.globalProperties.$apiClient = createApiClient();
            app.config.globalProperties.$toast = createToast();
            app.config.globalProperties.$popup = createPopup();
            app.config.globalProperties.$accountService = createAccountService();
            app.config.globalProperties.$settings = createSettingsService();
            app.config.globalProperties.$pauseApplication = $pauseApplication;
            app.config.globalProperties.$inprogress = $inprogress;
            app.config.globalProperties.$modal_inprogress = $modal_inprogress;
            $pauseApplication.init(emitter);
            app.config.globalProperties.$is_mobile = { value: typeof window !== 'undefined' ? window.innerWidth <= 640 : false };
            app.config.globalProperties.$is_tablet = { value: typeof window !== 'undefined' ? window.innerWidth <= 1024 : false };
            app.config.globalProperties.$dictionaries = createDictionaries();

            hostSetup?.(app);
            app.mount(el);
        },
    });
}

// Имена маршрутов исходного кабинета, от которых могли остаться ссылки в
// переопределениях хоста: пакет зарегистрировал те же экраны под своим префиксом.
const ROUTE_ALIASES = {
    'cabinet.settings': 'cabinet-kit.settings',
    'cabinet.settings.update': 'cabinet-kit.profile.update.post',
    'cabinet.settings.avatar': 'cabinet-kit.profile.avatar',
    'cabinet.account.set': 'cabinet-kit.account.set',
    'cabinet.account.update': 'cabinet-kit.account.update',
    'cabinet.account.addlogo': 'cabinet-kit.account.addlogo',
    'cabinet.account.member.invite': 'cabinet-kit.account.member.invite',
    'cabinet.account.member.role': 'cabinet-kit.account.member.role',
    'cabinet.account.member.remove': 'cabinet-kit.account.member.remove',
    'admin.user.update': 'cabinet-kit.users.update.post',
    'admin.role.togglepermission': 'cabinet-kit.permissions.toggle',
    'admin.permission.store': 'cabinet-kit.permissions.store',
    'admin.permission.update': 'cabinet-kit.permissions.rename.post',
};

/**
 * Ziggy отдаёт свой помощник только внутрь компонентов, а часть кода кабинета
 * (миксины таблиц/карточек, сервисы) зовёт его из обычных модулей — поэтому тот
 * же помощник кладётся ещё и в глобальную область.
 *
 * Штатный плагин Ziggy здесь не подключается намеренно: он ставит своё имя
 * маршрута глобальной примесью, которая перекрывает наше — подмена устаревших
 * имён тогда не срабатывала бы в шаблонах, а повторная выдача одного ключа
 * давала предупреждение при загрузке.
 */
function installRouteHelper(app) {
    const resolve = (name, params, absolute, config) => ziggyRoute(
        resolveRouteName(name, config),
        params,
        absolute,
        config,
    );

    app.config.globalProperties.route = resolve;
    app.provide('route', resolve);
    globalThis.route = resolve;
}

// Приоритет у маршрута, который хост зарегистрировал сам: подмена нужна только
// когда старого имени в списке нет.
function resolveRouteName(name, config) {
    const alias = ROUTE_ALIASES[name];

    if (! alias) {
        return name;
    }

    try {
        return ziggyRoute(undefined, undefined, undefined, config).has(name) ? name : alias;
    } catch {
        return alias;
    }
}

function hydrateI18n(payload = {}) {
    const locales = normalizeLocales(payload.locales);
    const localeCodes = Object.keys(locales);
    const fallbackLocale = payload.fallbackLocale || 'en';
    const locale = payload.locale || storedLocale() || browserLocale();

    if (localeCodes.length) {
        i18n.global.locales = locales;
        i18n.global.supported_locales = localeCodes;
        i18n.global.default_locale = localeCodes.includes(fallbackLocale) ? fallbackLocale : localeCodes[0];
    }

    i18n.global.fallbackLocale = fallbackLocale;
    i18n.global.setLocaleMessage(locale, payload.messages ?? {});
    applyLocale(locale);
}

function normalizeLocales(locales = []) {
    if (Array.isArray(locales)) {
        return locales.reduce((result, locale) => {
            if (locale?.code) result[locale.code] = locale;
            return result;
        }, {});
    }

    return Object.entries(locales).reduce((result, [code, locale]) => {
        result[code] = typeof locale === 'object' ? { code, ...locale } : { code, name: locale };
        return result;
    }, {});
}

function localizedRoute(name, locale, params = {}, absolute = undefined) {
    const localizedName = `${name}.${locale}`;

    try {
        return route(localizedName, params, absolute);
    } catch {
        return null;
    }
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

// Подтверждение необратимых действий. Оформленный диалог — дело хоста: он может
// подменить помощник своим, поэтому здесь только браузерный запрос как основа.
function createPopup() {
    let open = false;

    return {
        confirm_yn: async (message) => {
            if (typeof window === 'undefined') return false;

            open = true;
            const result = window.confirm(message);
            open = false;

            return result;
        },
        isOpen: () => open,
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

function createDictionaries() {
    return {
        currencies: [
            { id: 'UAH', name: 'UAH' },
            { id: 'USD', name: 'USD' },
            { id: 'EUR', name: 'EUR' },
        ],
    };
}

function applyDefaultTheme() {
    const root = document.documentElement;

    if (!root.classList.contains('dark') && !root.classList.contains('light')) {
        root.classList.add('dark');
    }
}
