<template lang="">
	<div class="header-cell text-nowrap "
		:field="column.field"
		:class="[
			items_class,
			isSortingAllowed && 'is-sortable',
			isLast && 'top-right',
			headerAlign ? 'justify-' + headerAlign : null,
			(column.type == 'checkbox' || column.type == 'checkicon') && '!justify-center',
			column.hide && column.hide + '-hidden',
			column.nowrap && 'whitespace-nowrap'
		]"
		:title="headerTitle"
		@click="onHeaderClick"
	>
		<div class="header-cell-content">
			<Icon v-if="column.icon" :icon="column.icon" class="icon" />
			<span v-else>{{ column.title ? $t(column.title) : '' }}</span>
			<!-- <Icon v-if="column.hint" icon="ph:info" class="icon icon-sm opacity-70" @click.stop/> -->
			<span v-if="showIndicator" class="inline-flex items-start font-bold leading-none text-slate-400 disabled">
				{{ sortSymbol }}
				<sup v-if="sortDirection">{{ sortPriority }}</sup>
			</span>
		</div>
	</div>
</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		components: { Icon },
		props: {
			column: {
				type: Object,
				required: true,
			},
			items_class: {
				type: String,
				default: '',
			},
			isLast: {
				type: Boolean,
				default: false,
			},
			sortRules: {
				type: Array,
				default: () => [],
			},
			sortEnabled: {
				type: Boolean,
				default: true,
			},
			// false → show the sort badge for indication only (no pointer, no click).
			sortInteractive: {
				type: Boolean,
				default: true,
			},
			// Таблично-широке вирівнювання заголовків (settings.header_align), що перекриває
			// вирівнювання даних колонки. Пер-колонковий column.header_align має вищий пріоритет.
			table_header_align: {
				type: String,
				default: '',
			},
		},
		emits: ['sort-click'],
		computed: {
			// Вирівнювання заголовка колонки: пер-колонковий override (column.header_align) →
			// таблично-широкий (table_header_align ← settings.header_align) → вирівнювання
			// даних колонки (column.align). Дозволяє шапці мати інше вирівнювання, ніж дані
			// (напр. числові колонки align:'end', а заголовки — по центру).
			headerAlign() {
				return this.column.header_align || this.table_header_align || this.column.align || '';
			},
			isSortable() {
				if ( !this.column?.field ) return false
				if ( this.column.field === 'id' ) return false
				return this.column.sortable !== false
			},
			isSortingAllowed() {
				if ( !this.sortEnabled ) return false
				if ( !this.sortInteractive ) return false
				return this.isSortable
			},
			// Show the badge for clickable columns (affordance) and for any column
			// that is actually sorted, even in read-only mode.
			showIndicator() {
				if ( this.isSortingAllowed ) return true
				return !!this.sortRule
			},
			// Match by field, and by title when a rule provides one — lets columns
			// that share a `field` be badged distinctly.
			ruleMatcher() {
				return rule => rule.field === this.column.field
					&& ( rule.title == null || rule.title === this.column.title )
			},
			sortRule() {
				return this.sortRules.find(this.ruleMatcher)
			},
			sortDirection() {
				return this.sortRule ? this.sortRule.direction : ''
			},
			sortPriority() {
				const idx = this.sortRules.findIndex(this.ruleMatcher)
				return idx >= 0 ? idx + 1 : 0
			},
			nextSortDirection() {
				if ( !this.sortDirection ) return 'asc'
				if ( this.sortDirection === 'asc' ) return 'desc'
				return 'reset'
			},
			sortSymbol() {
				if ( this.sortDirection === 'asc' ) return '↑'
				if ( this.sortDirection === 'desc' ) return '↓'
				return '⇅'
			},
			headerTitle() {
				const hint = this.column.hint ? this.$t(this.column.hint) : ''
				const sort = this.sortTooltip
				if ( hint && sort ) return `${hint}\n\n${sort}`
				return hint || sort
			},
			sortTooltip() {
				if ( !this.isSortingAllowed ) return ''

				if ( this.nextSortDirection === 'reset' ) return this.$t('Reset')

				if ( this.column.type === 'string' ) {
					if ( this.nextSortDirection === 'asc' ) return this.$t('Text: A→Z')
					return this.$t('Text: Z→A')
				}

				if ( this.column.type === 'date' || this.column.type === 'time' ) {
					if ( this.nextSortDirection === 'asc' ) return this.$t('Date: earliest first')
					return this.$t('Date: latest first')
				}

				if ( this.nextSortDirection === 'asc' ) return this.$t('Sort: smaller to larger')
				return this.$t('Sort: larger to smaller')
			},
		},
		methods: {
			onHeaderClick() {
				if ( !this.isSortingAllowed ) return
				this.$emit('sort-click', this.column)
			},
		},
	}
</script>

<style lang="scss" scoped>
	.header-cell-content {
		display: inline-flex;
		align-items: center;
		@include flex-gap(0.35rem);
	}
</style>
