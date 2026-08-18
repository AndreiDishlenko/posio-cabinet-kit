import { createI18n } from 'vue-i18n'

const messages = {
	en: {},
	uk: {},
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

export function detectBrowserLocale() {
	const offered = Object.keys(i18n.global.locales);
	const requested = (typeof navigator !== 'undefined' && (navigator.languages || [navigator.language]).filter(Boolean)) || [];

	for (const tag of requested) {
		const code = String(tag).toLowerCase().split('-')[0];
		if (offered.includes(code)) return code;
	}

	return i18n.global.default_locale;
}

export const $t = (str = '') => str || '';

export { i18n };
