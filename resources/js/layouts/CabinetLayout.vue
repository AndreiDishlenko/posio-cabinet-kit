<template>

	<Head :title="$t ? $t(page_name || 'Cabinet') : (page_name || 'Cabinet')"/>

	<div class="ck-page-wrapper flex flex-row h-full overflow-y-hidden">

		<SideMenu class="ck-page-menu max-h-[100dvh] h-[100dvh]"
			:in_data="$page.props.cabinetKitMenu"
			:current_id="current_id"
			:active_account_id="$page.props.account?.id"
			/>

		<div class="ck-page-layout relative grow min-w-0 flex flex-col">

			<CabinetHeader class="min-h-0 px-3 sm:px-5" :page_name="page_name">
				<template #header-actions>
					<slot name="header-actions"/>
				</template>
			</CabinetHeader>

			<div class="ck-page-content p-2 lg:p-4 flex flex-col overflow-hidden" :class="space_y_class">
				<div class="grow overflow-y-auto flex flex-col">
					<slot/>
				</div>
			</div>

		</div>

	</div>

</template>

<script>
	import { Head } from '@inertiajs/vue3';

	import CabinetHeader from './CabinetHeader.vue';
	import SideMenu from './SideMenu.vue';

	export default {
		name: 'CabinetLayout',
		inheritAttrs: false,
		components: { Head, CabinetHeader, SideMenu },
		props: {
			page_name: {
				type: String,
				default: '',
			},
			space_y: {
				type: Number,
				default: 5,
			},
		},
		computed: {
			current_id() {
				return this.$page.props.currentPage?.id ?? null;
			},
			// Static class map (not 'space-y-' + n) so Tailwind's content scanner
			// sees every literal class name and actually generates it.
			space_y_class() {
				const map = {
					0: 'space-y-0', 1: 'space-y-1', 2: 'space-y-2', 3: 'space-y-3',
					4: 'space-y-4', 5: 'space-y-5', 6: 'space-y-6', 8: 'space-y-8',
				};
				return map[this.space_y] || 'space-y-5';
			},
		},
	}
</script>

<style lang="scss">
	.ck-page-content {
		height: 100%;
	}
</style>
