<template>

	<CabinetLayout :space_y="2">

		<BrandSettings scope="cabinet"/>

	</CabinetLayout>

</template>

<script>
	import CabinetLayout from '@/_admin/js/layouts/CabinetLayout.vue';
	import BrandSettings from '@/_admin/js/pages/SiteSettings/BrandSettings.vue';

	export default {
		name: 'CabinetBrandSettings',
		components: { CabinetLayout, BrandSettings },
	}
</script>

<style lang="scss" scoped>
</style>
