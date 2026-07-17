<template>

	<AuthLayout page_name="Reset password">

		<form @submit.prevent="submit">
			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Email') : 'Email' }}</label>
				<input type="email" class="ck-input" v-model="form.email" :class="{ 'is-invalid': errors.email }" autofocus>
				<span v-if="errors.email" class="ck-error">{{ errors.email }}</span>
			</div>

			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('New password') : 'New password' }}</label>
				<input type="password" class="ck-input" v-model="form.password" :class="{ 'is-invalid': errors.password }">
				<span v-if="errors.password" class="ck-error">{{ errors.password }}</span>
			</div>

			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Confirm new password') : 'Confirm new password' }}</label>
				<input type="password" class="ck-input" v-model="form.password_confirmation" :class="{ 'is-invalid': errors.password_confirmation }">
				<span v-if="errors.password_confirmation" class="ck-error">{{ errors.password_confirmation }}</span>
			</div>

			<button type="submit" class="button primary-button w-full" :disabled="submitting">
				{{ $t ? $t('Reset password') : 'Reset password' }}
			</button>
		</form>

	</AuthLayout>

</template>

<script>
	import { router } from '@inertiajs/vue3';

	import AuthLayout from '../../layouts/AuthLayout.vue';

	export default {
		name: 'ResetPassword',
		components: { AuthLayout },
		props: {
			token: {
				type: String,
				required: true,
			},
			email: {
				type: String,
				default: '',
			},
		},
		data() {
			return {
				form: {
					token: this.token,
					email: this.email,
					password: '',
					password_confirmation: '',
				},
				errors: {},
				submitting: false,
			}
		},
		methods: {
			validateForm() {
				this.errors = {};

				if (!/^\S+@\S+\.\S+$/.test(this.form.email))
					this.errors.email = this.$t ? this.$t('Enter a valid email') : 'Enter a valid email';

				if (!this.form.password || this.form.password.length < 8)
					this.errors.password = this.$t ? this.$t('Password must be at least 8 characters') : 'Password must be at least 8 characters';

				if (this.form.password !== this.form.password_confirmation)
					this.errors.password_confirmation = this.$t ? this.$t('Passwords do not match') : 'Passwords do not match';

				return Object.keys(this.errors).length === 0;
			},
			submit() {
				if (!this.validateForm())
					return;

				this.submitting = true;

				router.post(route('password.update'), this.form, {
					onError: (errors) => { this.errors = errors; },
					onFinish: () => { this.submitting = false; },
				});
			},
		},
	}
</script>
