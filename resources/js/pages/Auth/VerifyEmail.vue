<template>

	<AuthLayout page_name="Verify your email">

		<p class="ck-verify-text">
			{{ $t ? $t('We sent a verification link to your email address. Click it to activate your account.') : 'We sent a verification link to your email address. Click it to activate your account.' }}
		</p>

		<p v-if="sent" class="ck-status">{{ $t ? $t('A new verification link has been sent.') : 'A new verification link has been sent.' }}</p>

		<button type="button" class="button primary-button w-full" :disabled="sending" @click="resend">
			{{ $t ? $t('Resend verification email') : 'Resend verification email' }}
		</button>

		<div class="ck-auth-links">
			<Link :href="route('logout')" method="post" as="button">{{ $t ? $t('Log out') : 'Log out' }}</Link>
		</div>

	</AuthLayout>

</template>

<script>
	import { Link, router } from '@inertiajs/vue3';

	import AuthLayout from '../../layouts/AuthLayout.vue';

	export default {
		name: 'VerifyEmail',
		components: { AuthLayout, Link },
		data() {
			return {
				sending: false,
				sent: false,
			}
		},
		methods: {
			resend() {
				this.sending = true;

				router.post(route('verification.send'), {}, {
					onSuccess: () => { this.sent = true; },
					onFinish: () => { this.sending = false; },
				});
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-verify-text {
		font-size: .85rem;
		opacity: .75;
		margin-bottom: 1rem;
	}

	.ck-status {
		font-size: .85rem;
		color: #16a34a;
		margin-bottom: 1rem;
	}

	.ck-auth-links {
		display: flex;
		justify-content: center;
		font-size: .8rem;
		margin-top: 1rem;
	}
</style>
