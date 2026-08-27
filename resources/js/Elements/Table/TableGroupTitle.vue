<template>

	<div class="flex items-center min-w-0">
		<!-- Іконка групи (напр. категорії) — з поля джерела групування,
		     заданого settings.group_icon_field -->
		<Icon v-if="settings.group_icon_field && group_entry.key"
			:icon="group_entry.item?.[settings.group_icon_field] || settings.group_icon_default || 'mdi:shape-outline'"
			class="icon icon-sm me-1.5 shrink-0"
			/>
		<span class="min-w-0 truncate">{{ group_entry.title }}</span>
		<span class="grow"></span>
		<!-- Дії над групою поруч із назвою — коли таблиця не має колонки рядкових дій,
		     інакше вони живуть у ній (притиснуті до правого краю таблиці). -->
		<TableGroupActions v-if="actions.length"
			:actions="actions"
			:item="group_entry.item || {}"
			@action="(action) => $emit('action', action)"
			/>
	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	import TableGroupActions from '@/js/Elements/Table/TableGroupActions.vue';

	// Вміст шапки групи: іконка, назва й дії над групою. Винесено окремо, бо
	// шапка малюється двома способами — суцільним рядком на всю ширину і
	// клітинкою в сітці колонок (коли група показує власні значення чи підсумки).
	export default {
		name: 'TableGroupTitle',
		components: { Icon, TableGroupActions },
		emits: ['action'],
		props: {
			group_entry: {
				type: Object,
				required: true,
			},
			settings: {
				type: Object,
				default: () => ({}),
			},
			actions: {
				type: Array,
				default: () => [],
			},
		},
	}
</script>
