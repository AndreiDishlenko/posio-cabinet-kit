<template>

	<div class="ck-table-wrapper">

		<table class="ck-table">
			<thead>
				<tr>
					<th v-if="selectable" class="ck-th-select"></th>
					<th v-for="column in columns" :key="column.key"
						:class="{ 'is-sortable': column.sortable }"
						@click="column.sortable && toggleSort(column.key)"
						>
						{{ $t ? $t(column.label) : column.label }}
						<Icon v-if="column.sortable && sort_key === column.key"
							:icon="sort_dir === 'asc' ? 'mdi:arrow-up' : 'mdi:arrow-down'"
							class="ck-icon-sm"/>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="row in sortedRows" :key="row[row_key]"
					class="ck-row"
					:class="{ 'is-selected': isSelected(row) }"
					@click="selectRow(row)"
					>
					<td v-if="selectable" class="ck-td-select">
						<input type="checkbox" :checked="isSelected(row)" @click.stop="selectRow(row)"/>
					</td>
					<td v-for="column in columns" :key="column.key">
						<slot :name="`cell-${column.key}`" :row="row">
							{{ column.format ? column.format(row[column.key], row) : row[column.key] }}
						</slot>
					</td>
				</tr>
				<tr v-if="!rows.length">
					<td :colspan="columns.length + (selectable ? 1 : 0)" class="ck-empty">
						{{ $t ? $t('No data') : 'No data' }}
					</td>
				</tr>
			</tbody>
		</table>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'Table',
		components: { Icon },
		props: {
			// [{ key, label, sortable, format(value, row) }]
			columns: {
				type: Array,
				required: true,
			},
			rows: {
				type: Array,
				default: () => [],
			},
			row_key: {
				type: String,
				default: 'id',
			},
			selectable: {
				type: Boolean,
				default: false,
			},
			multiple: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['onSelect'],
		data() {
			return {
				sort_key: null,
				sort_dir: 'asc',
				selected_keys: [],
			}
		},
		computed: {
			sortedRows() {
				if (!this.sort_key) return this.rows;

				const dir = this.sort_dir === 'asc' ? 1 : -1;
				return [...this.rows].sort((a, b) => {
					if (a[this.sort_key] === b[this.sort_key]) return 0;
					return a[this.sort_key] > b[this.sort_key] ? dir : -dir;
				});
			},
		},
		methods: {
			toggleSort(key) {
				if (this.sort_key !== key) {
					this.sort_key = key;
					this.sort_dir = 'asc';
					return;
				}
				this.sort_dir = this.sort_dir === 'asc' ? 'desc' : 'asc';
			},
			isSelected(row) {
				return this.selected_keys.includes(row[this.row_key]);
			},
			selectRow(row) {
				if (!this.selectable) {
					this.$emit('onSelect', row);
					return;
				}

				const key = row[this.row_key];
				if (this.multiple) {
					this.selected_keys = this.isSelected(row)
						? this.selected_keys.filter(k => k !== key)
						: [...this.selected_keys, key];
				} else {
					this.selected_keys = this.isSelected(row) ? [] : [key];
				}

				this.$emit('onSelect', this.multiple
					? this.rows.filter(r => this.selected_keys.includes(r[this.row_key]))
					: row);
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-table {
		width: 100%;
		border-collapse: collapse;

		th {
			text-align: start;
			font-size: .8rem;
			font-weight: 600;
			padding: .5rem .6rem;
			background: var(--ck-table-header-bg, #f6f7f9);
			border-bottom: 1px solid var(--ck-table-border-color, #e5e7eb);

			&.is-sortable {
				cursor: pointer;
			}
		}

		td {
			padding: .5rem .6rem;
			border-bottom: 1px solid var(--ck-table-border-color, #ececef);
		}
	}

	.ck-row {
		cursor: pointer;

		&:hover {
			background: var(--ck-table-hover-bg, #f6f7f9);
		}

		&.is-selected {
			background: var(--ck-table-selection-bg, #eaf1fd);
		}
	}

	.ck-empty {
		text-align: center;
		opacity: .5;
		padding: 1.5rem;
	}
</style>
