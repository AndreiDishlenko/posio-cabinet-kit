<template>

	<Head :title="$t ? $t(page_name || 'Admin') : (page_name || 'Admin')"/>

	<div class="ak-page-wrapper flex flex-row h-full overflow-y-hidden">

		<SideMenu class="ak-page-menu max-h-[100dvh] h-[100dvh]"
			:in_data="$page.props.adminKitMenu"
			:current_id="current_id"
			:active_account_id="$page.props.account?.id"
			/>

		<div class="ak-page-layout relative grow min-w-0 flex flex-col">

			<AdminHeader class="min-h-0 px-3 sm:px-5" :page_name="page_name">
				<template #header-actions>
					<slot name="header-actions"/>
				</template>
			</AdminHeader>

			<div class="ak-page-content p-2 lg:p-4 flex flex-col overflow-hidden" :class="['space-y-'+space_y]">
				<div class="grow overflow-y-auto flex flex-col">
					<slot/>
				</div>
			</div>

		</div>

	</div>

</template>

<script>
	import { Head } from '@inertiajs/vue3';

	import AdminHeader from './AdminHeader.vue';
	import SideMenu from './SideMenu.vue';

	export default {
		name: 'AdminLayout',
		inheritAttrs: false,
		components: { Head, AdminHeader, SideMenu },
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
		},
	}
</script>

<style lang="scss">
	.ak-page-content {
		height: 100%;
	}
</style>
