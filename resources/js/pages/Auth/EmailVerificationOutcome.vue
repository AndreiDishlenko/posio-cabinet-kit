<template>
	<AuthLayout title="Email Verification">

		<div class="email-verification-outcome card-body text-center space-y-3">
			<div class="text-secondary">
				{{ $t(outcome_message_key, { verified_email, current_email }) }}
			</div>
		</div>

		<div class="card-footer">
			<div class="email-verification-actions w-full v-flex space-y-3">
				<!-- Полная загрузка страницы: кабинет рендерится в собственном корневом шаблоне. -->
				<a :href="home_url" class="w-full button primary-button button-lg text-md">
					{{ $t('Continue as {email}', { email: current_email }) }}
				</a>
				<!-- Выход ведёт на страницу входа с уже подставленной почтой; после входа подтверждение завершится. -->
				<!-- Маршрут хоста на GET уходит полной загрузкой: после выхода у страницы новый токен защиты форм. -->
				<a v-if="switch_account_method === 'get'" :href="route('verification.switch-account')" class="w-full button outline-button button-lg text-md">
					{{ $t('Sign in as {email}', { email: verified_email }) }}
				</a>
				<Link v-else as="button" method="post" :href="route('verification.switch-account')" class="w-full button outline-button button-lg text-md">
					{{ $t('Sign in as {email}', { email: verified_email }) }}
				</Link>
			</div>
		</div>

	</AuthLayout>
</template>

<script>
	import { Link } from '@inertiajs/vue3';

	import AuthLayout from '../../layouts/AuthLayout.vue';
	import { kitFrontendOption } from '../../kitRoutes.js';

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
		computed: {
			switch_account_method() {
				return kitFrontendOption(this.$page, 'switch_account_method', 'post');
			},
			outcome_message_key() {
				return this.outcome === 'email-already-verified'
					? 'email-already-verified-signed-in-as-other'
					: 'email-verification-sign-in-required-signed-in-as-other';
			},
		},
	}
</script>
