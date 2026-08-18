<template>

	<!-- Table -->
    <div id="table" ref="table"
		class="table grow h-full"
		:data-drag-group="drag_group || null"
		:data-drag-accept="accept_drag.length ? accept_drag.join(',') : null"
		:style="{ '--tools-panel-height': toolsPanelHeight + 'px' }"
		:class="{
			'disabled': disabled,
			'select-none': noSelect,
			'min-w-0': fit_container || x_scroll,
			'y-scroll': y_scroll || x_scroll,
			'has-slaves': has_slaves,
			'is-grouped': is_grouped,
			'no-slave-marker': has_slaves && !slave_marker
		}"
		>

        <h2 v-if="header" class="table-title">
            {{ $t(header) }}
        </h2>

		<!-- Selection mode toolbar — floated as a fixed overlay near the bottom,
		     centered over the host container (selection_overlay_target). -->
		<Teleport v-if="selection.active" :to="selection_overlay_target">
			<div class="selection-bar">
				<span class="selection-bar-label">
					{{ $t(selection.label || 'Select a document') }}
				</span>
				<span class="grow"></span>
				<button class="button outline-button button-sm" @click="cancelSelection()">
					{{ $t('Cancel') }}
				</button>
				<button class="button primary-button button-sm"
					:class="{ disabled: !hasSelection }"
					@click="confirmSelection()"
					>
					{{ $t('Confirm') }}
				</button>
			</div>
		</Teleport>


		<TableToolsPanel ref="toolsPanel" class="top-0 z-[200]"
			v-show="!toolbar_hidden"
			:class="{
				'min-w-0' : fit_container,
				// 'sticky' : sticky_toolpanel
			}"
			:style="{ gridColumn: `span ${columnsCount}` }"
			v-if="
				Object.keys(settings.ctabutton || {}).length ||
				Object.keys(settings.groupactions || {}).length ||
				( Object.keys(settings.filters || {}).length && !show_rowbar ) ||
				Object.keys(settings.dropdownmenu || {}).length ||
				Object.keys(settings.panelitems || {}).length ||
				Object.keys(settings.custom_tools || {}).length ||
				$slots.tools
			"
			:settings="settings"
			:filters="filters"
			:panel_data="panel_data"
			:show_rowbar="show_rowbar"

			@addRow="addRow()"
			>
			<template v-if="$slots.tools" #tools>
				<slot name="tools" />
			</template>
		</TableToolsPanel>

		<TableWrapper ref="tableWrapper" class="table"
			:class="{ h_dividers, v_dividers }"
			:settings="settings"
			:seenColumns="seenColumns"
			:selector="show_selector"
			:rounded="true"
			:scrolled="false"
			:sticky_header="sticky_header"
			:fit_container="fit_container"
			:x_scroll="x_scroll"
			:y_scroll="y_scroll"
			:slave_key="slave_key"
			:show_rowbar="show_rowbar"
			@scrolledChange="onWrapperScrolled"
			>

			<!-- {{ seenColumns.length }}
			{{ columnsCount }} -->
			<!-- {{ openedGroups }} -->
			<!-- {{ table_data }} -->
			<!-- {{ grouped_data }} -->

				<!-- :dropdownmenu="settings.dropdownmenu" -->

			<TableHeader
				:settings	 = "settings"
				:seenColumns = "seenColumns"
				:selector	 = "show_selector"
				:slave_key   = "slave_key"
				:items_class = "''"
				:sortRules   = "activeSortRules"
				:sortEnabled = "allowSorting"
				:show_rowbar = "show_rowbar"
				:panel_data  = "panel_data"
				@sort-click  = "applySort"
				/>

			<!-- Рядок-ілюстрація (CSS-бари + лінія) під шапкою, вирівняний по колонках.
			     Вмикається data-driven налаштуванням settings.chart_row. -->
			<TableChartRow
				v-if="settings.chart_row && table_data.length"
				:columns		= "seenColumns"
				:data			= "table_data"
				:config			= "settings.chart_row"
				:show_selector	= "show_selector"
				:has_slaves		= "has_slaves"
				:show_rowbar	= "show_rowbar"
				/>

			<!-- Table Body Groups -->
			<AccordionItem2 v-for="group_entry in group_entries"
				:key	= "group_entry.key"
				:ref	= "'group' + group_entry.key"
				class	= "contents group-wrapper"
				:button_arrow		= "group_entry.key ? true : false"
				:accordion_class	= "'table-group'"
				:cells_mode			= "group_entry.key && hasGroupTotals ? true : false"
				:button_class		= "groupButtonClass(group_entry) + (group_entry.item?.is_deleted ? ' is-deleted' : '')"
				:button_styles		= "{ gridColumn: `span ${columnsCount}` }"
				:button_attrs		= "groupButtonAttrs(group_entry)"
				:arrow_position		= "settings.group_arrow || 'end'"
				:body_class			= "'table-group-body'"
				:is_opened 			= "group_entry.key ? isGroupOpened(group_entry.key) : true"
				@before_open		= "openGroup(group_entry.key)"
				@closed				= "closeGroup(group_entry.key)"
				>

				<template #acc_button>
					<template v-if="group_entry.key && hasGroupTotals">
						<!-- group title spans columns from start until first totals column -->
						<div class="table-group-header table-cell group-name-cell"
							:style="{ gridColumn: `1 / ${firstTotalsGridColumn}` }"
							>
							{{ group_entry.title }}
						</div>
						<!-- Per-column total cells -->
						<template v-for="(column, idx) in seenColumns" :key="column.field">
							<div v-if="settings.group_totals.includes(column.field)"
								class="table-group-header table-cell font-bold"
								:class="column.align ? 'justify-' + column.align : ''"
								:style="{ gridColumnStart: idx + 1 + selectorOffset }"
								>
								{{ formatGroupTotalValue(column, getGroupTotal(group_entry.key, column.field)) }}
							</div>
						</template>
					</template>
					<template v-else>
						<div class="flex items-center">
							<!-- Іконка групи (напр. категорії) — з поля джерела групування,
							     заданого settings.group_icon_field -->
							<Icon v-if="settings.group_icon_field && group_entry.key"
								:icon="group_entry.item?.[settings.group_icon_field] || settings.group_icon_default || 'mdi:shape-outline'"
								class="icon icon-sm me-1.5 shrink-0"
								/>
							<span class="min-w-0 truncate">{{ group_entry.title }}</span>
							<span class="grow"></span>
							<!-- Дії над самою групою (напр. керування категорією товарів):
							     клік по них не має розкривати/згортати групу. -->
							<span v-if="groupActionsOf(group_entry).length" class="group-actions" @click.stop>
								<Icon v-for="action in groupActionsOf(group_entry)"
									:key="action.event"
									class="icon icon-md text-secondary cursor-pointer"
									:icon="action.icon"
									:title="action.tooltip ? $t(action.tooltip) : ''"
									@click.stop.prevent="groupAction(action, group_entry)"
									/>
							</span>
						</div>
					</template>
				</template>

				<template #acc_block>

					<!-- Table rows — уже сплющене дерево: верхній рівень і розкриті
					     підпорядковані рядки будь-якої глибини йдуть одним списком -->
					<template v-for="(entry, rowindex) in visibleRowsOf(group_entry.key)" :key="entry.row[row_key] ?? ('row' + rowindex)">

						<div
							class="table-row contents"
							:data-row-key="entry.row[row_key]"
							@click="rowClick(entry.row)"
							@contextmenu="onRowContext(entry.row, $event)"
							@mousedown="canDragRow(entry) ? onRowMouseDown(entry.row, dragGroupOf(group_entry.key), $event) : null"
							:class="[
								{
									'selected': entry.row.selected,
									'slave-row': entry.depth > 0,
									'draggable-row': canDragRow(entry),
									'dragging-row': drag.active && drag.candidateRow === entry.row,
									'drop-into-row': drag.active && drag.overPos === 'into' && drag.overRow === entry.row,
									'is-parent-expanded': entry.expanded
								},
								rowClass(entry.row)
							]"
							>

							<div v-if="show_selector" class="table-selector table-cell row-cell" @click.stop="onSelectionToggle(entry.row)">
								<input type="checkbox" class="form-control"
									:checked="!!entry.row.is_selected"
									@click.stop="onSelectionToggle(entry.row)">
							</div>

							<!-- Expand/collapse toggle; підлеглий рядок отримує псевдо tree-маркер
							     зв'язку з батьком, а якщо сам має дітей — ще й шеврон поруч.
							     На нульовому рівні маркер порожній — це лише розпірка, щоб
							     шеврон root-рядка починався рівно там, де очікує маркер
							     першого рівня вкладеності (той самий крок для всіх рівнів). -->
							<div v-if="has_slaves"
								class="table-cell slave-toggle-cell"
								:style="{ '--slave-depth': entry.depth }"
								@click.stop="entry.has_children && toggleParent(entry.row)"
								>
								<span v-if="slave_marker" class="slave-tree-marker">{{ entry.depth ? (entry.is_last ? '└' : '├') : '' }}</span>
								<button v-if="entry.has_children"
									class="slave-toggle-btn"
									:class="{ 'is-expanded': entry.expanded }"
									>
									<Icon icon="lucide:chevron-down" class="icon icon-md text-secondary slave-toggle-chevron" />
								</button>
							</div>

							<TableCell
								v-for="(column, index) in seenColumns"
								:key="column.field"
								:column="column"
								:row="entry.row"
								:select_sources="select_sources"
								:is_bottom_left="rowindex == table_data.length - 1 && index == 1"
								:inline_edit="isInlineAddCell(entry.row, column)"
								@inline-confirm="confirmInlineAdd"
								@inline-cancel="cancelAdd"
								/>

							<TableRowBar
								v-if="show_rowbar"
								:row="entry.row"
								:rowbar="settings.rowbar || []"
								:deleted_filter="!!settings.filters?.deleted"
								:hide_delete_icons="settings.rowbar_hide_delete || false"
								@action="rowbarAction"
								/>

						</div>

					</template>

				</template>

			</AccordionItem2>

			<div v-if="fit_container" ref="tableSpacer" class="table-spacer !min-h-0"
				:style="{ gridColumn: `span ${columnsCount}` }"
				aria-hidden="true"
				></div>

			<!-- <div v-for="(column, index) in seenColumns" class="grow">aa</div> -->
			<!-- <div class="grow col-start-1 col-end-[-1] h-full border-test">aa</div> -->

			<TableTotals
				v-if="settings.totals && settings.totals?.length"
				:seenColumns="seenColumns"
				:totals="totals"
				:settings="settings"
				:selector="show_selector"
				:slave_key="slave_key"
				:show_rowbar="show_rowbar"
				/>

        </TableWrapper>

		<TableContextMenu
			ref="contextMenu"
			:actions="contextActions"
			:row="contextRow || {}"
			:deleted_filter="!!settings.filters?.deleted"
			:show_delete="showContextDelete"
			:hide_delete="settings.hide_delete || false"
			@select="onContextSelect"
			/>

		<TableCopyHandler />
		<TableDragHandler
			ref="dragHandler"
			:enabled="drag_enabled"
			:group_key="drag_group"
			:row_key="row_key"
			table_ref="table"
			:grouped_data="drag_grouped_data"
			:tree_mode="tree_drag"
			:groups_draggable="draggable_groups"
			:can_drop="canDropOn"
			@drag-update="onDragUpdate"
			@drop="onDragDrop"
			/>

		<!-- Floating action — appears in the bottom-right corner once the table
		     is scrolled down. When the table defines a CTA button
		     (settings.ctabutton) that CTA replaces the scroll-to-top FAB here;
		     otherwise a scroll-to-top FAB returns the table to the top. -->
		<Transition name="fab-fade">
			<div v-if="showFab" class="table-scroll-fab">
				<SelectableButton v-if="hasCtaButton"
					:actions="ctabuttonActions"
					type="primary"
					size="md"
					direction="up"
					@click.stop
					/>
				<FabButton v-else
					variant="primary"
					size="md"
					icon="mdi:arrow-up"
					:aria-label="$t('Scroll to top')"
					@click="$refs.tableWrapper?.scrollToTop()"
					/>
			</div>
		</Transition>
    </div>

</template>

<script>
	import { reactive } from 'vue'

    import { Link } from '@inertiajs/vue3';
	import { Icon } from '@iconify/vue';

	import { Datetime } from '@/js/posio/helpers/Datetime';

    import TableToolsPanel  from './Table/TableToolsPanel.vue';
    import TableWrapper     from './Table/TableWrapper.vue';
    import TableHeader      from './Table/TableHeader.vue';
    import TableCell        from './Table/TableCell.vue';
    import TableChartRow    from './Table/TableChartRow.vue';
    import TableRowBar      from './Table/TableRowBar.vue';
    import TableTotals      from './Table/TableTotals.vue';
    import TableCopyHandler from './Table/TableCopyHandler.vue';
    import TableDragHandler from './Table/TableDragHandler.vue';
    import TableContextMenu from './Table/TableContextMenu.vue';

    import Checkbox from './Forms/Checkbox.vue';
    import SelectableButton from './Forms/SelectableButton.vue';
	import AccordionItem2 from './AccordionItem2.vue';
	import FabButton from './FabButton.vue';

    // import ScrolledWrapper  from '@/js/Elements/ScrolledWrapper.vue';

    export default {
        components: { Link, Icon, TableToolsPanel, TableWrapper, TableHeader, TableCell, TableChartRow, TableRowBar, TableTotals, TableCopyHandler, TableDragHandler, TableContextMenu, Checkbox, SelectableButton, AccordionItem2, FabButton },
        props: { 
            header: {
                type: String,
                default: ''
            },
            in_data: {
                type: Array,
                default: []
            }, 
            settings: {
                type: Object,
                default: {}
            },
			selects: {
				type: Object,
				default: {}
			},
            scrolled: {
                type: Boolean,
                default: true
            },
            rounded: {
                type: Boolean,
                default: false
            },
            pagination: {
                type: Object,
                default: {}
            },
            filters: {
                type: Array,
                default: []
            },
            selector: {
                type: Boolean,
                default: false
            },
            wrap_content: {
                type: String,
                default: 'nowrap'
            },
            selectable: {
                type: Boolean,
                default: true
            },

            defaults: {
                type: Object,
                default: {}
            },
			groupBy: {
				type: String,
				default: ''
			},
			// Довідник груп: задає порядок груп, їх заголовки (поле name) і показ груп
			// без жодного рядка. Без нього групи складаються лише з наявних даних —
			// у порядку появи, із «сирим» значенням поля групування як заголовком.
			group_source: {
				type: Array,
				default: () => []
			},
			// Прогонять заголовки груп через $t (для словникових груп з англ. ключами;
			// для груп з користувацькими назвами — категорії/контрагенти — лишати false).
			translate_groups: {
				type: Boolean,
				default: false
			},
			expandedByDefault: {
				type: Boolean,
				default: false
			},
			single_open_accordion: {
				type: Boolean,
				default: false
			},
			h_dividers: {
				type: Boolean,
				default: true
			},
			v_dividers: {
				type: Boolean,
				default: false
			},
			disabled: {
				type: Boolean,
				default: false
			},
			allowSorting: {
				type: Boolean,
				default: true
			},
			noSelect: {
				type: Boolean,
				default: false
			},
			sticky_header: {
				type: Boolean,
				default: false
			},
			fit_container: {
				type: Boolean,
				default: true
			},
			// Коли таблицю прокручують вниз — ховати панель інструментів (toolbar).
			// За замовчуванням вимкнено; вмикається лише цим пропом.
			collapse_toolbar_on_scroll: {
				type: Boolean,
				default: false
			},
			// Включає горизонтальну прокрутку: коли ширина таблиці перевищує доступну,
			// колонки максимально ущільнюються (без обрізання даних), а таблиця
			// прокручується вліво-вправо замість того, щоб обрізати клітинки.
			x_scroll: {
				type: Boolean,
				default: false
			},
			// Власна вертикальна прокрутка тіла: таблиця лишається в межах відведеної
			// їй висоти, шапка липне до її верху, а не до прокрутки сторінки. Потрібно,
			// коли на сторінці кілька таблиць поруч і кожна має гортатися окремо.
			y_scroll: {
				type: Boolean,
				default: false
			},
			storage_key: {
				type: String,
				default: ''
			},
			draggable_rows: {
				type: Boolean,
				default: false
			},
			// Шапку групи можна тягнути (порядок груп), а рядок — кидати на шапку
			// (перенесення рядка в іншу групу). Потребує groupBy.
			draggable_groups: {
				type: Boolean,
				default: false
			},
			// Запамʼятовувати, які групи розгорнуті (по storage_key сторінки), замість
			// того щоб щоразу відкривати їх за expandedByDefault.
			persist_groups: {
				type: Boolean,
				default: false
			},
			// Перетягування по дереву: рядок будь-якого рівня можна і переставити між
			// сусідами, і перепідпорядкувати — drop у середину рядка робить його вкладеним.
			// Потребує slave_key; замість rowReorder таблиця емітить rowTreeMove.
			draggable_tree: {
				type: Boolean,
				default: false
			},
			drag_group: {
				type: String,
				default: ''
			},
			// Групи перетягування, рядки яких ця таблиця приймає з **іншої** таблиці на
			// сторінці (напр. список категорій приймає товар). Скидання на рядок емітить
			// rowExternalDrop у таблиці-джерелі — приймачу власного обробника не треба.
			accept_drag: {
				type: Array,
				default: () => []
			},
			// Field used as the stable per-row key for drag-and-drop hit-testing.
			// Defaults to `id`; override for datasets keyed differently (e.g. `p_id`).
			row_key: {
				type: String,
				default: 'id'
			},
			// Field in slave rows that holds the parent row's id value.
			// A non-empty value enables the master/slave (subordinate rows) mode.
			slave_key: {
				type: String,
				default: ''
			},
			// Показувати псевдо tree-marker (├ / └) у підлеглих (slave) рядках. Коли false —
			// маркер прихований, а колонка тоглу/відступу підтискається до сусідньої клітинки
			// (клас .no-slave-marker на корені прибирає правий padding цих клітинок).
			slave_marker: {
				type: Boolean,
				default: true
			},
			// Where the floating selection-mode bar is teleported to. Defaults to the
			// cabinet content area so it sits centered over the page, not the table.
			selection_overlay_target: {
				type: String,
				default: '.page-layout'
			}
        },
        data: function () {
            return {
                key: 0,
				windowWidth: window.innerWidth,
                selectAll : false,
                currentRow: {},
                contextRow: null,
				// Рядок, створений додаванням і ще не збережений. Потрібен, щоб відрізнити
				// скасоване додавання (рядок зник без id) від збереженого — див. addRow()
				// та reselectCurrent().
				addedRow: null,
				// Рядок, назва якого зараз вводиться прямо в таблиці (inline-режим додавання).
				inline_add: { row: null },
				// Manual row-selection mode (checkbox per row). Toggled via startSelection().
				selection: {
					active: false,
					multiple: false,
					label: '',
				},
                panel_data: {
                    showDeleted: false
                },
				openedGroups: new Set(),
				// Стан розкриття гілок зберігається як відхилення від типового: коли
				// підпорядковані рядки згорнуті за замовчуванням — позначаємо розкриті,
				// коли розкриті — позначаємо згорнуті користувачем.
				expandedParents: new Set(),
				collapsedParents: new Set(),
				localSortRules: [],
				sortTouched: false,
				toolsPanelHeight: 0,
				toolsPanelObserver: null,
				// Set by TableWrapper's scrolledChange event: true once the scroll box
				// is scrolled down. Drives the floating action and hides the tools panel.
				scrolledDown: false,
				// The floating CTA/FAB is revealed slightly after scrolledDown turns on
				// (eases in a beat later); hidden immediately when scrolling back up.
				showFab: false,
				fabDelayTimer: null,
				tableFillObserver: null,
				panelItemUnwatchers: [],
				restoringPanelItems: false,
				drag: {
					active: false,
					candidateRow: null,
					candidateGroup: null,
					candidateGroupKey: null,
					startX: 0,
					startY: 0,
					overRow: null,
					overGroup: null,
					overGroupKey: null,
					overPos: 'before',
					ignoreClick: false
				}
				// opened_accordion: null
            }
        },
        computed: {
			// Master/slave mode is enabled whenever a slave_key is provided.
			has_slaves() {
				return !!this.slave_key;
			},
			// Спосіб заведення запису: 'inline' — назва вводиться прямо в новому рядку
			// таблиці, 'modal' (типово) — рядок віддається сторінці, яка відкриває картку.
			inline_add_mode() {
				return this.settings.add_mode === 'inline' && !!this.inline_add_field;
			},
			// Колонка, у якій зʼявляється поле вводу при inline-додаванні. Типово — перша
			// текстова колонка (для словників це назва запису).
			inline_add_field() {
				if ( this.settings.inline_add_field )
					return this.settings.inline_add_field;

				const column = this.seenColumns.find(item =>
					item.field && item.field !== 'id' && ( !item.type || item.type === 'string' || item.type === 'subtext' )
				);

				return column ? column.field : '';
			},
			// Перетягування по дереву можливе лише разом з підпорядкуванням рядків.
			tree_drag() {
				return this.draggable_tree && this.has_slaves;
			},
			drag_enabled() {
				return this.draggable_rows || this.tree_drag;
			},
			// Набір рядків для hit-тесту при перетягуванні. У дереві це весь видимий
			// плаский список (щоб цілями були й вкладені рядки), інакше — групи як є.
			drag_grouped_data() {
				if ( !this.tree_drag )
					return this.grouped_data;

				const result = {};
				Object.keys(this.visible_rows).forEach(group_name => {
					result[group_name] = this.visible_rows[group_name].map(entry => entry.row);
				});

				return result;
			},
			// Checkbox column is shown for the static `selector` prop or while in selection mode.
			show_selector() {
				return this.selector || this.selection.active;
			},
			// Tools panel is hidden only when the opt-in prop is set and the table
			// is scrolled down. Off by default — the toolbar always stays visible.
			toolbar_hidden() {
				return this.collapse_toolbar_on_scroll && this.scrolledDown;
			},
			// CTA button (settings.ctabutton) actions, normalized for SelectableButton
			// (same mapping as TableToolsPanel — title → name).
			ctabuttonActions() {
				return (this.settings.ctabutton?.actions ?? []).map(item => ({
					...item,
					name: item.title ?? item.name,
				}));
			},
			// When a CTA button is configured, it replaces the scroll-to-top FAB in
			// the bottom-right corner while the table is scrolled down.
			hasCtaButton() {
				return this.settings.ctabutton?.type === 'SelectButton' && this.ctabuttonActions.length > 0;
			},
			hasSelection() {
				return this.table_data.some(row => row.is_selected);
			},
			// Table-specific context-menu actions declared in settings.contextmenu.
			contextActions() {
				return this.settings.contextmenu || [];
			},
			// Standard Delete/Restore item shows only where the table can actually delete.
			showContextDelete() {
				return !!(this.settings.filters?.deleted
					|| this.settings.rowbar?.some(bar => bar.event === 'onDelete'));
			},
			hasContextMenu() {
				return this.showContextDelete || this.contextActions.length > 0;
			},
			// Mobile viewport (≤ md). Mirrors the `show:'md'` column-hide breakpoint.
			is_mobile() {
				return this.windowWidth <= 768;
			},
			// Whether the rowbar column is rendered. When `rowbar_mobile_only` is set,
			// the rowbar (e.g. a Delete button) is shown on mobile only — on desktop
			// its actions live in the right-click context menu instead.
			// `rowbar_from` gates the rowbar to a minimum breakpoint (e.g. 'lg') —
			// shown only at that width and above. By default the rowbar is always on.
			show_rowbar() {
				const configured = !!(this.settings?.rowbar?.length || this.settings.filters?.deleted);
				if ( !configured )
					return false;

				if ( this.settings.rowbar_mobile_only )
					return this.is_mobile;

				if ( this.settings.rowbar_from )
					return this.windowWidth >= this.breakpointMinWidth(this.settings.rowbar_from);

				return true;
			},
            seenColumns() {
                let result = [];
                
                this.settings?.columns.forEach((column) => {
                    if ( column.field == 'id' )
                        return;

					if ( column.field == this.groupBy )
						return;

					// Колонки з extra:true видимі лише коли увімкнено settings.show_extra (Доп. інфо).
					if ( column.extra && !this.settings.show_extra )
						return;

                    if ( this.colWidth(column) == '0px' )
                        return;

                    result.push(column);
                })

                return result;
            },
			columnsCount() {
                let result = this.seenColumns?.length;

				if ( this.has_slaves )
					result++

				if ( this.show_selector )
					result++

				if (this.show_rowbar)
					result++

                return result
            },
			headerColumnsWidths() {
				const widths = [];

				this.seenColumns?.forEach(column => {
					widths.push(this.colWidth(column));
				});

				if (this.settings?.rowbar?.length || this.settings.filters?.deleted)
					widths.push('min-content');

				return widths.join(' ');
			},
            table_data() {
                // console.log('table computed table_data', this.settings.filters?.deleted, this.panel_data.showDeleted);
                let result = Object.assign( [], this.in_data);

				result = this.filterDataSource(result);
				result = this.filterDataSource_new(result);				
				result = this.orderDataSource(result);
				// result = this.groupDataSource(result, this.groupBy);

                return result;
            },
			activeAccountId() {
				return this.$page?.props?.account?.id ?? null;
			},
			activeSortRules() {
				if ( this.sortTouched )
					return this.localSortRules;

				return this.normalizeSortRules(this.settings?.orderby);
			},
			// Set of row ids currently present in the (filtered) dataset. Used to
			// detect orphan slaves whose parent isn't displayed (deleted/filtered out).
			present_row_ids() {
				const ids = new Set();
				this.table_data.forEach(row => {
					const id = row[this.row_key];
					if (id != null && id !== '')
						ids.add(String(id));
				});
				return ids;
			},
			// Рядки за їх id — для підйому вгору по ланцюжку підпорядкування.
			row_by_id() {
				const idx = {};
				this.table_data.forEach(row => {
					const id = row[this.row_key];
					if (id != null && id !== '')
						idx[String(id)] = row;
				});
				return idx;
			},
			// Гілки дерева розкриті одразу при завантаженні: для списків, де підпорядковані
			// рядки — така сама частина даних, як і верхній рівень, а не деталізація.
			slaves_expanded_by_default() {
				return !!this.settings.expand_slaves;
			},
			slave_index() {
				if (!this.slave_key) return {};
				const present = this.present_row_ids;
				const idx = {};
				this.table_data.forEach(row => {
					const pid = row[this.slave_key];
					// A parent ref must be a real id; 0 / "0" / empty mean "top-level".
					// Skip orphans whose parent isn't present (deleted/filtered) — they
					// are promoted to top-level by parent_data instead. A self-reference
					// would recurse forever, so it counts as "no parent" too.
					if (pid && pid != 0 && pid != row[this.row_key] && present.has(String(pid))) {
						if (!idx[pid]) idx[pid] = [];
						idx[pid].push(row);
					}
				});
				return idx;
			},
			parent_data() {
				if (!this.slave_key) return this.table_data;
				const present = this.present_row_ids;
				return this.table_data.filter(row => {
					const pid = row[this.slave_key];
					// Top-level when it has no parent ref, when it points at itself, or
					// when its parent is not present in the current dataset
					// (deleted/filtered) — orphan shown as root.
					return !pid || pid == 0 || pid == row[this.row_key] || !present.has(String(pid));
				});
			},
			// Плоский список видимих рядків кожної групи: верхній рівень плюс рекурсивно
			// розкриті підпорядковані рядки будь-якої глибини. Дерево сплющується тут, бо
			// рядки — display:contents у спільній grid-розкладці, і вкладені контейнери
			// зламали б вирівнювання колонок. Кожен запис несе рівень вкладеності, ознаку
			// останнього серед сусідів (tree-маркер) та стан розкриття.
			visible_rows() {
				const result = {};

				Object.keys(this.grouped_data).forEach(group_name => {
					const rows = [];
					const visited = new Set();

					const walk = (list, depth) => {
						list.forEach((row, index) => {
							const id = row[this.row_key];
							const key = ( id != null && id !== '' ) ? String(id) : null;

							// Один і той самий рядок двічі — це цикл у ланцюжку підпорядкування.
							if ( key !== null ) {
								if ( visited.has(key) )
									return;

								visited.add(key);
							}

							const children = this.has_slaves ? this.getSlaveRows(row) : [];
							const expanded = children.length > 0 && this.isParentExpanded(row);

							rows.push({
								row,
								depth,
								is_last:      index === list.length - 1,
								has_children: children.length > 0,
								expanded,
							});

							if ( expanded )
								walk(children, depth + 1);
						});
					};

					walk(this.grouped_data[group_name], 0);
					result[group_name] = rows;
				});

				return result;
			},
			grouped_data() {
                const source = this.slave_key ? this.parent_data : this.table_data;

				if ( !this.groupBy )
					return { '': source };

                return this.groupByField(source, this.groupBy);
            },
			// Готовий список груп для рендеру: ключ, заголовок, запис довідника і рядки.
			// Порядок беремо звідси, а не з ключів об'єкта груп: числоподібні ключі
			// (id категорії) об'єкт сортує сам по зростанню, і ручний порядок довідника
			// був би втрачений.
			group_entries() {
				const data = this.grouped_data;

				if ( !this.groupBy )
					return [{ key: '', title: '', item: null, rows: data[''] || [] }];

				const result = [];
				const listed = new Set();

				this.group_source.forEach(item => {
					const key = item?.[this.row_key] != null ? String(item[this.row_key]) : '';
					if ( !key || listed.has(key) )
						return;

					const rows = data[key] || [];

					// М'яко видалений запис довідника показуємо лише поки в ньому лишилися
					// рядки або ввімкнено показ видалених — інакше порожня група лише
					// засмічує список.
					if ( item.is_deleted && !rows.length && !this.panel_data.showDeleted )
						return;

					listed.add(key);
					result.push({ key, title: item.name ?? key, item, rows });
				});

				// Значення, яких немає в довіднику (порожня категорія рядка, видалений
				// запис) — окремими групами в кінці, щоб рядки не зникли зі списку.
				Object.keys(data).forEach(key => {
					if ( listed.has(key) )
						return;

					result.push({ key, title: this.groupTitle(key), item: null, rows: data[key] });
				});

				return result;
			},
			hasGroupTotals() {
				return !!this.settings.group_totals?.length;
			},
			// Accordion grouping is active when a groupBy field yields real (named) groups.
			// In this mode a group header sits directly under the table header, so the
			// header's bottom rounding is suppressed (see .is-grouped style override).
			is_grouped() {
				return !!this.groupBy
					&& Object.keys(this.grouped_data).some(name => name !== '');
			},
			selectorOffset() {
				let offset = this.show_selector ? 1 : 0;
				if (this.has_slaves) offset++;
				return offset;
			},
			firstTotalsColumnIndex() {
				if ( !this.hasGroupTotals )
					return -1;

				return this.seenColumns.findIndex(c => this.settings.group_totals.includes(c.field));
			},
			firstTotalsGridColumn() {
				if ( this.firstTotalsColumnIndex < 0 )
					return this.columnsCount + 1;

				return this.firstTotalsColumnIndex + 1 + this.selectorOffset;
			},
			group_totals_data() {
				const result = {};
				if ( !this.settings.group_totals?.length )
					return result;

				Object.keys(this.grouped_data).forEach(group_name => {
					result[group_name] = {};

					// Group totals must cover the slave (subordinate) documents too, not
					// just the parent rows shown in the group — otherwise they disagree with
					// the footer totals, which sum the whole dataset. Підпорядковані рядки
					// беруться на всю глибину вкладеності й незалежно від того, чи розкриті.
					const group_rows = [];
					const counted = new Set();
					this.grouped_data[group_name].forEach(row => this.collectWithSubordinates(row, group_rows, counted));

					this.settings.group_totals.forEach(field => {
						// За замовчуванням підсумок групи — сума. Колонка може задати
						// group_agg:'avg' (середнє) та group_agg_field (агрегувати інше поле рядка).
						const total_column = this.settings.columns?.find(c => c.field === field);
						const aggregate    = total_column?.group_agg || 'sum';
						const source_field = total_column?.group_agg_field || field;
						let sum = 0;
						let valid_count = 0;
						group_rows.forEach(row => {
							const value = parseFloat(String(row[source_field]).replace(',', '.').replace('в€’', '-'));
							if ( isNaN(value) )
								return;

							sum += value;
							valid_count++;
						});
						result[group_name][field] = aggregate === 'avg'
							? ( valid_count ? sum / valid_count : 0 )
							: sum;
					});
				});

				return result;
			},
            select_sources() {
                // console.log('ss', this.settings.selects);
                let result = {};

				// Old option
				if ( this.settings.selects )
					Object.keys(this.settings.selects).forEach(key => {
						result[key] = $H.Ar.toAssociative(this.settings.selects[key], "id");
					})
				// New option
				else
					Object.keys(this.selects).forEach(key => {
						result[key] = $H.Ar.toAssociative(this.selects[key], "id");
					})
                

                return result;
            },
            totals() {
                let result = {};
                if ( !this.settings.totals )
                    return result

                this.settings.totals.forEach(entry=>{
                    if ( entry && typeof entry === 'object' && entry.field ) {
                        result[entry.field] = { static: true, value: entry.value, icon: entry.icon };
                        return;
                    }

                    let field = entry;
                    let sum=0;
                    this.table_data.forEach(item=>{
                        let value = parseFloat(String(item[field]).replace(',', '.').replace('в€’', '-'));
                        sum += isNaN(value) ? 0 : value;
                    })
                    const column = this.settings.columns?.find(c => c.field === field);
                    result[field] = this.formatColumnTotal(column, sum);
                });

                return result;
            },
        },
		watch: {
			activeAccountId() {
				this.restoreSortRules();
				this.restorePanelItems();
				this.openedGroups.clear();
				this.restoreOpenedGroups();
			},
			// Зберігаємо підсвічування поточного рядка, коли набір рядків змінюється
			// (напр. словник перезавантажується після збереження картки й reconciliation
			// може скинути прапор selected або підмінити посилання) — переобираємо за id.
			present_row_ids() {
				this.reselectCurrent();
			},
		},
        created() {
            // Стан розгорнутих груп потрібен ДО першого рендеру: акордеон читає
            // початковий стан лише при своєму монтуванні.
            this.restoreOpenedGroups();
        },
        mounted() {
            // this.settings.groupactions = this.settings.groupactions || {};
            // this.settings.filters = this.settings.filters || {};
            window.addEventListener("resize", this.updateWidth);
            this.setFiltersDefault();
            this.$nextTick(() => {
                this.observeToolsPanel();
                this.observeTableFill();
            });
			this.restoreSortRules();
			this.restorePanelItems();
        },
        beforeUnmount() {
            window.removeEventListener("resize", this.updateWidth);
            if (this.toolsPanelObserver) {
                this.toolsPanelObserver.disconnect();
                this.toolsPanelObserver = null;
            }
            if (this.tableFillObserver) {
                this.tableFillObserver.disconnect();
                this.tableFillObserver = null;
            }
            this.unwatchPanelItems();
            clearTimeout(this.fabDelayTimer);
        },
        updated() {
            this.$nextTick(() => {
                this.observeToolsPanel();
                this.updateTableSpacer();
            });
        },
        methods: {
			// Минимальная ширина (px) для брейкпоинта — мин-граница Tailwind-брейкпоинтов.
			// Используется для `rowbar_from` (показывать rowbar начиная с брейкпоинта).
			breakpointMinWidth(breakpoint) {
                const map = {
                    xs:    480,
                    sm:    640,
                    md:    768,
                    lg:    1024,
                    xl:    1280,
                    '2xl': 1536,
                    xxl:   1536,
                };

                return map[breakpoint] ?? 0;
            },
			colWidth(column) {
                if (column.show=='xs'    && this.windowWidth <= 480)
                    return '0px'
                if (column.show=='sm'    && this.windowWidth <= 640)
                    return '0px'
                if (column.show=='md'    && this.windowWidth <= 768)
                    return '0px'
                if (column.show=='lg'    && this.windowWidth <= 1024)
                    return '0px'
                if (column.show=='xl'    && this.windowWidth <= 1280)
                    return '0px'
                if (column.show=='xxl'   && this.windowWidth <= 1536)
                    return '0px'
                if (column.show=='lt-xs'  && this.windowWidth >= 480)
                    return '0px'
                if (column.show=='lt-sm'  && this.windowWidth >= 640)
                    return '0px'
                if (column.show=='lt-md'  && this.windowWidth >= 768)
                    return '0px'
                if (column.show=='lt-lg'  && this.windowWidth >= 1024)
                    return '0px'
                if (column.show=='lt-xl'  && this.windowWidth >= 1280)
                    return '0px'
                if (column.show=='lt-2xl' && this.windowWidth >= 1536)
                    return '0px'
                
                if (column.width=='auto-1/2')
                    return 'minmax(min-content, 0.5fr)';

                if (column.width=='auto' || !column.width)
                    return 'minmax(min-content, 1fr)';

                if (column.width=='min')
                    return 'min-content';

				if (column.width=='max')
                    return 'min-content';

                // Ширина від заданого px-значення (мінімум) до 1fr (максимум):
                // width: '200px-1fr' → minmax(200px, 1fr). Підтримує px/rem/em/%.
                const widthFromTo = String(column.width).match(/^(\d+(?:\.\d+)?(?:px|rem|em|%)?)-1fr$/);
                if (widthFromTo)
                    return `minmax(${widthFromTo[1]}, 1fr)`;

                return column.width;
            },

			// Returns the saved per-page table slice, or {} if none / no storage_key.
			getTableState() {
				if ( !this.storage_key || !this.$settings ) return {};
				return this.$settings.getPageState(this.storage_key, this.activeAccountId).table || {};
			},
			mergeTableState(partial) {
				if ( !this.storage_key || !this.$settings ) return;
				const current = this.getTableState();
				this.$settings.mergePageState(this.storage_key, this.activeAccountId, {
					table: { ...current, ...partial },
				});
			},

			restoreSortRules() {
				if ( !this.storage_key || !this.$settings ) return;

				let saved = this.getTableState().sort;

				// Backward-compat: read legacy `table_sort` storage one last time
				if ( !Array.isArray(saved) ) {
					const accountId = String(this.activeAccountId ?? 'default');
					const legacy = this.$settings.getSetting('table_sort', {}) || {};
					saved = legacy?.[accountId]?.[this.storage_key];
				}

				if ( !Array.isArray(saved) ) return;

				const validRules = saved.filter(rule =>
					rule && typeof rule === 'object' && rule.field &&
					(rule.direction === 'asc' || rule.direction === 'desc')
				);

				if ( !validRules.length ) return;

				this.localSortRules = validRules;
				this.sortTouched = true;
			},
			saveSortRules() {
				this.mergeTableState({ sort: this.localSortRules });
			},

			// в”Ђв”Ђв”Ђ Panel items / custom tools persistence в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
			// Items marked with `persist: true` in settings.panelitems or
			// settings.custom_tools get their `model` saved per-page.
			persistedPanelItems() {
				const result = [];
				const groups = [
					this.settings.panelitems || {},
					this.settings.custom_tools || {},
				];

				groups.forEach(group => {
					Object.keys(group).forEach(key => {
						if ( group[key] && group[key].persist )
							result.push({ key, item: group[key] });
					});
				});

				return result;
			},
			restorePanelItems() {
				this.unwatchPanelItems();
				if ( !this.storage_key || !this.$settings ) return;

				const saved = this.getTableState().panel_items || {};
				this.restoringPanelItems = true;

				this.persistedPanelItems().forEach(({ key, item }) => {
					if ( saved[key] !== undefined )
						item.model = saved[key];
					else if ( item.default !== undefined )
						item.model = item.default;

					// Fire action so the consumer can mirror state on restore.
					// (Programmatic model assignment does not trigger v-model @change.)
					if ( typeof item.action === 'function' )
						item.action(item.model);
				});

				this.$nextTick(() => {
					this.restoringPanelItems = false;
					this.setupPanelItemWatchers();
				});
			},
			setupPanelItemWatchers() {
				this.persistedPanelItems().forEach(({ item }) => {
					const unwatch = this.$watch(
						() => item.model,
						() => {
							if ( this.restoringPanelItems ) return;
							this.savePanelItems();
						}
					);
					this.panelItemUnwatchers.push(unwatch);
				});
			},
			unwatchPanelItems() {
				this.panelItemUnwatchers.forEach(fn => fn());
				this.panelItemUnwatchers = [];
			},
			savePanelItems() {
				const data = {};
				this.persistedPanelItems().forEach(({ key, item }) => {
					data[key] = item.model;
				});
				this.mergeTableState({ panel_items: data });
			},
			observeToolsPanel() {
				const el = this.$refs.toolsPanel?.$el;

				if (!el) {
					if (this.toolsPanelObserver) {
						this.toolsPanelObserver.disconnect();
						this.toolsPanelObserver = null;
					}
					if (this.toolsPanelHeight !== 0) this.toolsPanelHeight = 0;
					return;
				}

				if (this.toolsPanelObserver?._el === el) return;

				if (this.toolsPanelObserver) this.toolsPanelObserver.disconnect();

				const updateHeight = () => {
					const style = window.getComputedStyle(el);
					const marginBottom = parseFloat(style.marginBottom) || 0;
					this.toolsPanelHeight = el.offsetHeight + marginBottom;
				};

				this.toolsPanelObserver = new ResizeObserver(updateHeight);
				this.toolsPanelObserver._el = el;
				this.toolsPanelObserver.observe(el);
				updateHeight();
			},
			observeTableFill() {
				const spacer = this.$refs.tableSpacer;
				if (!spacer) return;

				const wrapper = spacer.closest('.t-wrapper');
				if (!wrapper) return;

				if (this.tableFillObserver?._el === wrapper) {
					this.updateTableSpacer();
					return;
				}

				if (this.tableFillObserver) this.tableFillObserver.disconnect();

				this.tableFillObserver = new ResizeObserver(() => this.updateTableSpacer());
				this.tableFillObserver._el = wrapper;
				this.tableFillObserver.observe(wrapper);
				this.updateTableSpacer();
			},
			updateTableSpacer() {
				const spacer = this.$refs.tableSpacer;
				if (!spacer) return;

				const wrapper = spacer.closest('.t-wrapper');
				if (!wrapper) return;

				const currentH = spacer.offsetHeight;

				// Collapse to measure layout WITHOUT current spacer contribution
				if (currentH > 0) spacer.style.height = '0px';

				const measureBottom = (node) => {
					const r = node.getBoundingClientRect();
					if (r.width === 0 && r.height === 0 && node.children.length > 0) {
						let max = -Infinity;
						for (const child of node.children) {
							const v = measureBottom(child);
							if (v > max) max = v;
						}
						return max;
					}
					return r.bottom;
				};

				let trailingBottom = spacer.getBoundingClientRect().bottom;
				let cur = spacer.nextElementSibling;
				while (cur) {
					const b = measureBottom(cur);
					if (b > trailingBottom) trailingBottom = b;
					cur = cur.nextElementSibling;
				}

				// The spacer only fills the visible slack of the actual scroll viewport —
				// the nearest scrollable ancestor (an inner overflow box, or the cabinet page
				// scroller .scrolled-wrapper), falling back to the window when nothing scrolls.
				// Bounding by that box (not the window) lets the spacer collapse to zero once
				// the content already overflows it, instead of padding it down to the bottom of
				// the screen — which showed as an extra blank row when scrolling down inside a
				// smaller scroll container (e.g. ManageOrders).
				const viewportBound = window.innerHeight;
				const scroller = this.findSpacerScrollContainer(wrapper);
				const maxBottom = scroller
					? Math.min(scroller.getBoundingClientRect().top + scroller.clientHeight, viewportBound)
					: viewportBound;

				const target = Math.max(0, maxBottom - trailingBottom);

				if (Math.abs(target - currentH) > 0.5)
					spacer.style.height = target + 'px';
				else if (currentH > 0)
					spacer.style.height = currentH + 'px';
			},
			// Nearest scrollable ancestor of the table — the box whose visible area the
			// spacer fills. Mirrors TableWrapper.findScrollContainer: accept the cabinet
			// page scroller (.scrolled-wrapper) explicitly, otherwise the first ancestor
			// with overflow-y auto/scroll. Returns null when the window itself scrolls.
			findSpacerScrollContainer(fromEl) {
				let el = fromEl;
				while (el && el !== document.body && el !== document.documentElement) {
					if (!(el instanceof Element)) {
						el = el.parentElement;
						continue;
					}

					if (el.classList.contains('scrolled-wrapper'))
						return el;

					const overflow_y = window.getComputedStyle(el).overflowY;
					if (overflow_y === 'auto' || overflow_y === 'scroll')
						return el;

					el = el.parentElement;
				}
				return null;
			},
			getTableData() {
				return this.grouped_data
			},
			openGroup(group_key) {
				// console.log('openGroup', this.$refs);

				this.openedGroups.add(group_key)
				this.saveOpenedGroups()

				if ( !this.single_open_accordion )
					return true

				this.openedGroups.forEach(item => {
					// console.log('item', 'group'+item);
					if ( item != group_key )
						this.groupAccordion(item)?.close()
				})
			},

			closeGroup(group_key) {
				this.openedGroups.delete(group_key)
				this.saveOpenedGroups()
			},
			// Чи має група відкритися при появі: із запамʼятованого стану або за
			// типовим налаштуванням розгортання.
			isGroupOpened(group_key) {
				if ( !this.persist_groups )
					return !!this.expandedByDefault;

				return this.openedGroups.has(String(group_key));
			},
			restoreOpenedGroups() {
				if ( !this.persist_groups )
					return;

				const saved = this.getTableState().opened_groups;
				if ( !Array.isArray(saved) )
					return;

				saved.forEach(key => this.openedGroups.add(String(key)));
			},
			saveOpenedGroups() {
				if ( !this.persist_groups )
					return;

				this.mergeTableState({ opened_groups: [...this.openedGroups].map(String) });
			},
			// Компонент-акордеон групи за її ключем.
			groupAccordion(group_key) {
				const ref = this.$refs['group' + group_key];
				return Array.isArray(ref) ? ref[0] : ref;
			},
			// Публічне розгортання групи за ключем (напр. після перенесення рядка в неї —
			// інакше рядок «зникне» у згорнутій групі).
			expandGroup(group_key) {
				if ( group_key == null || group_key === '' )
					return;

				const accordion = this.groupAccordion(String(group_key));
				if ( accordion && typeof accordion.open === 'function' )
					accordion.open();
			},
			// Заголовок групи, якої немає в довіднику груп — саме значення поля.
			groupTitle(group_key) {
				return this.translate_groups ? this.$t(group_key) : group_key;
			},
			// Кнопки дій над групою; видимість може залежати від прапорця запису
			// довідника (напр. видалити / відновити для м'яко видаленої категорії).
			groupActionsOf(group_entry) {
				if ( !group_entry.key )
					return [];

				return (this.settings.group_actions || []).filter(action => {
					if ( action.flag && !group_entry.item?.[action.flag] )
						return false;

					if ( action.flag_off && group_entry.item?.[action.flag_off] )
						return false;

					return true;
				});
			},
			groupAction(action, group_entry) {
				// Table.groupAction
				if ( action.event )
					this.$emit(action.event, group_entry.item, group_entry.rows);
				else if ( typeof action.action === 'function' )
					action.action(group_entry.item, group_entry.rows);
			},
			// Per-row CSS class(es) from settings.row_class — function (row) => string|object|array
			// (or a static string). Дозволяє сторінці підсвічувати окремі рядки (напр. акцентний бар).
			rowClass(row) {
				const rule = this.settings.row_class;
				return typeof rule === 'function' ? rule(row) : (rule || '');
			},
			groupByField(array, group_field) {
				let result = array.reduce((groups, item) => {
					// console.log('a', item)
					const group = item[group_field] || '';

					if (!groups[group]) 
						groups[group] = reactive([])

					groups[group].push(item);

					return groups;
				}, {})

                return result
			},

			
			// New array filters helper
			decodeFieldValue(raw_value, field_type) {
				let result = raw_value;

				if ( field_type == 'date' )
					result = this.$dayjs(raw_value).format('YYYY-MM-DD')

				return result;
			},

			// Array filters (props)
			filterDataSource_new(datasource) {
				// console.log('filterDataSource_new', datasource.length);
				
				let result = datasource;
				const conditions = ['==', '!=', '>', '>=', '<', '<=', 'like']

				this.filters.forEach((filter) => {
					// console.log('qqq', filter);					
					if ( !Array.isArray(filter) )
						return

					const field = filter[0]
					const condition = conditions.includes(filter[1]) ? filter[1] : '=='
					const filter_value = conditions.includes(filter[1]) ? filter[2] : filter[1]
					const filter_type =  conditions.includes(filter[1]) ? filter[3] : filter[2]
					// console.log('filter', field, condition, filter_value, filter_type);

					if ( !filter_value && filter_value!=0)
						return					

					// const field_settings = this.settings.columns.find(t => t.field == field)
					// console.log('field_settings', field_settings);
					
					result = result.filter((row) => {						
						const field_value = this.decodeFieldValue(row[field], filter_type);						
						// console.log('field_value', row[field], field_value, condition, filter_value);
						
						switch (condition) {
							case '!=':
								if ( field_value == filter_value)
									return false;
								break;
							case '==':
								if ( field_value != filter_value)
									return false;
								break;
							case '>':
								if ( field_value <= filter_value)
									return false;
								break;
							case '>=':
								if ( field_value < filter_value)
									return false;
								break;
							case '<':
								if ( field_value >= filter_value)
									return false;
								break;
							case '<=':
								if ( field_value > filter_value)
									return false;
								break;
							case 'like':
								// console.log(field_value);
								
								if ( !field_value.toLowerCase().includes(filter_value) )
									return false;
								break;

							default:
								break;
						}

						return true;
					})

				})

				return result
			},

			// Old filters
			filterDataSource(datasource) {
				// console.log('---new filter');
				
                let result = datasource.filter((row) => {   
                    // console.log('row', this.filters);
                    if ( this.settings.filters?.deleted && !this.panel_data.showDeleted && row['is_deleted'] )
                        return false;
                    if ( !this.settings.filters?.deleted && row['is_deleted'])
                        return false

                    for ( let key in this.settings.filters) {
						// console.log('key', key);
						if ( this.filters[key]==undefined || this.filters[key]==null || row[key]==undefined || row[key]==null )
							continue;											

                        if ( this.filters[key+'_type']=='!=' && row[key] == this.filters[key] ) {
							return false;
						}
                        if ( this.filters[key+'_type']!='!=' && row[key] != this.filters[key] ) {
                            return false;
						}
                    }

                    return true;
                });

				return result;
			},
			orderByKey(datasource, key, order = 'asc') {
				const rules = this.normalizeSortRules([{ field: key, direction: order }]);
				return this.sortDataSource(datasource, rules);
			},
			orderDataSource(datasource) {
				const rules = this.activeSortRules;
				if ( !rules.length )
					return datasource

				return this.sortDataSource(datasource, rules);
			},
			// Поле, за яким колонка реально порівнюється. Колонка може показувати
			// відформатоване значення (дата 'DD.MM.YY', підпис замість числа) — тоді
			// сортувати треба за сирим полем із settings.columns[].sort_field, інакше
			// порядок рахується по тексту й виходить довільним.
			sortValueField(field) {
				const column = (this.settings?.columns || []).find(item => item?.field === field);
				return column?.sort_field || field;
			},
			sortDataSource(datasource, rules) {
				if ( !datasource.length )
					return datasource

				const resolved_rules = rules.map(rule => ({ ...rule, field: this.sortValueField(rule.field) }));

				return [...datasource].sort((a, b) => {
					for (const rule of resolved_rules) {
						const compare_result = this.compareSortValues(a[rule.field], b[rule.field], rule.direction);
						if ( compare_result !== 0 )
							return compare_result;
					}

					return 0;
				});
			},
			compareSortValues(valueA, valueB, direction = 'asc') {
				const sort_direction = direction == 'desc' ? -1 : 1;
				const normalizedA = this.normalizeSortValue(valueA);
				const normalizedB = this.normalizeSortValue(valueB);

				if ( normalizedA == null && normalizedB == null )
					return 0;
				if ( normalizedA == null )
					return 1;
				if ( normalizedB == null )
					return -1;

				if ( normalizedA < normalizedB )
					return -1 * sort_direction;

				if ( normalizedA > normalizedB )
					return 1 * sort_direction;

				return 0;
			},
			normalizeSortValue(rawValue) {
				if ( rawValue === null || rawValue === undefined )
					return null;

				if ( rawValue instanceof Date )
					return rawValue.getTime();

				if ( typeof rawValue == 'string' ) {
					const trimmed_value = rawValue.trim();
					if ( trimmed_value === '' )
						return null;

					const as_number = Number(trimmed_value);
					if ( !Number.isNaN(as_number) )
						return as_number;

					const as_date = this.parseSortDate(trimmed_value);
					if ( as_date !== null )
						return as_date;

					return trimmed_value.toLowerCase();
				}

				if ( typeof rawValue == 'number' )
					return Number.isNaN(rawValue) ? null : rawValue;

				if ( typeof rawValue == 'boolean' )
					return rawValue ? 1 : 0;

				return String(rawValue).toLowerCase();
			},
			// Дату разбираем по частям, а не целой строкой: встроенный разбор строки
			// в Safari возвращает не-дату там, где Chrome справляется, и колонка
			// тихо сортируется как текст.
			parseSortDate(value) {
				const formats = [
					{ pattern: /^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/,  parse: Datetime.parseReverseString },
					{ pattern: /^\d{2}\.\d{2}\.\d{4}( \d{2}:\d{2}(:\d{2})?)?$/,   parse: Datetime.parseSimpleString  },
				];

				for ( const format of formats ) {
					if ( !format.pattern.test(value) )
						continue;

					const parsed = format.parse.call(Datetime, value.replace('T', ' ')).getTime();
					return Number.isNaN(parsed) ? null : parsed;
				}

				return null;
			},
			normalizeSortRules(orderby) {
				if ( !Array.isArray(orderby) )
					return [];

				return orderby.reduce((rules, order_rule) => {
					if ( typeof order_rule == 'string' && order_rule ) {
						rules.push({ field: order_rule, direction: 'asc' });
						return rules;
					}

					if ( !order_rule || typeof order_rule != 'object' || !order_rule.field )
						return rules;

					rules.push({
						field: order_rule.field,
						direction: order_rule.direction == 'desc' ? 'desc' : 'asc',
					});

					return rules;
				}, []);
			},
			isSortable(column) {
				if ( !column?.field )
					return false

				if ( column.field == 'id' )
					return false

				return column.sortable !== false
			},
			applySort(column) {
				if ( !this.allowSorting )
					return

				if ( !this.isSortable(column) )
					return

				if ( !this.sortTouched ) {
					this.localSortRules = [...this.activeSortRules];
					this.sortTouched = true;
				}

				const field = column.field;
				const existing_index = this.localSortRules.findIndex(rule => rule.field == field);

				if ( existing_index < 0 ) {
					this.localSortRules.push({ field, direction: 'asc' });
				} else if ( this.localSortRules[existing_index].direction == 'asc' ) {
					this.localSortRules.splice(existing_index, 1, { field, direction: 'desc' });
				} else {
					this.localSortRules.splice(existing_index, 1);
				}

				this.$emit('sortChange', [...this.localSortRules]);
				this.saveSortRules();
			},

            unselectAll() {
                // Скидати виділення по ПОВНОМУ джерелу (in_data), а не по table_data:
                // table_data відфільтроване (пошук/фільтри), тож раніше виділений рядок,
                // який зараз відфільтрований, лишався б із selected=true і «спливав» як
                // зайве множинне виділення при зміні фільтра/пошуку.
                this.in_data.forEach((row) => {
                    row.selected = false;
                })
                this.currentRow = null
            },
            selectRow(row) {
                // console.log('Table.selectRow', row, this.selectable);
                if ( !this.selectable ) 
                    return;

                this.unselectAll()
                this.currentRow = row
                row.selected = true

                // Підпорядкований рядок може бути схований під згорнутими рівнями —
                // розкриваємо їх, щоб виділення було видно.
                this.expandAncestors(row[this.row_key]);

                // Якщо рядок у згорнутій групі — розгортаємо групу, щоб виділення було видно.
                this.$nextTick(() => this.expandRowGroup(row));

				// console.log('Table.selectRow', row, this.selectable);
            },
            // Розгортає акордеон-групу, до якої належить рядок (за полем groupBy).
            expandRowGroup(row) {
                if ( !this.groupBy || !row )
                    return;

                const group_key = row[this.groupBy] ? String(row[this.groupBy]) : '';
                if ( !group_key )
                    return;

                const accordion = this.groupAccordion(group_key);
                if ( accordion && typeof accordion.open === 'function' )
                    accordion.open();
            },
            // Переобрати поточний рядок за id після оновлення даних, щоб підсвічування
            // зберігалося при reconciliation словника (default-поведінка всіх таблиць,
            // напр. нова статтю лишається виділеною одразу після збереження картки).
            reselectCurrent() {
                if ( !this.selectable )
                    return;

                if ( this.addedRow ) {
                    const added_id = this.addedRow[this.row_key];
                    const added_saved = added_id != null && added_id !== '';

                    // Рядок так і не отримав id і зник із даних — додавання скасували
                    // (напр. картку закрили без збереження). Стандартна реакція на
                    // скасування — зняти виділення, а не підсвічувати щось інше.
                    if ( !added_saved && !this.in_data.includes(this.addedRow) ) {
                        this.addedRow = null;
                        this.inline_add.row = null;
                        this.unselectAll();
                        return;
                    }

                    // Рядок збережено (зʼявився id) — трекінг більше не потрібен,
                    // підсвічування далі тримає переобрання за id.
                    if ( added_saved )
                        this.addedRow = null;
                }

                if ( !this.currentRow )
                    return;

                const id = this.currentRow[this.row_key];
                if ( id == null || id === '' )
                    return;

                const row = this.in_data.find(item => item && item[this.row_key] === id);
                if ( row && !row.selected )
                    this.selectRow(row);
            },
            // Public: keep a row highlighted by id across the next data reload, even
            // when it isn't present yet. Used when an action replaces the selected row
            // with a NEW row of a different id (e.g. converting a document to another
            // type): call it before reloading, and reselectCurrent() will highlight the
            // new row once it appears in the dataset.
            selectRowById(id) {
                if ( !this.selectable || id == null || id === '' )
                    return;

                const row = this.in_data.find(item => item && item[this.row_key] === id);
                if ( row ) {
                    this.selectRow(row);
                    return;
                }

                // Not loaded yet — track it so the present_row_ids watcher reselects it.
                this.currentRow = { [this.row_key]: id };
            },
            selectFirst() {
                if ( !this.table_data.length )
                    return;

                let first_row = this.table_data[0];
                this.selectRow(first_row)
            },
            selectLast() {
                if ( !this.table_data.length )
                    return;

                let last_row = this.table_data[ this.table_data.length-1 ];
                this.selectRow(last_row)
            },
            rowClick(row) {
				// console.log('Table.rowClick');
				if (this.drag.ignoreClick) return;

				// Перехід на інший рядок під час inline-вводу — це відмова від додавання.
				if (this.inline_add.row && this.inline_add.row !== row) {
					this.cancelAdd();
					return;
				}

				// In selection mode a row click toggles its checkbox instead of opening it.
				if (this.selection.active) {
					this.onSelectionToggle(row);
					return;
				}

                if (this.selectable) {
                    this.selectRow(row)
					this.$emit('rowSelect', row)
                }
            },

			// ─── Manual selection mode ──────────────────────────────────
			// Enter selection mode. options: { multiple?: bool, label?: string }.
			startSelection(options = {}) {
				// Table.startSelection
				this.clearSelection();
				this.selection.multiple = !!options.multiple;
				this.selection.label = options.label || '';
				this.selection.active = true;
			},
			cancelSelection() {
				// Table.cancelSelection
				this.exitSelection();
				this.$emit('selectionCancel');
			},
			confirmSelection() {
				// Table.confirmSelection
				const selected = this.getSelectedRows();
				if (!selected.length) return;

				this.$emit('selectionConfirm', selected);
				this.exitSelection();
			},
			exitSelection() {
				// Table.exitSelection
				this.selection.active = false;
				this.clearSelection();
			},
			clearSelection() {
				// Table.clearSelection
				this.table_data.forEach(row => { row.is_selected = false; });
			},
			getSelectedRows() {
				// Table.getSelectedRows
				return this.table_data.filter(row => row.is_selected);
			},
			onSelectionToggle(row) {
				// Table.onSelectionToggle
				const next = !row.is_selected;

				// Single mode: only one row stays checked.
				if (!this.selection.multiple && next)
					this.clearSelection();

				row.is_selected = next;
			},

			// Right-click: highlight the row (without opening it) and show the context menu.
			onRowContext(row, event) {
				// Table.onRowContext
				if (this.drag.active) return;
				if (this.selection.active) return;
				if (!this.hasContextMenu) return;

				event.preventDefault();

				this.selectRow(row);
				this.contextRow = row;

				this.$nextTick(() => {
					this.$refs.contextMenu?.open(event.clientX, event.clientY);
				});
			},
			onContextSelect(item) {
				// Table.onContextSelect
				if (!item) return;

				const row = this.contextRow;

				if (item.event)
					this.$emit(item.event, row);
				else if (typeof item.action === 'function')
					item.action(row);
			},

			// в”Ђв”Ђв”Ђ Drag & drop reordering в”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђв”Ђ
			onRowMouseDown(row, group, event) {
				// Table.onRowMouseDown вЂ” delegates all mechanics to TableDragHandler.
				if (!this.drag_enabled) return;
				this.$refs.dragHandler?.onRowMouseDown(row, group, event);
			},
			onGroupMouseDown(group_key, event) {
				// Table.onGroupMouseDown — перетягування самої групи (порядок груп).
				if (!this.draggable_groups) return;
				this.$refs.dragHandler?.onGroupMouseDown(group_key, event);
			},
			// Плаский режим тягне лише верхній рівень (глибші рядки — наслідок
			// підпорядкування, їх порядок несамостійний); дерево — рядок будь-якого рівня.
			canDragRow(entry) {
				if ( this.tree_drag )
					return true;

				return this.draggable_rows && !entry.depth;
			},
			dragGroupOf(group_name) {
				return this.drag_grouped_data[group_name] || [];
			},
			// Ціль перетягування у дереві: не сам рядок і не його нащадок — інакше гілка
			// відʼєдналася б від кореня в замкнене кільце.
			canDropOn(row, target, position) {
				if ( !this.tree_drag )
					return true;

				if ( !row || !target || row === target )
					return false;

				return !this.isDescendantOf(target, row);
			},
			isDescendantOf(row, ancestor) {
				// Table.isDescendantOf — підйом ланцюжком підпорядкування вгору.
				const ancestor_id = String(ancestor[this.row_key]);
				const guard = new Set();
				let current = row;

				while ( current ) {
					const pid = current[this.slave_key];
					if ( !pid || pid == 0 || guard.has(String(pid)) )
						return false;

					if ( String(pid) === ancestor_id )
						return true;

					guard.add(String(pid));
					current = this.row_by_id[String(pid)];
				}

				return false;
			},
			onDragUpdate(state) {
				// Table.onDragUpdate вЂ” mirror handler state so the template renders placeholders.
				this.drag.active            = state.active;
				this.drag.candidateRow      = state.candidateRow;
				this.drag.candidateGroup    = state.candidateGroup;
				this.drag.candidateGroupKey = state.candidateGroupKey;
				this.drag.overRow           = state.overRow;
				this.drag.overGroup         = state.overGroup;
				this.drag.overGroupKey      = state.overGroupKey;
				this.drag.overPos           = state.overPos;
				this.drag.ignoreClick       = state.ignoreClick;
			},
			onDragDrop({ row, group, group_key, overRow, overGroup, overGroupKey, overPos, external }) {
				// Тягнули шапку групи — міняється порядок самих груп у довіднику.
				if ( group_key != null )
					return this.emitGroupReorder(group_key, overGroupKey, overPos);

				// Рядок кинули в сусідню таблицю-приймач: своєї моделі її рядка тут немає,
				// сторінці віддається ключ, за яким вона його й знайде.
				if ( external )
					return this.$emit('rowExternalDrop', { row, group: external.group, row_key: external.row_key });

				// Рядок кинули на шапку групи — переходить у неї (в кінець).
				if ( overGroupKey != null )
					return this.emitRowGroupMove(row, overGroupKey, null, null);

				if ( this.tree_drag )
					return this.emitTreeMove(row, overRow, overPos);

				// Рядок кинули в іншу групу — переходить у неї на місце цілі.
				if ( overGroup !== group ) {
					if ( !this.draggable_groups )
						return;

					return this.emitRowGroupMove(row, overRow?.[this.groupBy], overRow, overPos);
				}

				const fromIndex   = group.indexOf(row);
				const targetIndex = group.indexOf(overRow);
				if (fromIndex < 0 || targetIndex < 0) return;

				let toIndex = overPos === 'before' ? targetIndex : targetIndex + 1;
				if (fromIndex < toIndex) toIndex -= 1;
				if (toIndex < 0 || toIndex >= group.length) return;
				if (fromIndex === toIndex) return;

				this.$emit('rowReorder', { row, fromIndex, toIndex, dataset: group });
			},
			// Зміна порядку самих груп: таблиця віддає записи довідника груп і бік,
			// на який стала перетягнута група.
			emitGroupReorder(group_key, target_key, position) {
				// Table.emitGroupReorder
				if ( group_key == null || target_key == null || group_key === target_key )
					return;

				const item   = this.groupItemOf(group_key);
				const target = this.groupItemOf(target_key);

				// Групи-сироти (значення без запису в довіднику) переставляти нікуди.
				if ( !item || !target )
					return;

				this.$emit('groupReorder', { item, target, position });
			},
			// Перенесення рядка в іншу групу. target/position задані, лише коли рядок
			// кинули на конкретне місце всередині групи (а не на її шапку).
			emitRowGroupMove(row, group_key, target, position) {
				// Table.emitRowGroupMove
				if ( !row || group_key == null || group_key === '' )
					return;

				const from_key = row[this.groupBy] != null ? String(row[this.groupBy]) : '';
				const to_key   = String(group_key);

				if ( from_key === to_key && !target )
					return;

				// Інакше перенесений рядок «зник» би у згорнутій групі.
				this.expandGroup(to_key);

				this.$emit('rowGroupMove', {
					row,
					from_key,
					to_key,
					item: this.groupItemOf(to_key),
					target: target || null,
					position: target ? position : null,
				});
			},
			groupItemOf(group_key) {
				return this.group_entries.find(entry => entry.key === String(group_key))?.item || null;
			},
			// Переміщення в дереві: рядок стає сусідом цілі (before/after) або вкладеним
			// у неї (into). Таблиця віддає готовий результат — нове підпорядкування і
			// порядок майбутніх сусідів, щоб сторінка лише зберегла його.
			emitTreeMove(row, target, position) {
				// Table.emitTreeMove
				if ( !row || !target )
					return;

				if ( !this.canDropOn(row, target, position) )
					return;

				const parent_id = position === 'into'
					? target[this.row_key]
					: this.parentIdOf(target);

				const siblings = this.siblingRows(parent_id).filter(item => item !== row);

				if ( position === 'into' ) {
					siblings.push(row);
				} else {
					const index = siblings.indexOf(target);
					if ( index < 0 )
						return;

					siblings.splice(position === 'before' ? index : index + 1, 0, row);
				}

				// Вкладення у згорнутий рядок інакше виглядало б як зникнення.
				if ( position === 'into' )
					this.expandParent(target);

				this.$emit('rowTreeMove', { row, target, position, parent_id: parent_id ?? null, siblings });
			},
			// Підпорядкування рядка, нормалізоване до null: 0/порожнє/зниклий батько —
			// це верхній рівень (так само, як це трактує побудова дерева).
			parentIdOf(row) {
				const pid = row[this.slave_key];

				if ( !pid || pid == 0 || !this.present_row_ids.has(String(pid)) )
					return null;

				return pid;
			},
			// Поточні сусіди по рівню (діти заданого батька або верхній рівень).
			siblingRows(parent_id) {
				if ( parent_id == null )
					return this.parent_data.slice();

				return (this.slave_index[parent_id] || []).slice();
			},

            rowbarAction(action, row) {
				// console.log('[Table.rowbarAction]');				
                this.selectRow(row)
                this.$emit(action, row)
            },
            updateWidth() {
                this.windowWidth = window.innerWidth;
                this.updateTableSpacer();
            },
            switchSelection() {
                let position = this.selectAll;

                this.in_data.forEach(item => {
                    item.is_selected = position;
                })

                // this.selectAll = !this.selectAll;

            },
            // Scroll state from TableWrapper: hides the tools panel immediately while
            // scrolled down, and reveals the floating CTA/FAB a beat later (so it eases
            // in slightly after scrolling rather than the instant the threshold is hit).
            onWrapperScrolled(scrolled) {
                // Table.onWrapperScrolled
                this.scrolledDown = scrolled;

                clearTimeout(this.fabDelayTimer);
                this.fabDelayTimer = null;

                if ( scrolled ) {
                    this.fabDelayTimer = setTimeout(() => { this.showFab = true; }, 280);
                } else {
                    this.showFab = false;
                }
            },
            scrollToSelected() {
                // console.log('scrollToSelected');                
                this.$nextTick(() => {
                    const wrapper = this.$refs.table;//.querySelector('.scrolled-wrapper');
                    const activeRow = wrapper.querySelector('.table-row.selected');

                    if (activeRow) {
                        const scrollTarget = activeRow.querySelector(':scope > *') || activeRow;
                        if (scrollTarget) {
                            scrollTarget.scrollIntoView({
                                behavior: 'smooth', // РџР»Р°РІРЅР°СЏ РїСЂРѕРєСЂСѓС‚РєР°
                                block: 'center', // Р¦РµРЅС‚СЂРёСЂРѕРІР°С‚СЊ СЃС‚СЂРѕРєСѓ
                            });
                        }
                    }
                });
            },

            setFiltersDefault() {
				// console.log('setFiltersDefault', this.settings?.custom_tools);				
                // if ( this.settings?.custom_tools)
                //     this.settings.custom_tools.forEach((filter) => {
                //         // console.log('aa', filter);
                //         if ( filter.action)
                //             filter.action(this.filters, filter.default)
                //     })
            },

            addRow(external_defaults = {}) {
                let newRow = Object.assign({}, this.settings.defaults ? this.settings.defaults : {}, external_defaults)

                // Запис заводиться в підпорядкування виділеного рядка, без виділення —
                // у корінь. Явно передане підпорядкування (напр. з контекстного меню)
                // має пріоритет над виділенням. Таблиці, де підпорядкування означає не
                // гілку дерева, а звʼязок документів, вимикають це через add_to_selected.
                if ( this.has_slaves && this.settings.add_to_selected !== false && !(this.slave_key in external_defaults) )
                    newRow[this.slave_key] = this.currentRowId();

                this.addedRow = newRow;

                if (this.settings.add_to_begin)
                    this.in_data.unshift(newRow)
                else
                    this.in_data.push(newRow);

                // Інакше новий рядок опиниться всередині згорнутої гілки — візуально зникне.
                if ( this.has_slaves && newRow[this.slave_key] )
                    this.expandParent(newRow[this.slave_key]);

                this.selectRow(newRow)
                this.scrollToSelected()

                // Inline-режим: сторінка отримає рядок лише після підтвердження вводу.
                if ( this.inline_add_mode ) {
                    this.inline_add.row = newRow;
                    return newRow;
                }

                this.$emit('onAdd', newRow);

                return newRow;
            },
            // Id виділеного рядка або null, якщо виділення немає (чи рядок ще не збережений).
            currentRowId() {
                const id = this.currentRow ? this.currentRow[this.row_key] : null;

                return ( id != null && id !== '' ) ? id : null;
            },
            isInlineAddCell(row, column) {
                return this.inline_add.row === row && column.field === this.inline_add_field;
            },
            // Підтвердження inline-вводу: порожня назва не зберігається — сторінка
            // отримує рядок тільки із заповненим значенням.
            confirmInlineAdd() {
                // Table.confirmInlineAdd
                const row = this.inline_add.row;
                if ( !row )
                    return;

                const value = String(row[this.inline_add_field] ?? '').trim();
                if ( !value )
                    return;

                row[this.inline_add_field] = value;
                this.inline_add.row = null;

                this.$emit('onAdd', row);
            },
            // Скасування додавання (будь-який режим): незбережений рядок зникає з таблиці,
            // виділення знімається повністю — після відмови не лишається ні чернетки в
            // даних, ні підсвіченого рядка. Публічний метод: modal-режим викликає його зі
            // сторінки, коли картку закрили без збереження.
            cancelAdd() {
                // Table.cancelAdd
                const row = this.inline_add.row || this.addedRow;

                this.inline_add.row = null;
                this.addedRow = null;

                if ( row ) {
                    const index = this.in_data.indexOf(row);
                    if ( index >= 0 )
                        this.in_data.splice(index, 1);
                }

                this.unselectAll();

                this.$emit('onAddCancel', row || null);
            },
			getGroupButtonClasses(group_name)  {
				return `table-group-header col-span-${this.columnsCount} ${group_name ? 'table-cell' : ''}`
			},
			// Ключі розкритих груп — значення поля групування (не id рядків).
			getOpenedGroups() {
				return this.openedGroups
			},

			getTotalValue(key) {
				return this.totals[key] ? this.totals[key] : 0
			},
			toggleParent(row) {
				const id = row[this.row_key];

				if (this.isParentExpanded(row))
					this.markCollapsed(id);
				else
					this.markExpanded(id);
			},
			markExpanded(id) {
				if (this.slaves_expanded_by_default)
					this.collapsedParents.delete(id);
				else
					this.expandedParents.add(id);
			},
			markCollapsed(id) {
				if (this.slaves_expanded_by_default)
					this.collapsedParents.add(id);
				else
					this.expandedParents.delete(id);
			},
			// Public: force a parent row's slaves to be revealed. Accepts a row or its id.
			// Разом з ним розкривається весь ланцюжок вищих рівнів — інакше сам рядок
			// лишиться схованим усередині згорнутого підпорядкування над ним.
			expandParent(row) {
				const id = (row && typeof row === 'object') ? row[this.row_key] : row;
				if (id == null || id === '')
					return;

				this.markExpanded(id);
				this.expandAncestors(id);
			},
			// Розкриває всі рівні над рядком, щоб він став видимим у дереві.
			expandAncestors(id) {
				if (!this.has_slaves || id == null || id === '')
					return;

				let current = this.row_by_id[String(id)];
				const guard = new Set();

				while (current) {
					const pid = current[this.slave_key];
					if (!pid || pid == 0 || guard.has(String(pid)))
						return;

					guard.add(String(pid));

					const parent = this.row_by_id[String(pid)];
					if (!parent)
						return;

					this.markExpanded(parent[this.row_key]);
					current = parent;
				}
			},
			isParentExpanded(row) {
				const id = row[this.row_key];

				return this.slaves_expanded_by_default
					? !this.collapsedParents.has(id)
					: this.expandedParents.has(id);
			},
			getSlaveRows(row) {
				return this.slave_index[row[this.row_key]] || [];
			},
			// Рядок разом з усіма підпорядкованими на будь-яку глибину (для підсумків).
			collectWithSubordinates(row, acc, visited) {
				const id = row[this.row_key];
				const key = ( id != null && id !== '' ) ? String(id) : null;

				if ( key !== null ) {
					if ( visited.has(key) )
						return acc;

					visited.add(key);
				}

				acc.push(row);

				if ( this.has_slaves )
					this.getSlaveRows(row).forEach(child => this.collectWithSubordinates(child, acc, visited));

				return acc;
			},
			// Видимі рядки групи (пласке дерево); порожній масив для невідомої групи.
			visibleRowsOf(group_name) {
				return this.visible_rows[group_name] || [];
			},
			groupButtonClass(group_entry) {
				const group_name = group_entry.key;

				if ( !group_name )
					return 'table-group-header';

				let result = this.hasGroupTotals
					? 'table-group-header'
					: 'table-group-header table-cell';

				if ( this.draggable_groups )
					result += ' draggable-group';

				// Рядок зависає над шапкою чужої групи — підсвічуємо її як приймач
				// (над своєю ж групою переносити нікуди).
				if ( this.drag.active
					&& this.drag.candidateGroupKey == null
					&& this.drag.overGroupKey === group_name
					&& String(this.drag.candidateRow?.[this.groupBy] ?? '') !== group_name )
					result += ' drop-into-group';

				if ( this.drag.active && this.drag.candidateGroupKey === group_name )
					result += ' dragging-group';

				return result;
			},
			// Атрибути шапки групи: ключ для влучання при перетягуванні, захоплення
			// натискання і гасіння кліку, який лишився після перетягування (інакше
			// група ще й згорталася б).
			groupButtonAttrs(group_entry) {
				if ( !group_entry.key )
					return {};

				return {
					'data-group-key': group_entry.key,
					onMousedown:      (event) => this.onGroupMouseDown(group_entry.key, event),
					onClickCapture:   (event) => {
						if ( !this.drag.ignoreClick )
							return;

						event.stopImmediatePropagation();
						event.preventDefault();
					},
				};
			},
			getGroupTotal(group_name, field) {
				return this.group_totals_data[group_name]?.[field] ?? 0;
			},
			formatGroupTotalValue(column, value) {
				if ( value === null || value === undefined )
					return '';

				return this.formatColumnTotal(column, value);
			},
			// Форматує підсумок (sum) під precision колонки з налаштувань таблиці:
			// currency → 2 знаки + ₴; number → column.precision (типово 2);
			// інакше → column.precision (типово 0).
			// Спільний для нижніх totals і totals-рядка групи, щоб precision збігались.
			formatColumnTotal(column, value) {
				const num = Number(value);
				if ( Number.isNaN(num) )
					return value;

				if ( column?.type === 'currency' )
					return num.toFixed(2) + ' ₴';

				if ( column?.type === 'number' )
					return num.toFixed(column?.precision ?? 2) + (column?.suffix ?? '');

				return num.toFixed(column?.precision ?? 0);
			},
        }
    }
</script>


<style lang="scss" scoped>

	// Заголовок таблиці (h2, проп `header`) — стандартні вертикальні відступи, щоб
	// відділити його від панелі інструментів/шапки таблиці нижче. Типографіка — з h2.
	.table-title {
		padding-top: 0.5rem;
		padding-bottom: 0.75rem;
	}

	.table-sticky-header {
		// display: grid;
		// position: sticky;
		// top: 0;
		// z-index: 200;
		background-color: var(--page-background, var(--table-body-background));
	}

	.table-sticky-panel {
		background-color: var(--page-background, var(--table-body-background));
	}

    .show-deleted-checkbox {
        border-color: var(--form-control-border-color-focus);
    }

	// Floating overlay, fixed near the bottom and centered over the host
	// container. Stays put while the table/filters scroll underneath it.
	.selection-bar {
		position: absolute;
		bottom: 1.5rem;
		left: 50%;
		transform: translateX(-50%);
		z-index: 1000;

		display: flex;
		align-items: center;
		@include flex-gap(0.75rem);
		max-width: calc(100% - 2rem);
		padding: 0.625rem 1rem;
		border-radius: 0.75rem;

		// Токен-фолбэк перед смесью: без него на Apple ниже 16.2 панель массовых
		// действий остаётся прозрачной поверх таблицы и текст в ней нечитаем.
		background-color: var(--table-header-background-80);
		background-color: color-mix(in srgb, var(--table-header-background) 80%, transparent);
		backdrop-filter: blur(8px);
		box-shadow: 0 10px 30px -6px rgb(0 0 0 / 0.35);

		@media (max-width: 768px) {
			// Полное занятое место нижнего бара — с безопасным отступом под индикатором жеста.
			bottom: calc(1rem + var(--bottom-tab-bar-total, var(--bottom-tab-bar-height, 0px)));
		}
	}

	.selection-bar-label {
		font-weight: 600;
		color: var(--table-color);
	}

	// Pin the floating action (scroll-to-top FAB or CTA button) to the viewport's
	// bottom-right corner. `fixed` (not the FabButton default `absolute`) is
	// required because in sticky_header mode #table grows taller than the screen —
	// an absolutely-positioned child would sit at the bottom of that tall content,
	// off-screen.
	.table-scroll-fab {
		position: fixed;
		bottom: 24px;
		right: 24px;
		z-index: 1000;

		// Semi-transparent at rest so it doesn't dominate the scrolled content;
		// becomes fully opaque on hover/focus when the user reaches for it.
		opacity: 0.6;
		transition: opacity 0.2s ease;

		&:hover,
		&:focus-within {
			opacity: 1;
		}
	}

	// Таблиця з власною прокруткою тіла не виходить за відведену їй область, тож
	// кнопка живе в її куті, а не в куті екрана: інакше дві сусідні таблиці кладуть
	// свої кнопки одну на одну.
	#table.y-scroll .table-scroll-fab {
		position: absolute;
	}

	// The FAB inside the wrapper flows naturally — the wrapper owns the placement,
	// so neutralize FabButton's own absolute positioning.
	.table-scroll-fab ::v-deep(.fab-button) {
		position: static;
		bottom: auto;
		right: auto;
	}

	// Lift the action above the bottom tab bar on mobile so it stays reachable
	// in the bottom-right corner (matches the selection-bar offset).
	@media (max-width: 768px) {
		.table-scroll-fab {
			bottom: calc(1rem + var(--bottom-tab-bar-height, 0px));
			right: 16px;
		}
	}

	// Fade + slide transition for the FAB appearing/disappearing on scroll.
	.fab-fade-enter-active,
	.fab-fade-leave-active {
		transition: opacity 0.2s ease, transform 0.2s ease;
	}

	.fab-fade-enter-from,
	.fab-fade-leave-to {
		opacity: 0;
		transform: translateY(8px) scale(0.9);
	}

    .t-body {
        padding-bottom: 1rem;
    }

	#table {
		display: flex;
		flex-direction: column;
		min-height: 0;
		position: relative;
	}

	.table-spacer {
		background-color: var(--table-body-background);
		min-height: 0;
		pointer-events: none;
	}

	::v-deep(.table-group-header) {
		color: var(--table-color);
		background-color: var(--table-group-background);
	}

	// In accordion grouping mode a group header sits directly under the table header,
	// so drop the header's bottom corner rounding — it should read as continuous.
	.is-grouped ::v-deep(.header-cell:first-child) {
		border-bottom-left-radius: 0;
	}

	.is-grouped ::v-deep(.header-cell:last-child) {
		border-bottom-right-radius: 0;
	}

	::v-deep(.group-name-cell) {
		padding-left: 2rem !important;
	}

	::v-deep(.table-group-header.table-cell) {
		padding-right: 1rem;
	}

	// М'яко видалений запис довідника груп читається так само, як видалений рядок.
	::v-deep(.table-group-header.is-deleted) {
		color: var(--error-color);
	}

	::v-deep(.table-group-header.draggable-group) {
		cursor: grab;
	}

	::v-deep(.table-group-header.dragging-group) {
		opacity: 0.35;
	}

	// Шапка-приймач: сигнал «рядок перейде в цю групу» — тому підсвічується вся шапка,
	// а не лінія між групами.
	::v-deep(.table-group-header.drop-into-group) {
		background-color: var(--drop-into-row-background);
	}

	::v-deep(.group-actions) {
		display: flex;
		align-items: center;
		// Відступ задано міксином: розкладка приходить із цього класу, утилітарного
		// класу розкладки на елементі немає і фолбек збірника до нього не дістає.
		@include flex-gap(0.75rem);
		padding-left: 0.75rem;
	}

	::v-deep(.table-group) {
	}

    ::v-deep(.table-cell) {
        display: flex;
        align-items: center;
        vertical-align: middle;
        font-weight: 400;
        white-space: nowrap;

        overflow: hidden;
		// padding-right: 0.75rem;
		// padding-left: 0.75rem;
        @apply
			px-1.5
			md:px-2
			2xl:px-3
            relative
            cursor-pointer;
    }

    ::v-deep(.table-cell::-webkit-scrollbar) {
        display: none;
    }

	// Extra left padding only when NOT in parent/slave mode; with slaves the
	// first cell is the toggle/indent column and keeps the standard cell padding.
	// Gate on #table (the root) — the inner TableWrapper also carries class .table.
	#table:not(.has-slaves) ::v-deep(.header-cell:first-child),
	#table:not(.has-slaves) ::v-deep(.table-cell:first-child) {
		// border:1px solid green;
		@apply
			ps-4
			md:ps-5
			2xl:ps-6;
	}

	#table:not(.has-slaves) ::v-deep(.header-cell:last-child),
	#table:not(.has-slaves) ::v-deep(.table-cell:last-child) {
		// border: 1px solid green;
		@apply
			pe-4
			md:pe-5
			2xl:pe-6;
	}

	// Indicator / rowbar cells hug their content: drop the standard cell padding so
	// the column track collapses to the icon/dot size. The table-edge side is
	// excluded (first cell's left, last cell's right) so a leading indicator or a
	// trailing rowbar still carries the row's edge air (var(--table-edge-inset))
	// instead of being glued to the table border.
	// ::v-deep(.table-cell.slave-toggle-cell) {
	// 	// border: 1px solid red;
	// 	@apply
	// 		ps-0;
	// }


	// ::v-deep(.table-cell.indicator-cell:not(:first-child)),
	// ::v-deep(.table-cell.rowbar-cell:not(:first-child)) {
	// 	padding-left: 0;
	// }
	// ::v-deep(.table-cell.indicator-cell:not(:last-child)),
	// ::v-deep(.table-cell.rowbar-cell:not(:last-child)) {
	// 	padding-right: 0;
	// }

	// Header cell of the rowbar column (hosts the "Show deleted" toggle): center the
	// toggle over the column. The last-child right inset is kept so the header
	// background spans the full edge air like every other last cell.
	::v-deep(.header-cell.rowbar-cell),
	::v-deep(.header-cell.rowbar-cell:last-child) {
		justify-content: center;
	}

	// On touch screens long-pressing a row opens the context menu — keep that
	// gesture from selecting cell text (and suppress the iOS callout).
	@media (max-width: 768px) {
		::v-deep(.table-cell) {
			-webkit-user-select: none;
			user-select: none;
			-webkit-touch-callout: none;
		}

		// Editable cells keep their inputs selectable/editable.
		::v-deep(.table-cell input),
		::v-deep(.table-cell textarea) {
			-webkit-user-select: text;
			user-select: text;
		}
	}

    .table-row .table-cell {
        background: var(--table-body-background);
    }

    .table-row:not(.table-header) .table-cell {              
    }
    .table-row:hover .table-cell {background-color: var(green)}
    .table-row.selected .table-cell {background-color: var(--table-selection-color)}
    .table-row.selected > :first-child {
        border-top-left-radius: var(--table-border-radius, 0.35rem);
        border-bottom-left-radius: var(--table-border-radius, 0.35rem);
    }
    .table-row.selected > :last-child {
        border-top-right-radius: var(--table-border-radius, 0.35rem);
        border-bottom-right-radius: var(--table-border-radius, 0.35rem);
    }

	// Generic accent row — colored left bar (opt-in via settings.row_class).
	// Рядок має display:contents, тож акцент малюємо на першій клітинці.
	.table-row.accent-row .table-cell:first-child {
		box-shadow: inset 3px 0 0 0 var(--table-accent-row, #3b82f6);
	}

    .hidden {
        padding:0px;
    }
    @media (max-width: 640px) {
        .sm-hidden {
            padding: 0px;
            border: 0px;
        }
    } 
    @media (max-width: 768px) {
        .md-hidden {
            padding: 0px;
            border: 0px;
        }
    } 
    @media (max-width: 1024px) {
        .lg-hidden {
            padding: 0px;
            border: 0px;
        }
    } 
    @media (max-width: 1280px) {
        .xl-hidden {
            padding: 0px;
            border: 0px;
        }
    } 
    @media (max-width: 1536px) {
        .xxl-hidden {
            padding: 0px;
            border: 0px;
        }
    } 

    .table-md .footer-cell {
        display: flex;
        align-items: center;
        font-weight: bold;
        font-size: var(--text-md)!important;
    }

    .is-deleted {
        color: var(--error-color);
    }

    .rowbar {
        height: 100%;
        display: flex;
        flex: 0 1 auto;
        align-items: center;    
    }

	.footer-cell {
	}

	::v-deep(.draggable-row .table-cell) {
		cursor: grab;
	}

	::v-deep(.dragging-row > *) {
		opacity: 0.35;
	}

	// Рядок-приймач при вкладенні: підсвічується весь рядок (а не лінія між рядками) —
	// сигнал «стане вкладеним сюди». Рядок — display:contents, тож фарбуємо клітинки.
	// Селектор навмисно важчий за звичайний фон клітинки й за фон виділеного рядка:
	// інакше підсвічення програє їм за вагою і не видно взагалі.
	#table ::v-deep(.table-row.drop-into-row > *),
	#table ::v-deep(.table-row.drop-into-row .table-cell) {
		background-color: var(--drop-into-row-background);
		background-color: color-mix(in srgb, var(--primary-button-bg, #3b82f6) 22%, transparent);
	}

	// Ліве поле клітинки зростає з рівнем вкладеності (рівень приходить у рядку),
	// тому кожен наступний рівень підпорядкування зсувається праворуч. Вирівнювання
	// по лівому краю обовʼязкове: колонка спільна для всіх рівнів, і центрування
	// зʼїло б різницю відступів. Ширина колонки — min-content, тож вона тягнеться
	// під найглибший розкритий рядок.
	//
	// Крок вкладеності дорівнює ширині tree-маркера (нижче), тому шеврон підлеглого
	// рядка, що сам є батьком, завжди виявляється точно під tree-маркером свого нащадка.
	$slave-tree-step: 0.85rem;

	::v-deep(.slave-toggle-cell) {
		justify-content: flex-start;
		padding-left: calc(0.5rem + var(--slave-depth, 0) * #{$slave-tree-step});
		padding-right: 0;
		cursor: default !important;
	}

	::v-deep(.slave-toggle-btn) {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 1.25rem;
		height: 1.25rem;
		border-radius: 0.25rem;
		background: transparent;
		color: var(--table-color);
		cursor: pointer;
		flex-shrink: 0;
	}

	// Dropdown-style chevron: points right when collapsed, rotates down when expanded.
	::v-deep(.slave-toggle-chevron) {
		transform: rotate(-90deg);
		transition: transform 0.2s ease;
	}

	::v-deep(.slave-toggle-btn.is-expanded .slave-toggle-chevron) {
		transform: rotate(0deg);
	}

	// Pseudo tree-indent marker (├ / └) for subordinate rows. Фіксована ширина
	// (=крок вкладеності) — щоб шеврон наступного рівня завжди починався точно
	// під ним, а не «плавав» за шириною символу шрифту. На нульовому рівні рядок
	// без вмісту (порожня розпірка) — той самий зсув перед шевроном root-рядка.
	::v-deep(.slave-tree-marker) {
		display: flex;
		align-items: center;
		justify-content: center;
		width: $slave-tree-step;
		flex-shrink: 0;
		font-family: monospace;
		font-size: 1.5rem;
		line-height: 1;
		opacity: 0.5;
		color: var(--table-slave-accent);
	}

	// На виділеному рядку фон клітинки — table-selection-color; напівпрозорий
	// приглушений маркер на ньому майже зникає, тому робимо його кольору .disabled.
	::v-deep(.table-row.selected .slave-tree-marker) {
		opacity: 0.5;
		color: var(--text-color-disabled);
	}

	// Parent of currently expanded subordinate rows
	::v-deep(.is-parent-expanded:not(.selected) .table-cell) {
		background-color: var(--table-parent-expanded-background);
		font-weight: 500;
	}

	// Subordinate (slave) rows — text slightly muted vs. parent rows
	::v-deep(.slave-row .table-cell) {
		background: var(--table-slave-background);
		color: var(--table-slave-color);
	}

	::v-deep(.slave-row.selected .table-cell) {
		background-color: var(--table-selection-color);
	}

</style>
