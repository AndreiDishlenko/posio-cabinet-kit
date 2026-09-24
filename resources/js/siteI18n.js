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
export default {
    install(app, { messages = {}, page = null } = {}) {
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
