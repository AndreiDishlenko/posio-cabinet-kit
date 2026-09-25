<template>

	<div class="empty-state-cta">

		<Icon :icon="icon" class="icon icon-xl empty-state-icon" />

		<div class="font-semibold">{{ $t(title) }}</div>

		<p v-if="text" class="text-sm empty-state-text">{{ $t(text) }}</p>

		<div v-if="visible_actions.length" class="flex flex-wrap wrap-gap-2 justify-center mt-1">
			<button
				v-for="action in visible_actions"
				:key="action.label"
				type="button"
				class="button button-sm"
				:class="action.primary ? 'primary-button' : 'outline-button'"
				@click="action.action()"
				>{{ $t(action.label) }}</button>
		</div>

		<slot />

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	// Экран без данных объясняет причину и предлагает следующий шаг. В списках ту же
	// роль играет состояние самой таблицы — этот вариант для экранов, у которых
	// списка нет: сводки, графики, отчёты за период.
	export default {
		name: 'EmptyStateCta',
		components: { Icon },

		props: {
			icon: {
				type: String,
				default: 'lucide:calendar-search',
			},
			title: {
				type: String,
				required: true,
			},
			text: {
				type: String,
				default: '',
			},
			// [{ label, action, primary, visible }] — ключ label идёт через перевод,
			// visible скрывает шаг, который сейчас не имеет смысла.
			actions: {
				type: Array,
				default: () => [],
			},
		},

		computed: {
			visible_actions() {
				return this.actions.filter(action => action && action.label && action.visible !== false);
			},
		},
	}
</script>

<style lang="scss" scoped>

	.empty-state-cta {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		text-align: center;
		padding: 2.5rem 1rem;
		color: var(--text-color-disabled);
		@include flex-gap(0.35rem, column);
	}

	.empty-state-icon {
		color: var(--text-color-disabled);
		opacity: 0.6;
	}

	.empty-state-text {
		max-width: 32rem;
	}

</style>
