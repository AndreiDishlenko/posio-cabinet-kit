<template>

	<div class="card">
		<h3 class="card-header">{{ $t ? $t('Account') : 'Account' }}</h3>

		<div class="form-group">
			<span class="form-label">{{ $t ? $t('Name') : 'Name' }}</span>
			<span>{{ account?.name }}</span>
		</div>

		<div class="form-group">
			<span class="form-label">{{ $t ? $t('Expire') : 'Expire' }}</span>
			<span>{{ account?.expire || '—' }}</span>
		</div>
	</div>

</template>

<script>
	export default {
		name: 'AccountTab',
		props: {
			account: {
				type: Object,
				default: null,
			},
		},
	}
</script>

<style lang="scss" scoped>
    
</style>
