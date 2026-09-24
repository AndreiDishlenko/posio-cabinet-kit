<template>
	<AuthLayout title="Email Verification">

		<div class="email-verification-outcome card-body text-center space-y-3">
			<div class="text-secondary">
				{{ $t(outcome === 'email-already-verified' ? 'email-already-verified-signed-in-as-other' : 'email-verified-signed-in-as-other', { verified_email, current_email }) }}
			</div>
		</div>

		<div class="card-footer">
			<div class="email-verification-actions w-full v-flex space-y-3">
				<!-- Полная загрузка страницы: кабинет рендерится в собственном корневом шаблоне. -->
				<a :href="home_url" class="w-full button primary-button button-lg text-md">
					{{ $t('Continue as {email}', { email: current_email }) }}
				</a>
				<!-- Выход ведёт на страницу входа — там входят под подтверждённой почтой. -->
				<Link as="button" method="post" :href="route('logout')" class="w-full button outline-button button-lg text-md">
					{{ $t('Sign in as {email}', { email: verified_email }) }}
				</Link>
			</div>
		</div>

	</AuthLayout>
</template>

<script>
	import { Link } from '@inertiajs/vue3';

	import AuthLayout from '../../layouts/AuthLayout.vue';

	export default {
		name: 'EmailVerificationOutcome',
		components: { Link, AuthLayout },
		props: {
			outcome: {
				type: String,
				default: '',
			},
			verified_email: {
				type: String,
				default: '',
			},
			current_email: {
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
