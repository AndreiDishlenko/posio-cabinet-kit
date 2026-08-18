<template>
	<div class="table-cell row-cell rowbar-cell !justify-center">
		<div class="rowbar">

			<template v-for="bar in rowbar">
				<template v-if="barVisible(bar) && ((!deleted_filter && bar.event == 'onDelete') || bar.event != 'onDelete')">
					<!-- href-кнопка: справжнє посилання, тож працює ПКМ → «відкрити в новій вкладці»,
					     Ctrl/Cmd/середній клік → нова вкладка; звичайний лівий клік — дія в SPA.
					     Якщо задано bar.target ('_blank') — звичайний клік відкриває посилання
					     в новій вкладці/вікні (без SPA-дії). -->
					<a v-if="bar.href"
						:href="bar.href(row)"
						:target="bar.target"
						:title="bar.tooltip"
						@click="onBarLinkClick($event, bar)">
						<Icon class="icon icon-md text-secondary cursor-pointer" :icon="bar.icon" />
					</a>
					<span v-else :title="bar.tooltip">
						<Icon class="icon icon-md text-secondary cursor-pointer"
							:icon="bar.icon"
							@click.stop.prevent="$emit('action', bar.event, row)"
							/>
					</span>
				</template>
			</template>

			<template v-if="deleted_filter && !hide_delete_resolved">
				<span :title="!row['is_deleted'] ? $t('Delete') : $t('Restore')">
					<Icon v-if="!row['is_deleted']"
						icon="material-symbols:delete-outline-rounded"
						class="icon icon-md text-secondary cursor-pointer"
						@click.stop.prevent="$emit('action', 'onDelete', row)"
						/>
					<Icon v-if="row['is_deleted']"
						icon="material-symbols:restore-from-trash-outline-rounded"
						class="icon icon-md text-secondary cursor-pointer"
						@click.stop.prevent="$emit('action', 'onRestore', row)"
						/>
				</span>
			</template>

		</div>
	</div>
</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'TableRowBar',
		components: { Icon },
		props: {
			row: {
				type: Object,
				required: true,
			},
			rowbar: {
				type: Array,
				default: () => [],
			},
			deleted_filter: {
				type: Boolean,
				default: false,
			},
			// Suppress the auto Delete/Restore icons in the rowbar (deletion stays
			// available via the context menu). The "Show deleted" toggle/header is unaffected.
			// Boolean or a (row) => boolean predicate for per-row suppression (e.g. rows the
			// user may not delete at all — system-generated documents).
			hide_delete_icons: {
				type: [Boolean, Function],
				default: false,
			},
		},
		emits: ['action'],
		computed: {
			hide_delete_resolved() {
				// TableRowBar.hide_delete_resolved
				if ( typeof this.hide_delete_icons === 'function' )
					return !!this.hide_delete_icons(this.row);

				return !!this.hide_delete_icons;
			},
		},
		methods: {
			// Кнопка rowbar показується завжди, або лише коли поле row[bar.row_flag] істинне
			// (напр. дозволити розшифровку тільки для рядків-статей, не для балансових).
			// row_flag_off — дзеркальна умова: ховати кнопку, коли поле істинне (напр. дії,
			// недоступні для згенерованих системою рядків).
			barVisible(bar) {
				if ( bar.row_flag_off && !!this.row[bar.row_flag_off] )
					return false;

				return !bar.row_flag || !!this.row[bar.row_flag];
			},
			// Лівий клік по href-кнопці: з модифікатором (Ctrl/Cmd/Shift) лишаємо браузеру
			// (нова вкладка/вікно), інакше — дія в SPA замість переходу.
			// Якщо bar.target задано (напр. '_blank') — завжди лишаємо браузеру:
			// посилання відкривається в новій вкладці/вікні, SPA-дія не викликається.
			onBarLinkClick(event, bar) {
				event.stopPropagation();

				if (bar.target || event.metaKey || event.ctrlKey || event.shiftKey)
					return;

				event.preventDefault();
				this.$emit('action', bar.event, this.row);
			},
		},
	}
</script>

<style lang="scss" scoped>
	.rowbar {
		height: 100%;
		display: flex;
		// Отступ задан миксином: раскладка приходит из этого класса, утилитарного
		// класса раскладки на элементе нет и фолбэк сборщика до него не достаёт.
		@include flex-gap(0.75rem);
		flex: 0 1 auto;
		align-items: center;
	}
</style>
