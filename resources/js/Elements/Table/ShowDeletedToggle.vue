<template>

	<span class="show-deleted-toggle inline-flex items-center cursor-pointer"
		:class="{ 'is-on': modelValue }"
		:title="$t(modelValue ? 'Hide deleted' : 'Show deleted')"
		@click.stop="toggle"
		>
		<!-- Размер как у иконок действий в строках — переключатель встаёт с ними в одну колонку. -->
		<Icon class="icon icon-md" :icon="modelValue ? 'mdi:delete-outline' : 'mdi:delete-off-outline'" />
	</span>

</template>

<script>
	import { Icon } from '@iconify/vue'

	// Переключатель фильтра мягко удалённых записей: зачёркнутая приглушённая урна —
	// удалённые скрыты, красная урна — показаны.
	export default {
		components: { Icon },
		props: {
			modelValue: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['update:modelValue'],
		methods: {
			toggle() {
				this.$emit('update:modelValue', !this.modelValue)
			},
		},
	}
</script>

<style lang="scss" scoped>
	.show-deleted-toggle {
		// Выключенный фильтр — обычное состояние таблицы, поэтому без акцента.
		color: var(--text-color-secondary);
		opacity: .55;
		transition: opacity .15s ease, color .15s ease;

		&:hover {
			opacity: .85;
		}

		&.is-on {
			color: var(--error-color);
			opacity: 1;
		}
	}
</style>
