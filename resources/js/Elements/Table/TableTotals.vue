<template>
	<div v-if="totals && Object.keys(totals).length" class="table-row contents">

		<!-- Selector placeholder (matches TableHeader / row order) -->
		<div v-if="selector" class="footer-cell table-cell brd"></div>

		<!-- Slave toggle placeholder -->
		<div v-if="slave_key" class="footer-cell table-cell"></div>

		<template v-for="column in seenColumns" :key="column.field">

			<!-- <div v-if="index==0" class="footer-cell table-cell !font-bold col-span-2">
				{{ $t('Total') }}:
			</div> -->
			<div class="footer-cell table-cell !font-bold"
				:class="column.align ? 'justify-' + column.align : ''"
				>
				<template v-if="isStaticTotal(column.field)">
					<Icon v-if="totals[column.field].icon"
						:icon="totals[column.field].icon"
						class="icon icon-sm mr-1 opacity-70"
						/>
					{{ totals[column.field].value }}
				</template>
				<template v-else>
					{{ totals[column.field] }}
				</template>
			</div>

		</template>

		<!-- Rowbar placeholder — only when the rowbar track is actually rendered
		     (host respects rowbar_mobile_only, so this is false on desktop). -->
		<div v-if="show_rowbar" class="footer-cell table-cell"></div>

	</div>
</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'TableTotals',
		components: { Icon },
		props: {
			seenColumns: {
				type: Array,
				required: true,
			},
			totals: {
				type: Object,
				default: () => ({}),
			},
			settings: {
				type: Object,
				default: () => ({}),
			},
			// Leading selection-checkbox column (matches grid track in TableWrapper).
			selector: {
				type: Boolean,
				default: false,
			},
			// Non-empty enables the leading subordinate-rows toggle column.
			slave_key: {
				type: String,
				default: '',
			},
			// Whether the trailing rowbar column/track is rendered (computed by host Table).
			show_rowbar: {
				type: Boolean,
				default: false,
			},
		},
		methods: {
			isStaticTotal(field) {
				const t = this.totals[field];
				return t && typeof t === 'object' && t.static;
			},
		},
	}
</script>

<style lang="scss" scoped>

	.footer-cell {
		position: sticky;
		bottom: 0;
		z-index: 100;
		border-top: 1px solid var(--table-header-border-color);
		background-color: var(--table-header-background);
	}

	.footer-cell:first-child {
		border-top-left-radius: var(--table-border-radius);
        border-bottom-left-radius: var(--table-border-radius);
	}

	.footer-cell:last-child {
        border-top-right-radius: var(--table-border-radius);
        border-bottom-right-radius: var(--table-border-radius);
	}

</style>
