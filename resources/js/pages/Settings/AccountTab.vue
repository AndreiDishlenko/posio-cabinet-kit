<template>

	<div class="ck-card">
		<h3 class="ck-card-title">{{ $t ? $t('Account') : 'Account' }}</h3>

		<div class="ck-field-row">
			<span class="ck-field-label">{{ $t ? $t('Name') : 'Name' }}</span>
			<span>{{ account?.name }}</span>
		</div>

		<div class="ck-field-row">
			<span class="ck-field-label">{{ $t ? $t('Expire') : 'Expire' }}</span>
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
