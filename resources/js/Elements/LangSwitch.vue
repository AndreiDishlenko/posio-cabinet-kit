<template>

	<div class="lang-switch inline-flex items-center space-x-0.5"
		:class="`lang-switch--${variant}`"
		role="group"
		:aria-label="label"
		>

		<button class="lang-switch-item"
			v-for="item in locales"
			:key="item.code"
			type="button"
			:class="{ 'is-active': item.code === current }"
			:aria-pressed="item.code === current"
			:lang="item.code"
			@click="select(item.code)"
			>{{ item.label || item.code.toUpperCase() }}</button>

	</div>

</template>

<script>
	// Только отображение выбора: как применить язык (i18n, переход, запоминание)
	// решает тот, кто слушает событие, — поэтому переключатель годится и кабинету,
	// и публичному сайту со своим механизмом перевода.
	export default {
		name: 'LangSwitch',

		props: {
			locales: {
				type: Array,
				default: () => [],
			},
			current: {
				type: String,
				default: '',
			},
			label: {
				type: String,
				default: '',
			},
			// plain — строка кодов без подложки; pill — «пилюля» шапки кабинета.
			variant: {
				type: String,
				default: 'plain',
				validator: (value) => ['plain', 'pill'].includes(value),
			},
		},

		emits: ['change'],

		methods: {
			select(code) {
				if (code !== this.current)
					this.$emit('change', code);
			},
		},
	};
</script>

<style lang="scss" scoped>
	// Оформление задаётся переменными снаружи; значения по умолчанию — вид шапки кабинета:
	// без подложки, неактивный язык полупрозрачным текстом того же цвета.
	.lang-switch {
		padding: var(--lang-switch-padding, 0);
		background: var(--lang-switch-bg, transparent);
		border: var(--lang-switch-border, 0);
		border-radius: var(--lang-switch-radius, 9999px);
		color: var(--lang-switch-color, inherit);
	}

	// Геометрия и анимация «пилюли» зафиксированы, чтобы переключатель везде выглядел
	// как в шапке кабинета; снаружи меняются только цвета.
	.lang-switch--pill {
		height: 36px;
		padding: 0 1.5rem 1px;
		border: var(--lang-switch-border, 0);
		border-radius: 1.5rem;
		background: var(--lang-switch-bg, var(--button-background));
		color: var(--lang-switch-color, var(--button-text-color));
		box-shadow: 0 1px 2px 0 var(--lang-switch-shadow-color, rgba(28, 25, 23, 0.5));
		transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out, border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;

		&:hover {
			border-radius: 0.75rem;
			background: var(--lang-switch-hover-bg, transparent);
			box-shadow: none;
		}

		.lang-switch-item {
			padding: 0.125rem 0.375rem;
			border-radius: 0.25rem;
			// Размер из шкалы части сервиса: в кабинете она мельче, и фиксированное
			// значение выбивалось из остальных надписей шапки.
			font-size: var(--text-sm);
			font-weight: 500;
			letter-spacing: normal;
			line-height: 1.25rem;
		}
	}

	.lang-switch-item {
		padding: var(--lang-switch-item-padding, 0.125rem 0.375rem);
		border-radius: var(--lang-switch-item-radius, 0.25rem);
		font-size: var(--lang-switch-font-size, var(--text-sm));
		font-weight: var(--lang-switch-font-weight, 500);
		letter-spacing: var(--lang-switch-letter-spacing, normal);
		line-height: var(--lang-switch-line-height, 1.25rem);
		color: inherit;
		background: transparent;
		cursor: pointer;
		opacity: var(--lang-switch-idle-opacity, 0.5);
		transition: opacity 0.15s ease, background 0.15s ease, color 0.15s ease;

		&:not(.is-active):hover {
			opacity: var(--lang-switch-hover-opacity, 0.75);
		}

		&.is-active {
			opacity: 1;
			color: var(--lang-switch-active-color, inherit);
			background: var(--lang-switch-active-bg, transparent);
		}

		&:focus-visible {
			outline: 1px solid currentColor;
			outline-offset: 1px;
		}
	}
</style>
