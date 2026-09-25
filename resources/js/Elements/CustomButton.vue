<template>

	<button
		v-if="!hidden"
		type="button"
		:class="[
			'button',
			sizeClass,
			variantClass,
			fontSizeClass,
			{ 'btn--icon-only': iconOnly },
		]"
		:disabled="disabled"
		v-bind="$attrs"
		@click="$emit('click', $event)"
	>

		<Icon v-if="icon && iconPosition === 'left'" :icon="icon" :class="['icon', iconSizeClass]" />
		<template v-if="!iconOnly && $slots.default">
			<slot v-if="raw" />
			<span v-else class="btn__label"><slot /></span>
		</template>
		<Icon v-if="icon && iconPosition === 'right'" :icon="icon" :class="['icon', iconSizeClass]" />
	
	</button>

</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'CustomButton',

		components: { Icon },

		inheritAttrs: false,

		emits: ['click'],

		props: {
			type: {
				type: String,
				default: 'default',
				// 'default' | 'primary' | 'outline' | 'ghost' | 'plain' | 'link' | 'danger' | 'pill' | 'badge' | 'badge-success' | 'badge-error' | 'badge-muted'
			},
			size: {
				type: String,
				default: 'md',
				// 'xs' | 'sm' | 'md' | 'lg' | 'xl' | 'xxl'; пустая строка — размерный класс
				// не навешивается: геометрию задаёт сам вызывающий (плитка, клавиша)
			},
			icon: {
				type: String,
				default: '',
			},
			iconPosition: {
				type: String,
				default: 'left',
				// 'left' | 'right'
			},
			iconOnly: {
				type: Boolean,
				default: false,
			},
			disabled: {
				type: Boolean,
				default: false,
			},
			hidden: {
				type: Boolean,
				default: false,
			},
			raw: {
				type: Boolean,
				default: false,
			},
			fontSize: {
				type: String,
				default: '',
				// 'xxs' … '6xl' — переопределяет font-size от size
			},
			iconSize: {
				type: String,
				default: '',
				// 'xs' … '6xl' — переопределяет размер значка, привязанный к размеру кнопки
			},
		},

		computed: {
			sizeClass() {
				return this.size ? `button-${this.size}` : '';
			},

			variantClass() {
				const map = {
					primary: 'primary-button',
					outline: 'outline-button',
					ghost: 'ghost-button',
					// Второстепенное действие рядом с акцентным: та же геометрия, без заливки и рамки
					plain: 'plain-button',
					link: 'link-button',
					danger: 'danger-button',
					pill: 'pill-button',
					badge: 'badge-button',
					// 'badge-success': 'badge-button badge-button-success',
					// 'badge-error': 'badge-button badge-error',
					// 'badge-muted': 'badge-button badge-muted',
					default: '',
				};
				return map[this.type] ?? '';
			},

			fontSizeClass() {
				// Имена классов перечислены целиком, а не собираются из подстроки:
				// сборщик утилит находит их только по литеральному вхождению в исходник.
				const map = {
					xxs: '!text-xxs',
					xs: '!text-xs',
					sm: '!text-sm',
					md: '!text-md',
					base: '!text-base',
					lg: '!text-lg',
					xl: '!text-xl',
					xxl: '!text-xxl',
					'2xl': '!text-2xl',
					'3xl': '!text-3xl',
					'4xl': '!text-4xl',
					'5xl': '!text-5xl',
					'6xl': '!text-6xl',
				};
				return map[this.fontSize] ?? '';
			},

			iconSizeClass() {
				if (this.iconSize) {
					const icon_map = {
						xs: 'icon-xs',
						sm: 'icon-sm',
						md: 'icon-md',
						base: 'icon-base',
						lg: 'icon-lg',
						xl: 'icon-xl',
						xxl: 'icon-xxl',
						'2xl': 'icon-2xl',
						'3xl': 'icon-3xl',
						'4xl': 'icon-4xl',
						'5xl': 'icon-5xl',
						'6xl': 'icon-6xl',
					};
					return icon_map[this.iconSize] ?? 'icon-base';
				}

				const map = {
					xs: 'icon-md',
					sm: 'icon-md',
					md: 'icon-base',
					lg: 'icon-lg',
					xl: 'icon-lg',
				};
				return map[this.size] ?? 'icon-base';
			},
		},
	};
</script>

<style lang="scss" scoped>
	.btn--icon-only {
		padding-left: 0;
		padding-right: 0;
		aspect-ratio: 1;
		justify-content: center;
	}
</style>
