import axios from 'axios'

import { i18n } from '@/js/i18n.config'

// Славянские языки обслуживаем украинской версией — то же правило, что и на бэкенде.
const SLAVIC = ['ru', 'be', 'uk', 'kk', 'uz', 'ky', 'sr', 'bg', 'mo', 'mk'];

export function isSupportedLocale(locale) {
	return !!locale && i18n.global.supported_locales.includes(locale);
}

// Язык браузера, приведённый к поддерживаемому. Незнакомый язык — не повод
// показывать английский: отдаём язык по умолчанию.
export function browserLocale() {
	const lang = String(navigator.language || navigator.userLanguage || '')
		.slice(0, 2)
		.toLowerCase();

	if ( SLAVIC.includes(lang) )
		return 'uk';

	return isSupportedLocale(lang) ? lang : i18n.global.default_locale;
}

// Применение языка: перевод, атрибут документа и память между визитами.
// Непригодное значение не применяем — иначе переводы остались бы без языка вовсе.
export function applyLocale(locale) {
	if ( !isSupportedLocale(locale) )
		return false;

	i18n.global.locale = locale;

	// В серверном рендере браузерного окружения нет — там уместен только сам перевод.
	if ( typeof document === 'undefined' )
		return true;

	document.documentElement.lang = locale;

	try {
		localStorage.setItem('locale', locale);
	} catch { /* приватный режим — просто не запоминаем */ }

	return true;
}

export function storedLocale() {
	try {
		const locale = localStorage.getItem('locale');
		return isSupportedLocale(locale) ? locale : null;
	} catch {
		return null;
	}
}

// Закрепление выбора на сервере: сессия, кука и — для авторизованного — профиль.
// Намеренно обычным запросом, а не переходом Inertia: переключатель часто сразу
// уводит на локализованный адрес, и переход отменил бы незавершённый визит.
export function rememberLocale(locale) {
	if ( !isSupportedLocale(locale) )
		return Promise.resolve(false);

	const url = typeof globalThis.route === 'function'
		? globalThis.route('app.setlocale')
		: '/setlocale';

	return axios.post(url, { locale })
		.then(() => true)
		.catch(() => false);
}
