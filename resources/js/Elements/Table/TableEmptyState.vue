<template>

	<div class="table-empty-state" :style="{ gridColumn: `span ${columns_count}` }">

		<Icon :icon="state_icon" class="icon icon-xl table-empty-icon" />

		<div class="font-semibold">{{ $t(state_title) }}</div>

		<p v-if="state_text" class="text-sm table-empty-text">{{ $t(state_text) }}</p>

		<div v-if="actions.length" class="flex flex-wrap wrap-gap-2 justify-center mt-1">
			<button
				v-for="action in actions"
				:key="action.label"
				type="button"
				class="button button-sm"
				:class="action.primary ? 'primary-button' : 'outline-button'"
				@click="run(action)"
				>{{ $t(action.label) }}</button>
		</div>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	// Пустой список объясняет, почему он пуст, и предлагает действие, которое снимает
	// саму причину пустоты: снять отбор, повторить неудавшийся запрос или завести
	// первую запись — последнее описывает сам список в настройках состояния.
	const STATES = {
		empty: {
			icon:  'lucide:inbox',
			title: 'No records yet',
			text:  '',
		},
		filtered: {
			icon:  'lucide:filter-x',
			title: 'Nothing matches the current filters',
			text:  'empty-filtered-text',
		},
		error: {
			icon:  'lucide:triangle-alert',
			title: 'Failed to load the data',
			text:  '',
		},
	};

	export default {
		name: 'TableEmptyState',
		components: { Icon },
		emits: ['reset', 'retry', 'action'],

		props: {
			// empty | filtered | error
			kind: {
				type: String,
				required: true,
			},
			// Тексты настраиваются страницей —
			// settings.empty_state: { icon, title, text, filtered_title, filtered_text }
			settings: {
				type: Object,
				default: () => ({}),
			},
			// Текст ошибки от страницы: показывается вместо стандартного объяснения
			error: {
				type: String,
				default: '',
			},
			columns_count: {
				type: Number,
				default: 1,
			},
		},

		computed: {
			state() {
				return STATES[this.kind] || STATES.empty;
			},

			custom() {
				return this.settings.empty_state || {};
			},

			state_icon() {
				return this.custom.icon && this.kind === 'empty' ? this.custom.icon : this.state.icon;
			},

			state_title() {
				if ( this.kind === 'filtered' )
					return this.custom.filtered_title || this.state.title;

				if ( this.kind === 'empty' )
					return this.custom.title || this.state.title;

				return this.state.title;
			},

			state_text() {
				if ( this.kind === 'error' )
					return this.error || '';

				if ( this.kind === 'filtered' )
					return this.custom.filtered_text ?? this.state.text;

				return this.custom.text ?? this.state.text;
			},

			actions() {
				if ( this.kind === 'error' )
					return [{ label: 'Retry', event: 'retry', primary: true }];

				if ( this.kind === 'filtered' )
					return [{ label: 'Reset filters', event: 'reset', primary: true }];

				// Первый шаг в пустом списке предлагает сам список: где-то это заведение
				// записи, где-то импорт готового каталога.
				return (this.custom.actions || []).filter(action => action && action.label);
			},
		},

		methods: {
			run(action) {
				if ( action.event === 'retry' || action.event === 'reset' )
					return this.$emit(action.event);

				this.$emit('action', action.event);
			},
		},
	}
</script>

<style lang="scss" scoped>

	// Сообщение стоит по центру свободного тела списка, а не прижато к шапке:
	// высоту под него растягивает обёртка списка.
	.table-empty-state {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		text-align: center;
		padding: 2.5rem 1rem;
		color: var(--text-color-disabled);
		@include flex-gap(0.35rem, column);
	}

	.table-empty-icon {
		color: var(--text-color-disabled);
		opacity: 0.6;
	}

	.table-empty-text {
		max-width: 32rem;
	}

</style>
