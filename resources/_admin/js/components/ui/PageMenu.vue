<template lang="">

	<Dropdown v-if="items.length" ref="dropdown"
		:align		= "'right'"
		:downOnClick	= "true"
		:transition	= "'menu'"
		:area_radius	= "'var(--ui-radius-md, 0.625rem)'"
		:buttonclass	= "'flex'"
		:offset		= "10"
		>
		<template #button>
			<span class="flex items-center text-secondary px-1 button button-md !w-9">
				<Icon icon="mdi:dots-vertical" class="icon icon-md" />
			</span>
		</template>
		<template #dropdownitems>
			<SelectableItems class="py-1"
				:in_data	= "items"
				:text_field	= "'name'"
				:items_class	= "'rounded-md'"
				:keyboard	= "true"
				@selectItem	= "onSelect"
				@close		= "$refs.dropdown.close()"
				/>
		</template>
	</Dropdown>

</template>

<script>
	import { Icon }			from '@iconify/vue';

	import Dropdown			from '@/js/Elements/Dropdown.vue';
	import SelectableItems	from '@/js/Elements/Forms/SelectableItems.vue';

	export default {
		name: 'PageMenu',
		components: { Icon, Dropdown, SelectableItems },
		props: {
			// Пункти меню сторінки: { name, icon?, action }.
			items: {
				type: Array,
				default: () => [],
			},
		},
		methods: {
			onSelect(e, item) {
				this.$refs.dropdown?.close();
				if ( typeof item.action === 'function' )
					item.action(e);
			},
		},
	}
</script>

<style lang="scss" scoped>

</style>
