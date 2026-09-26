import { createI18n } from 'vue-i18n'

import admin_en from '../locales/admin_en.js'
import admin_uk from '../locales/admin_uk.js'

const messages = {
	en: { ...admin_en },
	uk: { ...admin_uk },
	ru: {},
};

const i18n = createI18n({
	legacy: true,
	locale: 'uk',
	fallbackLocale: { ru: ['uk', 'en'], default: ['en'] },
	messages,
	silentFallbackWarn: true,
	missing: () => null,
});

i18n.global.locales = {
	uk: {
		code: 'uk',
		name: 'Ukrainian',
		icon: 'emojione:flag-for-ukraine',
	},
	en: {
		code: 'en',
		name: 'English',
		icon: 'emojione:flag-for-united-kingdom',
	},
};

i18n.global.supported_locales = ['uk', 'en'];
i18n.global.default_locale = 'uk';

// Настройки, которые хост меняет через расширение переводов; по умолчанию — поведение пакета.
const host_i18n = {
	browser_locale_aliases: {},
	translate_module_t: false,
};

export function detectBrowserLocale() {
	const offered = Object.keys(i18n.global.locales);
	const requested = (typeof navigator !== 'undefined' && (navigator.languages || [navigator.language]).filter(Boolean)) || [];

	for (const tag of requested) {
		const code = String(tag).toLowerCase().split('-')[0];
		if (offered.includes(code)) return code;

		// Язык, на который интерфейс не переведён, хост может обслуживать близким.
		const alias = host_i18n.browser_locale_aliases[code];
		if (alias && offered.includes(alias)) return alias;
	}

	return i18n.global.default_locale;
}

/**
 * Переводы и языки хоста поверх кабинетных. Вызывать в точке входа до первого
 * перевода: модульный перевод и правила проверки форм читают общий экземпляр.
 *
 *     extendI18n({
 *         baseMessages: { uk: site_uk },   // уступают кабинетным при совпадении ключа
 *         messages:     { uk: chat_uk },   // перекрывают кабинетные
 *         supportedLocales: ['uk', 'en', 'ru'],
 *         browserLocaleAliases: { ru: 'uk' },
 *         translateModuleT: true,
 *     });
 *
 * Слияние поверхностное: одноимённый ключ заменяется целиком.
 */
export function extendI18n({
	baseMessages = {},
	messages: host_messages = {},
	supportedLocales = null,
	browserLocaleAliases = null,
	translateModuleT = null,
} = {}) {
	for (const [locale, bundle] of Object.entries(baseMessages))
		i18n.global.setLocaleMessage(locale, { ...bundle, ...i18n.global.getLocaleMessage(locale) });

	for (const [locale, bundle] of Object.entries(host_messages))
		i18n.global.setLocaleMessage(locale, { ...i18n.global.getLocaleMessage(locale), ...bundle });

	if (supportedLocales)
		i18n.global.supported_locales = [...supportedLocales];

	if (browserLocaleAliases)
		host_i18n.browser_locale_aliases = { ...browserLocaleAliases };

	if (translateModuleT !== null)
		host_i18n.translate_module_t = !!translateModuleT;
}

// Перевод строки с числом внутри: число уходит в подстановку {number}.
i18n.global.tNumbered = function(message) {
	const match = message.match(/(\d+)/);

	if (!match)
		return this.t(message);

	const before = message.substring(0, match.index);
	const number = match[0];
	const after  = message.substring(match.index + number.length);

	return this.t(before + '{number}' + after, { number });
};

i18n.global.tArray = function(array, prefix) {
	const result = [];

	Object.keys(array).forEach((key) => {
		result[key] = prefix + i18n.global.tNumbered(array[key]);
	});

	return result;
};

// Перевод вне компонентов. По умолчанию возвращает ключ как есть — хост включает
// перевод через расширение переводов.
export const $t = (str = '', params = {}) => {
	if (!str)
		return '';

	return host_i18n.translate_module_t ? i18n.global.t(str, params) : str;
};

export { i18n };
