<template>

	<div class="ak-card">
		<h3 class="ak-card-title">{{ $t ? $t('Profile') : 'Profile' }}</h3>

		<div class="ak-field-row">
			<span class="ak-field-label">{{ $t ? $t('Name') : 'Name' }}</span>
			<span>{{ $page.props.user?.name }}</span>
		</div>

		<div class="ak-field-row">
			<span class="ak-field-label">{{ $t ? $t('Email') : 'Email' }}</span>
			<span>{{ $page.props.user?.email }}</span>
		</div>

		<p class="ak-hint">
			{{ $t ? $t('Password and profile fields are managed by your host app\'s own auth — extend this tab in resources/_admin/overrides/pages/Settings/ProfileTab.vue.') : 'Password and profile fields are managed by your host app\'s own auth — extend this tab in resources/_admin/overrides/pages/Settings/ProfileTab.vue.' }}
		</p>
	</div>

</template>

<script>
	export default {
		name: 'ProfileTab',
	}
</script>

<style lang="scss" scoped>
	.ak-hint {
		font-size: .8rem;
		opacity: .55;
		margin-top: 1rem;
	}
</style>
