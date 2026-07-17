<template>

	<div class="ak-card">
		<h3 class="ak-card-title">{{ $t ? $t('Account') : 'Account' }}</h3>

		<div class="ak-field-row">
			<span class="ak-field-label">{{ $t ? $t('Name') : 'Name' }}</span>
			<span>{{ account?.name }}</span>
		</div>

		<div class="ak-field-row">
			<span class="ak-field-label">{{ $t ? $t('Expire') : 'Expire' }}</span>
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
