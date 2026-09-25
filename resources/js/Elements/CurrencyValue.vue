<template>

	<span class="currency-value inline-flex items-baseline whitespace-nowrap leading-[1]"
		:class="[size_class, { 'currency-value--spaced': parts.spaced, 'font-bold': bold }]"
		>
		<span v-if="parts.sign && parts.position == 'before'" class="currency-sign font-normal" :class="sign_classes">{{ parts.sign }}</span>
		<span class="currency-amount tabular-nums">{{ parts.amount }}</span>
		<span v-if="parts.sign && parts.position == 'after'" class="currency-sign font-normal" :class="sign_classes">{{ parts.sign }}</span>
	</span>

</template>

<script>
	import { currencyParts } from '@/js/currencyFormat.js';

	// Шкала суммы смещена на два шага вверх относительно текстовой: деньги —
	// главное число экрана, и даже мелкий их размер крупнее текста рядом.
	// Размер живёт на корне, чтобы обозначение валюты считалось от суммы.
	const SIZE_CLASSES = {
		inherit: '',
		xs:      'text-sm',
		sm:      'text-base',
		md:      'text-xl',
		lg:      'text-2xl',
		xl:      'text-3xl',
		'2xl':   'text-4xl',
		'3xl':   'text-5xl',
		'4xl':   'text-6xl',
		'5xl':   'text-7xl',
		'6xl':   'text-8xl',
	};

	// Чем крупнее сумма, тем сильнее отстаёт обозначение валюты: на плашке итога
	// знак в один рост с числом перетягивает взгляд на себя. На текстовых
	// размерах и ниже отставания нет — знак равен тексту (доля задана в стилях).
	const SIGN_SCALED_SIZES = ['md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl'];

	const TONE_CLASSES = {
		mono:      '',
		accent:    'text-yellow',
		secondary: 'text-secondary',
		disabled:  'text-disabled',
	};

	export default {
		name: 'CurrencyValue',
		props: {
			value: {
				type: [String, Number],
				required: true,
			},
			// Знак валюты (₴, $). Не задан — берётся валюта аккаунта.
			currency: {
				type: String,
				default: '',
			},
			// Международный код (UAH, USD): по нему определяется сторона знака и
			// строятся словесные формы. Не задан — берётся валюта аккаунта.
			code: {
				type: String,
				default: '',
			},
			// symbol (₴) | code (UAH) | short (грн.) | name (гривня)
			display: {
				type: String,
				default: 'symbol',
			},
			// Цвет обозначения валюты: по умолчанию монохромный — тот же, что у суммы.
			tone: {
				type: String,
				default: 'mono', // mono | accent | secondary | disabled
			},
			// inherit — размер достаётся от родителя; остальные задают его сами.
			size: {
				type: String,
				default: 'lg', // inherit | xs | sm | md | lg | xl | 2xl | 3xl | 4xl | 5xl | 6xl
			},
			bold: {
				type: Boolean,
				default: true,
			},
			decimals: {
				type: Number,
				default: 2,
			},
			// Язык форматирования числа; по умолчанию — язык интерфейса.
			locale: {
				type: String,
				default: '',
			},
		},
		computed: {
			// Валюта аккаунта — общий случай для всех сумм сервиса, поэтому
			// повторять её в каждом месте вывода не требуется.
			account_currency() {
				return this.$settings?.account || {};
			},
			parts() {
				return currencyParts(this.value, {
					symbol:   this.currency || this.account_currency.currency_symbol || '',
					code:     this.code || this.account_currency.currency_name || '',
					display:  this.display,
					decimals: this.decimals,
					locale:   this.locale || this.$i18n?.locale || '',
				});
			},
			size_class() {
				return SIZE_CLASSES[this.size] === undefined ? SIZE_CLASSES.lg : SIZE_CLASSES[this.size];
			},
			sign_classes() {
				const scale = SIGN_SCALED_SIZES.indexOf(this.size) === -1 ? '' : `currency-sign--${this.size}`;
				return [scale, TONE_CLASSES[this.tone] || ''];
			},
		},
	}
</script>

<style lang="scss" scoped>

	// Отбивка и размер обозначения считаются от суммы, а не в пикселях: одна
	// разметка обслуживает и подпись плитки товара, и итог во весь экран.
	.currency-value--spaced > * + * {
		margin-inline-start: .2em;
	}

	// Доля знака от суммы убывает ровно по шагам шкалы: на текстовых размерах
	// знак в рост текста, на плашке итога во весь экран — самый мелкий.
	$sign-scale: (
		'md':  .92em,
		'lg':  .88em,
		'xl':  .84em,
		'2xl': .80em,
		'3xl': .76em,
		'4xl': .72em,
		'5xl': .68em,
		'6xl': .64em,
	);

	.currency-sign {
		font-size: 1em;
	}

	@each $size, $scale in $sign-scale {
		.currency-sign--#{$size} {
			font-size: $scale;
		}
	}

</style>
