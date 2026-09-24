import { router } from '@inertiajs/vue3';

import { i18n } from './i18n.config.js';
import { hydrateI18n, localizedRoute } from './i18nHydration.js';

// Переводы кабинета для публичного сайта хоста. Экземпляр тот же, что у кабинета:
// выбранный на сайте язык сервер и кабинет узнают без отдельной договорённости.
//
//     app.use(cabinetKitSiteI18n, { messages: { uk: siteUk }, page: props.initialPage });
//
// messages — переводы сайта по языкам, дополняют переводы кабинета.
// page — первая страница Inertia: с неё берётся язык, выбранный сервером.
// sourceLocale — язык, на котором написаны ключи словарей сайта (по умолчанию
// ключи английские). Для него ключ и есть текст, отдельный словарь не нужен.
export default {
    install(app, { messages = {}, page = null, sourceLocale = null } = {}) {
        if (sourceLocale)
            messages = withSourceTexts(messages, sourceLocale);

        for (const [locale, bundle] of Object.entries(messages))
            i18n.global.mergeLocaleMessage(locale, bundle);

        hydrateI18n(page?.props?.cabinetKitI18n);

        // Язык мог смениться на сервере между переходами (другая вкладка, вход в
        // кабинет), поэтому ответ каждой страницы применяется заново.
        if (typeof window !== 'undefined') {
            router.on('navigate', (event) => {
                const payload = event.detail.page?.props?.cabinetKitI18n;

                if (payload)
                    hydrateI18n(payload);
            });
        }

        app.use(i18n);
        app.config.globalProperties.$locRoute = localizedRoute;
    },
};

// Ключи на языке сайта отдаются на нём же как есть. Без этого текст на этом языке
// искался бы в запасном языке кабинета (английском) и показывался бы переводом.
function withSourceTexts(messages, sourceLocale) {
    const own = { ...(messages[sourceLocale] ?? {}) };

    for (const [locale, bundle] of Object.entries(messages)) {
        if (locale === sourceLocale)
            continue;

        for (const key of Object.keys(bundle))
            if (!(key in own))
                own[key] = literalMessage(key);
    }

    return { ...messages, [sourceLocale]: own };
}

// Текст как сообщение vue-i18n: подстановки {name} остаются, а знаки разметки
// связанных сообщений и форм множественного числа выводятся буквально.
function literalMessage(text) {
    return text.replace(/[@|]/g, (sign) => `{'${sign}'}`);
}
