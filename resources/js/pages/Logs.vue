<template>
	<CabinetLayout page_name="Logs">
		<div class="ck-card">
			<h1 class="ck-card-title">{{ $t ? $t('Logs') : 'Logs' }}</h1>
			<p class="text-secondary">
				{{ $t ? $t('No log viewer is configured for this CabinetKit installation.') : 'No log viewer is configured for this CabinetKit installation.' }}
			</p>
		</div>
	</CabinetLayout>
</template>

<script>
	import CabinetLayout from '../layouts/CabinetLayout.vue';

	export default {
		name: 'Logs',
		components: { CabinetLayout },
	}
</script>
