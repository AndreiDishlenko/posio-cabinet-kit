<template>
	<!-- Колонка дій завжди притиснута до правого краю таблиці: її ширину задає
	     найширший набір іконок (у шапці групи їх більше, ніж у рядку), і при
	     центруванні іконки рядків розійшлися б із рештою колонки по вертикалі. -->
	<div class="table-cell row-cell rowbar-cell !justify-end">
		<div v-if="show_icons" class="rowbar">

			<!-- Меню дій: одна кнопка замість набору іконок, решта дій рядка — його пункти. -->
			<Dropdown v-if="menu_action"
				ref="menu"
				class="rowbar-menu"
				align="right"
				transition="menu"
				:downOnClick="true"
				buttonclass="rowbar-menu-button"
				@click.stop
				>
				<template #button>
					<span :title="menu_action.tooltip ? $t(menu_action.tooltip) : null">
						<Icon class="icon icon-md cursor-pointer" :class="barIconClass(menu_action)" :icon="menu_action.icon" />
					</span>
				</template>

				<template #dropdownitems="{ direction }">
					<SelectableItems
						:in_data="menu_items"
						:direction="direction"
						@selectItem="(e, item) => onMenuSelect(item)"
						/>
				</template>
			</Dropdown>

			<template v-if="!menu_action">
				<template v-for="bar in visible_bars">
					<!-- href-кнопка: справжнє посилання, тож працює ПКМ → «відкрити в новій вкладці»,
					     Ctrl/Cmd/середній клік → нова вкладка; звичайний лівий клік — дія в SPA.
					     Якщо задано bar.target ('_blank') — звичайний клік відкриває посилання
					     в новій вкладці/вікні (без SPA-дії). -->
					<a v-if="bar.href"
						:href="bar.href(row)"
						:target="bar.target"
						:title="bar.tooltip ? $t(bar.tooltip) : null"
						@click="onBarLinkClick($event, bar)">
						<Icon class="icon icon-md cursor-pointer" :class="barIconClass(bar)" :icon="bar.icon" />
					</a>
					<span v-else :title="bar.tooltip ? $t(bar.tooltip) : null">
						<Icon class="icon icon-md cursor-pointer"
							:class="barIconClass(bar)"
							:icon="bar.icon"
							@click.stop.prevent="$emit('action', bar.event, row)"
							/>
					</span>
				</template>
			</template>

		</div>
	</div>
</template>

<script>
	import { Icon } from '@iconify/vue';
	import Dropdown from '@/js/Elements/Dropdown.vue';
	import SelectableItems from '@/js/Elements/Forms/SelectableItems.vue';
	import { STANDARD_ROW_ACTIONS, resolveRowAction, rowActionColorClass } from '@/js/Elements/Table/rowActions.js';

	export default {
		name: 'TableRowBar',
		components: { Icon, Dropdown, SelectableItems },
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
			// Whether the row's action icons render at all. False keeps the column cell
			// (grid alignment with the header) but empty — used when the host table has
			// a deleted-filter and rowbar_mobile_only, so the column stays reserved for
			// the header's "Show deleted" toggle while icons collapse to the context menu.
			show_icons: {
				type: Boolean,
				default: true,
			},
		},
		emits: ['action'],
		computed: {
			// Кнопки рядка: опис сторінки, доповнений стандартом своєї події (іконка,
			// підказка). Таблиця з фільтром видалених завжди дає видалити рядок, навіть
			// коли сторінка кнопку не описала.
			bars() {
				// TableRowBar.bars
				const declared = this.rowbar.map(bar => resolveRowAction(bar));

				if ( this.deleted_filter && !declared.some(bar => bar.event === 'onDelete' || bar.event === 'onRestore') )
					declared.push(resolveRowAction({ event: 'onDelete' }));

				const has_restore = declared.some(bar => bar.event === 'onRestore');
				const deleted_row = this.deleted_filter && !!this.row['is_deleted'];
				const items = [];

				// Видалення й відновлення — взаємовиключна пара: на видаленому рядку
				// лишається тільки відновлення. Коли сторінка описала саме видалення,
				// парна дія підставляється сама (зі стандартною іконкою й підказкою),
				// зберігаючи лише умови видимості кнопки.
				declared.forEach(bar => {
					if ( bar.event === 'onDelete' && deleted_row ) {
						if ( !has_restore )
							items.push({
								row_flag:     bar.row_flag,
								row_flag_off: bar.row_flag_off,
								...STANDARD_ROW_ACTIONS.onRestore,
								event: 'onRestore',
							});

						return;
					}

					if ( bar.event === 'onRestore' && this.deleted_filter && !deleted_row )
						return;

					items.push(bar);
				});

				// Видалення (і парне відновлення) завжди крайнє праворуч, у якому б місці
				// набору сторінка його не описала: край рядка — стале місце небезпечної дії.
				return items.sort((a, b) => this.isRemoval(a) - this.isRemoval(b));
			},
			visible_bars() {
				return this.bars.filter(bar => this.barVisible(bar));
			},
			// Меню дій згортає весь набір в одну кнопку: решта дій рядка стає його
			// пунктами, окремі іконки не показуються. Без жодної іншої дії згортати
			// нічого — кнопка меню не показується.
			menu_action() {
				const menu = this.visible_bars.find(bar => bar.event === 'onActions');

				if ( !menu || this.visible_bars.length < 2 )
					return null;

				return menu;
			},
			menu_items() {
				if ( !this.menu_action )
					return [];

				return this.visible_bars
					.filter(bar => bar.event !== 'onActions')
					.map(bar => ({
						name:  bar.tooltip,
						icon:  bar.icon,
						event: bar.event,
					}));
			},
			hide_delete_resolved() {
				// TableRowBar.hide_delete_resolved
				if ( typeof this.hide_delete_icons === 'function' )
					return !!this.hide_delete_icons(this.row);

				return !!this.hide_delete_icons;
			},
		},
		methods: {
			// Видалення та парне відновлення — дії, для яких зарезервовано край рядка.
			isRemoval(bar) {
				return bar.event === 'onDelete' || bar.event === 'onRestore' ? 1 : 0;
			},
			// Клас іконки дії: колір із дескриптора плюс мітка відновлення — таблиця
			// перефарбовує видалений рядок у червоне і за цією міткою лишає єдину
			// дію, що повертає запис, у власному кольорі.
			barIconClass(bar) {
				// TableRowBar.barIconClass
				return [
					rowActionColorClass(bar, this.row),
					bar.event === 'onRestore' ? 'row-action-restore' : '',
				];
			},
			onMenuSelect(item) {
				// TableRowBar.onMenuSelect
				this.$refs.menu?.close();
				this.$emit('action', item.event, this.row);
			},
			// Кнопка rowbar показується завжди, або лише коли поле row[bar.row_flag] істинне
			// (напр. дозволити розшифровку тільки для рядків-статей, не для балансових).
			// row_flag_off — дзеркальна умова: ховати кнопку, коли поле істинне (напр. дії,
			// недоступні для згенерованих системою рядків).
			barVisible(bar) {
				if ( (bar.event === 'onDelete' || bar.event === 'onRestore') && this.hide_delete_resolved )
					return false;

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

	// Кнопка меню дій має стояти в ряду іконок так само, як звичайна іконка.
	.rowbar-menu,
	::v-deep(.rowbar-menu-button) {
		display: flex;
		align-items: center;
	}
</style>
