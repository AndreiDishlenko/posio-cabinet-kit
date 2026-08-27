<template>

	<span class="group-actions" @click.stop>
		<Icon v-for="action in actions"
			:key="action.event"
			class="icon icon-md cursor-pointer"
			:class="colorOf(action)"
			:icon="action.icon"
			:title="action.tooltip ? $t(action.tooltip) : ''"
			@click.stop.prevent="$emit('action', action)"
			/>
	</span>

</template>

<script>
	import { Icon } from '@iconify/vue';

	import { rowActionColorClass } from '@/js/Elements/Table/rowActions.js';

	// Дії над самою групою (напр. керування категорією товарів). Винесено окремо,
	// бо малюються у двох місцях: усередині назви групи й у колонці рядкових дій,
	// коли таблиця її має — там вони стають на одну вертикаль з іконками рядків.
	export default {
		name: 'TableGroupActions',
		components: { Icon },
		emits: ['action'],
		props: {
			actions: {
				type: Array,
				default: () => [],
			},
			// Запис групи — з нього дія бере колір (напр. відновлення видаленої категорії).
			item: {
				type: Object,
				default: () => ({}),
			},
		},
		methods: {
			colorOf(action) {
				return rowActionColorClass(action, this.item || {});
			},
		},
	}
</script>
