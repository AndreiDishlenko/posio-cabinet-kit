<template>
	<button
		v-if="!hidden"
		type="button"
		:class="[
			'button',
			sizeClass,
			variantClass,
			fontSize ? `!text-${fontSize}` : '',
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
				// 'default' | 'primary' | 'outline' | 'ghost' | 'danger' | 'pill' | 'badge' | 'badge-success' | 'badge-error' | 'badge-muted'
			},
			size: {
				type: String,
				default: 'md',
				// 'xs' | 'sm' | 'md' | 'lg' | 'xl'
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
				// 'xs' | 'sm' | 'md' | 'lg' | 'xl' — переопределяет font-size от size
			},
		},

		computed: {
			sizeClass() {
				return `button-${this.size}`;
			},

			variantClass() {
				const map = {
					primary: 'primary-button',
					outline: 'outline-button',
					ghost: 'ghost-button',
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

			iconSizeClass() {
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
