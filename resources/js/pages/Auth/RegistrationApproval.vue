<template>
	<AuthLayout title="Registration approval">

		<div class="registration-approval-outcome card-body text-center space-y-3">
			<div :class="outcome === 'registration-approval-link-invalid' ? 'text-error' : 'text-secondary'">
				{{ $t(outcome) }}
			</div>
			<div v-if="user_email" class="text-md">
				<span v-if="user_name">{{ user_name }} · </span>{{ user_email }}
			</div>
		</div>

		<div class="card-footer">
			<!-- Полная загрузка страницы: кабинет рендерится в собственном корневом шаблоне. -->
			<a :href="home_url" class="w-full button primary-button button-lg text-md">
				{{ $t('Go to cabinet') }}
			</a>
		</div>

	</AuthLayout>
</template>

<script>
	import AuthLayout from '../../layouts/AuthLayout.vue';

	export default {
		name: 'RegistrationApproval',
		components: { AuthLayout },
		props: {
			outcome: {
				type: String,
				default: '',
			},
			user_name: {
				type: String,
				default: '',
			},
			user_email: {
				type: String,
				default: '',
			},
			home_url: {
				type: String,
				default: '/',
			},
		},
	}
</script>
