<template lang="">

    <!-- Table Header -->

	<template v-if="!settings.hideHeader">

		<!-- Group Header (overheader) -->
		<div v-if="settings.groupHeader?.length && expandedGroupHeader.some(c => c !== null)" class="table-header contents over-header-row">

			<!-- Column index placeholder -->
			<div v-if="row_header" class="header-cell !h-full !self-stretch" :class="items_class">
				&nbsp;
			</div>

			<!-- Selector placeholder -->
			<div v-if="selector" class="header-cell">
				&nbsp;
			</div>

			<!-- Slave toggle placeholder -->
			<div v-if="slave_key" class="header-cell">
				&nbsp;
			</div>

			<!-- Group cells (positional, with col_span support; empty cells fill the rest) -->
			<template v-for="(cell, idx) in expandedGroupHeader" :key="`gh-${idx}`" class="">
				<div v-if="cell" class="header-cell text-center"
					:style="cell.col_span > 1 ? { gridColumn: `span ${cell.col_span}` } : {}"
					:class="[
						cell.align ? `justify-${cell.align}` : '!justify-center',
						cell.weight ? `font-${cell.weight}` : null
						]">
					{{ $t(cell.title) }}
				</div>
				<div v-else class="header-cell">
					&nbsp;
				</div>
			</template>

			<!-- Rowbar placeholder (grid track width is dictated by TableRowBar in data rows) -->
			<div v-if="show_rowbar" class="header-cell rowbar-cell">
			</div>

		</div>

		<!-- <template v-if="!settings.groupHeader"> -->

		<!-- Standart header -->
		<div class="table-header contents">

			<!-- Column index. Оформление угловой ячейки задаёт хозяин таблицы: у
			     редактируемой таблицы шапка без скруглений, поэтому класс ячеек
			     шапки должен доставаться и этой заглушке. -->
			<div v-if="row_header" class="header-cell !h-full !self-stretch" :class="items_class">
				&nbsp;
			</div>

			<!-- Selector (placeholder; selection is per-row in single mode) -->
			<div v-if="selector" class="header-cell v-center">
				&nbsp;
			</div>

			<!-- Slave toggle placeholder -->
			<div v-if="slave_key" class="header-cell">
				&nbsp;
			</div>

			<!-- Columns names -->
			<template v-for="(column, colIndex) in seenColumns">
				<TableHeaderCell
					v-if="column.field && column.field !== 'id'"
					:key="column.field"
					:column="column"
					:items_class="items_class"
					:isLast="colIndex === seenColumns.length - 1"
					:sortRules="sortRules"
					:sortEnabled="sortEnabled"
					:sortInteractive="sortInteractive"
					:table_header_align="settings.header_align || ''"
					@sort-click="$emit('sort-click', $event)"
				/>
			</template>

			<!-- Rowbar placeholder (grid track width is dictated by TableRowBar in data rows).
			     Hosts the "show deleted" toggle (no label) when the table has a deleted filter. -->
			<div v-if="show_rowbar" class="header-cell rowbar-cell">
				<!-- A deleted-filter table always keeps this column (show_rowbar is forced
				     true for it), so the toggle always lives here — regardless of
				     rowbar_mobile_only/rowbar_from, which only collapse the row-level
				     action icons (see Table.vue show_rowbar_icons / TableRowBar). -->
				<ShowDeletedToggle v-if="settings.filters?.deleted"
					v-model="panel_data.showDeleted"
					/>
			</div>

		</div>
		<!-- </template> -->
		<!-- <template v-else>
			<div class="table-row contents sticky ">
				<div v-for="cell in settings.groupHeader" class="header-cell"
					:class="[ 
						cell.row_span ? `row-span-${cell.row_span}` : null,
						cell.col_span ? `col-span-${cell.col_span}` : null,
						cell.align ? `justify-${cell.align}` : '!justify-center',
						cell.weight ? `font-${cell.weight}` : null
						]">
					{{ $t(cell.title) }}
				</div>
			</div>
		</template> -->

	</template>

</template>

<script>
	import TableHeaderCell from '@/js/Elements/Table/TableHeaderCell.vue'
	import ShowDeletedToggle from '@/js/Elements/Table/ShowDeletedToggle.vue'

    export default {
		components: { TableHeaderCell, ShowDeletedToggle },
        props: {
            settings: {
                type: Object,
                default: {}
            },
            seenColumns: {
                type: Array,
                default: []
            },
            selector: {
                type: Boolean,
                default: false
            },
            items_class: {
                type: String,
                default: ''
            },
            row_header: {
                type: Boolean,
                default: false
            },
			sortRules: {
				type: Array,
				default: () => []
            },
			sortEnabled: {
				type: Boolean,
				default: true
            },
			// When false, sort badges are shown for indication only (no pointer,
			// no click-to-sort) — used by Tabledit's load-time sort.
			sortInteractive: {
				type: Boolean,
				default: true
            },
			// Non-empty enables the subordinate-rows toggle column.
			slave_key: {
				type: String,
				default: ''
			},
			// Whether the rowbar column/track is rendered (computed by the host Table).
			show_rowbar: {
				type: Boolean,
				default: false
			},
			// Shared panel state (holds showDeleted) — the "show deleted" toggle in the
			// rowbar header mutates panel_data.showDeleted, same source the filter reads.
			panel_data: {
				type: Object,
				default: () => ({})
			}
        },
		emits: ['sort-click'],
		computed: {
			visibleDataColumns() {
				return this.seenColumns.filter(c => c.field && c.field != 'id')
			},
			expandedGroupHeader() {
				// console.log('TableHeader.expandedGroupHeader')

				const columns = this.visibleDataColumns
				const groupHeader = this.settings?.groupHeader || []

				// Map: column field → group descriptor that owns it
				const fieldToGroup = new Map()
				for (const group of groupHeader) {
					const fields = group.columns || []
					for (const field of fields) {
						fieldToGroup.set(field, group)
					}
				}

				const result = []
				let i = 0

				while (i < columns.length) {
					const group = fieldToGroup.get(columns[i].field)

					if (group) {
						// Consume consecutive columns that belong to the same group
						let span = 0
						while (
							i + span < columns.length
							&& fieldToGroup.get(columns[i + span].field) === group
						) {
							span++
						}
						result.push({ ...group, col_span: span })
						i += span
					}
					else {
						result.push(null)
						i++
					}
				}

				return result
			},
		},
		methods: {
		},
    }
</script>

<style lang="scss" scoped>

    .table-header {
    }

    // Верхній групуючий рядок (overheader) НЕ робимо sticky: інакше і він, і
    // основний рядок заголовків липнуть до top:0 й накладаються один на одного.
    // Тут він прокручується вгору над липким основним заголовком — без зсувів.
    .over-header-row .header-cell {
        position: relative;
        top: auto;
        z-index: 90;
    }

    .header-cell {
        position: sticky;
        top: 0px; //var(--tools-panel-height, 0px);
        z-index: 100;
		font-weight: var(--table-header-font-weight, bold);
		color: var(--table-header-text-color, inherit);
		border-bottom: 1px solid var(--table-header-divider-color, transparent);

        background-color: var(--table-header-background)!important;

        display:flex;
        align-items: center;
        cursor: default!important;
    }
	// .sort-indicator {
	// 	display: inline-flex;
	// 	align-items: flex-start;
	// 	font-weight: 700;
	// 	line-height: 1;
	// 	color: var(--text-color-disabled, #94a3b8);
	// }
	.header-cell.is-sortable {
		cursor: pointer!important;
	}
    
	.header-cell:first-child {
        border-top-left-radius: var(--table-border-radius);
        border-bottom-left-radius: var(--table-border-radius);        
    }

    .header-cell:last-child {
        border-top-right-radius: var(--table-border-radius);
        border-bottom-right-radius: var(--table-border-radius);
    }

    // Adjustable
    // .table-sm .header-cell {
    //     font-size: var(--text-sm)!important;
    //     height: 38px; 
    // }
    // .table-md .header-cell {
    //     font-size: var(--text-md)!important;
    //     height: 40px; 
    // }
    // .table-lg .header-cell {
    //     font-size: var(--text-lg)!important;
    //     height: 58px; 
    // }
</style>

