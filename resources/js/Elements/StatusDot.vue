<template>

	<span class="status-dot inline-flex items-center" :title="tooltip">
		<span class="status-indicator shrink-0" :class="state_class" :pulse="pulse ? '' : null"></span>
		<span v-if="label" class="status-dot-label ms-1.5 whitespace-nowrap">{{ $t(label) }}</span>
	</span>

</template>

<script>
	// Кольорова точка стану з необов'язковим підписом — компактна заміна чекбокса
	// там, де прапорець лише показують, а не редагують (товар у продажу, каса
	// активна тощо). Кольори — зі спільної палітри індикаторів статусу.
	export default {
		name: 'StatusDot',
		props: {
			// Увімкнений стан: зелена точка, вимкнений — приглушена.
			active: {
				type: Boolean,
				default: false,
			},
			// Явний варіант кольору з палітри індикаторів ('success', 'warning',
			// 'error', 'off'); задається, коли станів більше двох.
			state: {
				type: String,
				default: '',
			},
			// Англійський ключ підпису поруч із точкою; порожній — сама лише точка.
			label: {
				type: String,
				default: '',
			},
			pulse: {
				type: Boolean,
				default: false,
			},
		},
		computed: {
			state_class() {
				return this.state || (this.active ? 'success' : 'off');
			},
			// Підпис може бути обрізаний вузькою колонкою — дублюємо його підказкою.
			tooltip() {
				return this.label ? this.$t(this.label) : null;
			},
		},
	}
</script>

<style lang="scss" scoped>
	.status-dot-label {
		font-size: 0.9em;
		line-height: 1;
	}
</style>
