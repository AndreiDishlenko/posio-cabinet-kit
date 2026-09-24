import { i18n } from './i18n.config.js';
import { applyLocale, browserLocale, storedLocale } from './localeSync.js';

// Общая настройка языка кабинета и публичного сайта хоста: оба запуска приложения
// берут язык, список языков и переводы из одного и того же ответа сервера.
export function hydrateI18n(payload = {}) {
    const locales = normalizeLocales(payload.locales);
    const localeCodes = Object.keys(locales);
    const fallbackLocale = payload.fallbackLocale || 'en';

    if (localeCodes.length) {
        i18n.global.locales = locales;
        i18n.global.supported_locales = localeCodes;
        i18n.global.default_locale = localeCodes.includes(fallbackLocale) ? fallbackLocale : localeCodes[0];
    }

    // В серверном рендере нет ни памяти браузера, ни его языка: без ответа сервера
    // остаётся язык по умолчанию.
    const locale = payload.locale
        || (typeof window === 'undefined' ? i18n.global.default_locale : storedLocale() || browserLocale());

    i18n.global.fallbackLocale = fallbackLocale;
    // Хост отдаёт переводы через lang/{locale}.json — они дополняют собранные
    // в бандл переводы, а не заменяют их полностью.
    i18n.global.setLocaleMessage(locale, {
        ...i18n.global.getLocaleMessage(locale),
        ...(payload.messages ?? {}),
    });
    applyLocale(locale);
}

export function normalizeLocales(locales = []) {
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

export function localizedRoute(name, locale, params = {}, absolute = undefined) {
    const localizedName = `${name}.${locale}`;

    try {
        return route(localizedName, params, absolute);
    } catch {
        return null;
    }
}
