// Единый формат денег для всего сервиса — по CLDR/Unicode.
//
// Разделение ответственности: локаль интерфейса задаёт разделители числа
// (1 250,00 против 1,250.00), валюта — сторону знака и отбивку ($1,250.00,
// но 1 250,00 ₴). Международный код всегда идёт перед суммой через пробел
// (UAH 1 250,00).

import { i18n, $t } from '@/js/i18n.config';

// Неразрывный пробел: сумма и знак валюты не должны разрываться переносом строки.
const NBSP = '\u00A0';

// Готовые форматтеры по набору параметров. Построение форматтера на старом
// движке стоит на порядки дороже самого форматирования, а наборов у сервиса
// единицы — тогда как сумм на экране (позиции чека, плитки товаров) сотни.
const formatters = {};

function numberFormatter(tag, settings) {
	const key = (tag || '') + '|' + settings.minimumFractionDigits
		+ '|' + settings.maximumFractionDigits
		+ '|' + (settings.useGrouping === false ? '0' : '1');

	if ( !formatters[key] )
		formatters[key] = new Intl.NumberFormat(tag, settings);

	return formatters[key];
}

// Знак стоит перед суммой вплотную — англоязычный ареал и валюты, унаследовавшие
// его запись со знаком доллара.
const SIGN_BEFORE_TIGHT = [
	'USD', 'GBP', 'CAD', 'AUD', 'NZD', 'HKD', 'SGD', 'TWD',
	'JPY', 'CNY', 'KRW', 'INR', 'ILS', 'PHP', 'THB',
	'BRL', 'MXN', 'ARS', 'CLP', 'COP',
];

// Знак перед суммой, но отбивается пробелом.
const SIGN_BEFORE_SPACED = ['CHF'];

// Опознание по знаку — на случай, когда рядом со знаком нет кода валюты.
const SYMBOLS_BEFORE_TIGHT = ['$', '£', '¥', '₹', '₪', '₩', '₱', '฿', 'R$', 'US$', 'C$', 'A$', 'HK$', 'S$', 'NT$'];

// Полные названия валют — ключи словаря интерфейса. Короткая форма переводится
// по самому коду («UAH» → «грн.»), поэтому отдельного словаря ей не нужно.
const FULL_NAME_KEYS = {
	UAH: 'Ukrainian hryvnia',
	USD: 'US dollar',
	EUR: 'Euro',
};

// Строка не сводится к числу — форматировать нечего (уже готовый текст, прочерк и т.п.).
function isNumeric(value) {
	if ( value === '' || value === null || value === undefined )
		return false;

	return isFinite(Number(value));
}

// Язык интерфейса; при недоступном i18n отдаём пустую строку — тогда число
// форматируется по настройкам браузера.
export function currentLocale() {
	const locale = i18n && i18n.global ? i18n.global.locale : '';

	return typeof locale === 'string' ? locale : (locale && locale.value) || '';
}

// Сторона и отбивка знака валюты. Код валюты надёжнее знака, но знать его
// обязаны не все вызывающие места.
export function currencySignPlacement(code, symbol) {
	const iso = String(code || '').trim().toUpperCase();

	if ( SIGN_BEFORE_TIGHT.indexOf(iso) !== -1 )
		return { position: 'before', spaced: false };

	if ( SIGN_BEFORE_SPACED.indexOf(iso) !== -1 )
		return { position: 'before', spaced: true };

	if ( !iso && SYMBOLS_BEFORE_TIGHT.indexOf(String(symbol || '').trim()) !== -1 )
		return { position: 'before', spaced: false };

	// Большинство валют мира, включая гривну и евро, ставят знак после суммы.
	return { position: 'after', spaced: true };
}

// Разделители числа текущей локали — нужны полю ввода, чтобы разобрать
// сгруппированную строку обратно в число.
export function numberSeparators(locale) {
	const tag = locale || currentLocale() || undefined;

	try {
		const parts = new Intl.NumberFormat(tag, { minimumFractionDigits: 2 }).formatToParts(12345.6);
		let group = '';
		let decimal = '.';

		for ( const part of parts ) {
			if ( part.type === 'group' )
				group = part.value;
			if ( part.type === 'decimal' )
				decimal = part.value;
		}

		return { group, decimal };
	} catch (e) {
		return { group: '', decimal: '.' };
	}
}

// Сумма без знака валюты: разряды и десятичный разделитель по локали интерфейса.
// Отрицательное число оформляет сама локаль (минус, а не скобки).
export function formatAmount(value, options) {
	const opts = options || {};

	if ( !isNumeric(value) )
		return value === null || value === undefined ? '' : String(value);

	const decimals = opts.decimals === undefined ? 2 : opts.decimals;
	const number = Number(value);
	const tag = opts.locale || currentLocale() || undefined;

	const settings = decimals >= 0
		? { minimumFractionDigits: decimals, maximumFractionDigits: decimals }
		: { maximumFractionDigits: 20 };

	if ( opts.grouping === false )
		settings.useGrouping = false;

	try {
		return numberFormatter(tag, settings).format(number);
	} catch (e) {
		return decimals >= 0 ? number.toFixed(decimals) : String(number);
	}
}

// Обозначение валюты в одной из четырёх форм: знак (₴), международный код (UAH),
// короткая словесная (грн.) и полное название (гривня). Словесные формы идут
// через словарь интерфейса, а без кода валюты выродиться им не во что — тогда
// показывается знак.
export function currencyLabel(code, symbol, display) {
	const iso = String(code || '').trim().toUpperCase();
	const sign = String(symbol || '').trim();

	if ( display === 'code' )
		return iso || sign;

	if ( display === 'short' )
		return iso ? $t(iso) : sign;

	if ( display === 'name' )
		return iso ? $t(FULL_NAME_KEYS[iso] || iso) : sign;

	return sign || iso;
}

// Сумма и обозначение валюты по отдельности — для компонентов, которые рисуют
// обозначение собственным элементом (свой цвет, свой размер).
export function currencyParts(value, options) {
	const opts = options || {};
	const amount = formatAmount(value, opts);
	const code = String(opts.code || '').trim().toUpperCase();
	const display = opts.display || 'symbol';
	const sign = currencyLabel(code, opts.symbol, display);

	if ( !sign )
		return { amount: amount, sign: '', position: 'after', spaced: false };

	// Международный код в мировой записи стоит перед суммой, словесные формы —
	// после неё; и то и другое всегда отбивается пробелом.
	if ( display === 'code' )
		return { amount: amount, sign: sign, position: 'before', spaced: true };

	if ( display === 'short' || display === 'name' )
		return { amount: amount, sign: sign, position: 'after', spaced: true };

	const placement = currencySignPlacement(code, sign);

	return { amount: amount, sign: sign, position: placement.position, spaced: placement.spaced };
}

// Готовая строка суммы со знаком валюты.
export function formatCurrency(value, options) {
	const parts = currencyParts(value, options);

	if ( !parts.sign )
		return parts.amount;

	const gap = parts.spaced ? NBSP : '';

	return parts.position === 'before'
		? parts.sign + gap + parts.amount
		: parts.amount + gap + parts.sign;
}
