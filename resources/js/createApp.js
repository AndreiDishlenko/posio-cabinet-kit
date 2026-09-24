import { createInertiaApp, router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import { createApp, h, reactive } from 'vue';
import { createVfm } from 'vue-final-modal';
import { route as ziggyRoute } from 'ziggy-js';

import CabinetApiClient from '../_admin/js/services/CabinetApiClient.js';
import { i18n } from './i18n.config.js';
import { hydrateI18n, localizedRoute } from './i18nHydration.js';
import { $inprogress, $modal_inprogress, $pauseApplication } from './pauseApplication.js';
import Helpers from './posio/helpers.js';
import { DictionariesClass } from './posio/system/DictionariesClass.js';
import { Emitter } from './posio/system/Emitter.js';
import { Popup } from './posio/system/Popup.js';
import { Toast } from './posio/system/ToastMessages.js';
import { takeOverBackButton } from './overlayHistory.js';
import { resolveCabinetKitPage } from './resolvePage.js';
// Страницы установленных модулей собирает плагин сборки пакета.
import cabinetKitModulePages from 'virtual:cabinet-kit-modules';
import './vee-validator.js';
// Перенесённый код кабинета пишет в журнал через console.msg / console.wrn —
// подключение модуля их и определяет.
import './posio/system/ConsoleService.js';
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
import 'vue-final-modal/style.css';
import '../scss/cabinet-kit.scss';

export function createCabinetKitApp({
    overrides = {},
    title,
    progress,
    setup: hostSetup,
    // Эндпоинт справочников принадлежит хосту: пакет только знает, под каким
    // именем маршрута его искать (первое найденное имя выигрывает).
    dictionariesRoute = ['cabinet-kit.api.dictionaries', 'cabinet.api.dictionaries'],
    dictionariesStorage = 'dict_cabinet',
} = {}) {
    // Раньше маршрутизатора: закрытие карточки забирает свою запись из истории, и
    // это событие не должно доходить до него — иначе каждое закрытие карточки
    // пересобирает страницу с нуля и заново загружает список.
    takeOverBackButton();

    return createInertiaApp({
        title,
        progress: progress ?? { color: '#4B5563' },
        // Порядок поиска: переопределения хоста → страницы модулей → страницы пакета.
        resolve: (name) => resolveCabinetKitPage(
            name,
            overrides,
            {
                ...cabinetKitModulePages,
                // Табы настроек — не страницы: их лениво грузит сама страница настроек.
                ...import.meta.glob(['../_admin/js/pages/**/*.vue', '!../_admin/js/pages/Settings/CabinetSettings*Tab.vue'], { eager: true }),
                ...import.meta.glob('./pages/**/*.vue', { eager: true }),
            },
        ),
        setup({ el, App, props, plugin }) {
            applyDefaultTheme();
            hydrateI18n(props.initialPage?.props?.cabinetKitI18n);

            // Переводы хоста отдаются только маршрутами кабинета: при входе в
            // кабинет переходом со страницы авторизации приложение уже запущено
            // без них, поэтому подхватываем их с каждой страницей, где они есть.
            router.on('navigate', (event) => {
                const payload = event.detail.page?.props?.cabinetKitI18n;

                if (payload)
                    hydrateI18n(payload);
            });

            const app = createApp({ render: () => h(App, props) });
            const emitter = Emitter;

            app.use(plugin);
            installRouteHelper(app);
            app.use(i18n);
            app.use(Helpers);
            app.use(createVfm());
            app.use(Toast);

            const settings = createSettingsService();
            const apiClient = CabinetApiClient(settings);

            // Диалоги кабинета оформлены акцентно (см. оформление в стилях кабинета).
            Popup.useAccentedDialogs();

            app.config.globalProperties.$emitter = emitter;
            app.config.globalProperties.$dayjs = dayjs;
            app.config.globalProperties.$locRoute = localizedRoute;
            app.config.globalProperties.$apiClient = apiClient;
            app.config.globalProperties.$popup = Popup;
            app.config.globalProperties.$accountService = createAccountService();
            app.config.globalProperties.$settings = settings;
            app.config.globalProperties.$pauseApplication = $pauseApplication;
            app.config.globalProperties.$inprogress = $inprogress;
            app.config.globalProperties.$modal_inprogress = $modal_inprogress;
            $pauseApplication.init(emitter);
            app.config.globalProperties.$is_mobile = { value: typeof window !== 'undefined' ? window.innerWidth <= 640 : false };
            app.config.globalProperties.$is_tablet = { value: typeof window !== 'undefined' ? window.innerWidth <= 1024 : false };
            app.config.globalProperties.$dictionaries = createDictionaries(apiClient, dictionariesRoute, dictionariesStorage);

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
    'cabinet.sitesettings': 'cabinet-kit.sitesettings',
    'cabinet.cabinetsettings': 'cabinet-kit.cabinetsettings',
    'cabinet.seo': 'cabinet-kit.seo',
    'admin.api.sitesettings.index': 'cabinet-kit.api.sitesettings.index',
    'admin.api.sitesettings.update': 'cabinet-kit.api.sitesettings.update',
    'admin.api.sitesettings.theme': 'cabinet-kit.api.sitesettings.theme',
    'admin.api.sitesettings.image': 'cabinet-kit.api.sitesettings.image',
    'admin.api.sitesettings.image.delete': 'cabinet-kit.api.sitesettings.image.delete',
    'cabinet.api.seodata': 'cabinet-kit.api.seodata',
    'cabinet.api.seodata.update': 'cabinet-kit.api.seodata.update',
    'cabinet.api.seodata.delete': 'cabinet-kit.api.seodata.delete',
    'cabinet.api.seodata.restore': 'cabinet-kit.api.seodata.restore',
    'cabinet.api.seodata.generatemeta': 'cabinet-kit.api.seodata.generatemeta',
    'cabinet.api.createsitemaps': 'cabinet-kit.api.createsitemaps',
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
    // Общий для всех страниц срез состояния аккаунта: выбранное здесь (точка
    // продаж) переезжает между страницами вместе с пользователем.
    const globalStateKey = (accountId = null) => `global_state:${String(accountId ?? 'default')}`;

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
        getGlobalState: (accountId = null) => read(globalStateKey(accountId), {}),
        setGlobalState: (accountId = null, state = {}) => write(globalStateKey(accountId), state),
        mergeGlobalState: (accountId = null, partial = {}) => {
            const current = read(globalStateKey(accountId), {});

            write(globalStateKey(accountId), {
                ...current,
                ...partial,
            });
        },
    };
}

// Справочники читаются через реактивную обёртку: экземпляр должен писать в неё
// же, иначе обновление из другой вкладки до шаблонов не доходит.
function createDictionaries(apiClient, routeNames, storageName) {
    const instance = new DictionariesClass(storageName, apiClient, firstResolvableRoute(routeNames));
    const dictionaries = reactive(instance);

    instance._proxy = dictionaries;

    return dictionaries;
}

// Маршрута справочников у пакета своего нет — отсутствие имени в списке хоста
// не повод ронять загрузку кабинета.
function firstResolvableRoute(names) {
    for (const name of [].concat(names)) {
        try {
            return route(name);
        } catch {
            continue;
        }
    }

    console.warn('[cabinet-kit] Dictionaries endpoint is not registered — dictionaries stay empty.');

    return null;
}

function applyDefaultTheme() {
    const root = document.documentElement;

    if (!root.classList.contains('dark') && !root.classList.contains('light')) {
        root.classList.add('dark');
    }
}
