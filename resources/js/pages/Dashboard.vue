<template>

	<AdminLayout page_name="Dashboard">

		<div class="ak-card">
			<h3 class="ak-card-title">{{ $t ? $t('Welcome') : 'Welcome' }}{{ account ? ' — ' + account.name : '' }}</h3>
			<p class="ak-hint">
				This is the AdminKit placeholder dashboard. Override it at
				resources/_admin/overrides/pages/Dashboard.vue, or point
				config('admin-kit.menu') dashboard entry at your own route.
			</p>
		</div>

	</AdminLayout>

</template>

<script>
	import AdminLayout from '../layouts/AdminLayout.vue';

	export default {
		name: 'Dashboard',
		components: { AdminLayout },
		props: {
			account: {
				type: Object,
				default: null,
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ak-hint {
		font-size: .85rem;
		opacity: .6;
		margin-top: .5rem;
	}
</style>
