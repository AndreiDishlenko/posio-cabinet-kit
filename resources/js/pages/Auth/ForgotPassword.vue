<template>

	<AuthLayout page_name="Forgot password">

		<p v-if="sent" class="ck-status">{{ $t ? $t('If that email exists, a reset link has been sent.') : 'If that email exists, a reset link has been sent.' }}</p>

		<form @submit.prevent="submit">
			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Email') : 'Email' }}</label>
				<input type="email" class="ck-input" v-model="form.email" :class="{ 'is-invalid': errors.email }" autofocus>
				<span v-if="errors.email" class="ck-error">{{ errors.email }}</span>
			</div>

			<button type="submit" class="button primary-button w-full" :disabled="submitting">
				{{ $t ? $t('Send reset link') : 'Send reset link' }}
			</button>

			<div class="ck-auth-links">
				<Link :href="route('login')">{{ $t ? $t('Back to login') : 'Back to login' }}</Link>
			</div>
		</form>

	</AuthLayout>

</template>

<script>
	import { Link, router } from '@inertiajs/vue3';

	import AuthLayout from '../../layouts/AuthLayout.vue';

	export default {
		name: 'ForgotPassword',
		components: { AuthLayout, Link },
		data() {
			return {
				form: {
					email: '',
				},
				errors: {},
				submitting: false,
				sent: false,
			}
		},
		methods: {
			validateForm() {
				this.errors = {};

				if (!/^\S+@\S+\.\S+$/.test(this.form.email))
					this.errors.email = this.$t ? this.$t('Enter a valid email') : 'Enter a valid email';

				return Object.keys(this.errors).length === 0;
			},
			submit() {
				if (!this.validateForm())
					return;

				this.submitting = true;

				router.post(route('password.email'), this.form, {
					onSuccess: () => { this.sent = true; },
					onError: (errors) => { this.errors = errors; },
					onFinish: () => { this.submitting = false; },
				});
			},
		},
	}
</script>

<style lang="scss" scoped>
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
