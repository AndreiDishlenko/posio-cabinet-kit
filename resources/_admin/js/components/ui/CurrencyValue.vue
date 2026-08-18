<template>

	<span class="currency-value inline-flex items-baseline space-x-1 whitespace-nowrap font-bold leading-[1]">
		<span v-if="currency" class="currency-sign text-yellow" :class="currencySizeClass">{{ currency }}</span>
		<span :class="valueSizeClass">{{ value }}</span>
	</span>

</template>

<script>
	export default {
		props: {
			value: {
				type: [String, Number],
				required: true,
			},
			currency: {
				type: String,
				default: '',
			},
			size: {
				type: String,
				default: 'lg', // xs | sm | md | lg | xl
			},
		},
		computed: {
			valueSizeClass() {
				const map = {
					xs: 'text-sm',
					sm: 'text-base',
					md: 'text-xl',
					lg: 'text-2xl',
					xl: 'text-3xl',
				};

				return map[this.size] || map.lg;
			},
			currencySizeClass() {
				const map = {
					// xs: 'text-xs',
					// sm: 'text-sm',
					// md: 'text-base',
					// lg: 'text-xl',
					// xl: 'text-2xl',
					xs: 'text-sm',
					sm: 'text-base',
					md: 'text-xl',
					lg: 'text-2xl',
					xl: 'text-3xl',
				};

				return map[this.size] || map.lg;
			},
		},
	}
</script>
