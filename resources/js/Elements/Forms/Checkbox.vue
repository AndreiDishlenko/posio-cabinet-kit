<template>
	<!-- {{ textSizeClass }} -->
	<label class="form-check inline-flex items-center cursor-pointer select-none "
		:class="[
			textSizeClass,
			{ 'opacity-60 cursor-not-allowed': disabled || disabled_by_fieldset },
		]"
		>
		<input
			ref="input"
			type="checkbox"
			class="form-control"
			:class="boxSizeClass"
			:checked="is_checked"
			:disabled="disabled || disabled_by_fieldset"
			@change="onChange"
			/>
		<template v-if="labelMobile">
			<span class="font-normal md:hidden">{{ $t(labelMobile) }}</span>
			<span v-if="resolvedLabel" class="font-normal lt-md:hidden">{{ $t(resolvedLabel) }}</span>
		</template>
		<span v-else-if="resolvedLabel" class="font-normal">{{ $t(resolvedLabel) }}</span>
	</label>
</template>

<script>
	const BOX_SIZE = {
		xs: '!size-3',
		sm: '!size-3.5',
		md: '!size-4',
		lg: '!size-5',
		xl: '!size-6',
	}

	const TEXT_SIZE = {
		xs: 'text-xs',
		sm: 'text-sm',
		md: 'text-md',
		base: 'text-base',
		lg: 'text-lg',
		xl: 'text-xl',
	}

	export default {
		props: {
			modelValue: {
				required: true,
				default: 0,
			},
			label: {
				type: String,
				required: false,
				default: '',
			},
			text: {
				type: String,
				required: false,
				default: '',
			},
			labelMobile: {
				type: String,
				required: false,
				default: '',
			},
			disabled: {
				type: Boolean,
				required: false,
				default: false,
			},
			size: {
				type: String,
				required: false,
				default: 'base',
				validator: (v) => ['xs', 'sm', 'base', 'md', 'lg', 'xl'].includes(v),
			},
		},
		emits: ['update:modelValue', 'onUpdate', 'onChange', 'change', 'input'],
		data() {
			return {
				disabled_by_fieldset: false,
			}
		},
		computed: {
			resolvedLabel() {
				return this.label || this.text
			},
			is_checked() {
				return !!Number(this.modelValue)
			},
			boxSizeClass() {
				return BOX_SIZE[this.size] || BOX_SIZE.base
			},
			textSizeClass() {
				return TEXT_SIZE[this.size] || TEXT_SIZE.base
			},
		},
		mounted() {
			this.checkFieldsetDisabled()
			window.addEventListener('change', this.checkFieldsetDisabled)
		},
		unmounted() {
			window.removeEventListener('change', this.checkFieldsetDisabled)
		},
		methods: {
			onChange(e) {
				const checked = e.target.checked
				const value = typeof this.modelValue === 'boolean' ? checked : Number(checked)
				this.$emit('update:modelValue', value)
				this.$emit('onUpdate', value)
				this.$emit('onChange', value)
				this.$emit('change', e)
				this.$emit('input')
			},
			checkFieldsetDisabled() {
				if (this.$refs.input) {
					const fieldset = this.$refs.input.closest('fieldset')
					this.disabled_by_fieldset = fieldset ? fieldset.disabled : false
				}
			},
		},
	}
</script>
