<template>

	<div class="t-panel-item button cursor-pointer"
		:class="[
			`button-${size}`,
			customClass,
			{
				'disabled'       : disabled,
				'primary-button' : !!modelValue,
			}
		]"
		@click="toggle"
		>

		<Icon v-if="icon"
			class="icon"
			:class="`icon-${size}`"
			:icon="icon"
			/>

		<slot v-if="$slots.default" />
		<span v-else-if="label" :class="{ 'lt-md:hidden': hideLabelMobile }">{{ $t(label) }}</span>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue'

	export default {
		components: { Icon },
		props: {
			modelValue: {
				type: Boolean,
				default: false,
			},
			label: {
				type: String,
				default: '',
			},
			icon: {
				type: String,
				default: '',
			},
			size: {
				type: String,
				default: 'md',
				validator: (v) => ['xs', 'sm', 'md', 'lg', 'xl', '2xl'].includes(v),
			},
			disabled: {
				type: Boolean,
				default: false,
			},
			customClass: {
				type: [String, Array, Object],
				default: '',
			},
			hideLabelMobile: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['update:modelValue', 'change'],
		methods: {
			toggle(e) {
				if ( this.disabled )
					return

				const new_value = !this.modelValue
				this.$emit('update:modelValue', new_value)
				this.$emit('change', new_value, e)
			}
		}
	}
</script>

<style lang="scss" scoped>
	.t-panel-item {
		display: flex;
		align-items: center;

		span {
			// Не text-wrap: его Apple понимает только с 17.4, ниже подпись переносится.
			white-space: nowrap;
		}
	}
</style>
