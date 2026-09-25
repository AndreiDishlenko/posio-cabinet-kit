<template>

	<CurrencyValue v-bind="$attrs" :tone="tone" />

</template>

<script>
	import CurrencyValue from '@/js/Elements/CurrencyValue.vue';

	// Прежний адрес суммы с валютой: хосты импортируют её отсюда. Сохраняет прежний
	// вид — знак валюты акцентным цветом, — новый компонент по умолчанию монохромный.
	export default {
		name: 'CurrencyValueLegacy',
		components: { CurrencyValue },
		inheritAttrs: false,
		props: {
			tone: {
				type: String,
				default: 'accent',
			},
		},
	}
</script>
