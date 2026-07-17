<template>

	<CabinetLayout page_name="Dashboard">

		<div class="ck-card">
			<h3 class="ck-card-title">{{ $t ? $t('Welcome') : 'Welcome' }}{{ account ? ' — ' + account.name : '' }}</h3>
			<p class="ck-hint">
				This is the CabinetKit placeholder dashboard. Override it at
				resources/_admin/overrides/pages/Dashboard.vue, or point
				config('cabinet-kit.menu') dashboard entry at your own route.
			</p>
		</div>

	</CabinetLayout>

</template>

<script>
	import CabinetLayout from '../layouts/CabinetLayout.vue';

	export default {
		name: 'Dashboard',
		components: { CabinetLayout },
		props: {
			account: {
				type: Object,
				default: null,
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-hint {
		font-size: .85rem;
		opacity: .6;
		margin-top: .5rem;
	}
</style>
