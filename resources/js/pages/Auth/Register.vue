<template>

	<AuthLayout page_name="Create an account">

		<form @submit.prevent="submit">
			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Name') : 'Name' }}</label>
				<input type="text" class="ck-input" v-model="form.name" :class="{ 'is-invalid': errors.name }" autofocus>
				<span v-if="errors.name" class="ck-error">{{ errors.name }}</span>
			</div>

			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Company name') : 'Company name' }}</label>
				<input type="text" class="ck-input" v-model="form.company_name" :class="{ 'is-invalid': errors.company_name }">
				<span v-if="errors.company_name" class="ck-error">{{ errors.company_name }}</span>
			</div>

			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Email') : 'Email' }}</label>
				<input type="email" class="ck-input" v-model="form.email" :class="{ 'is-invalid': errors.email }">
				<span v-if="errors.email" class="ck-error">{{ errors.email }}</span>
			</div>

			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Password') : 'Password' }}</label>
				<input type="password" class="ck-input" v-model="form.password" :class="{ 'is-invalid': errors.password }">
				<span v-if="errors.password" class="ck-error">{{ errors.password }}</span>
			</div>

			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Confirm password') : 'Confirm password' }}</label>
				<input type="password" class="ck-input" v-model="form.password_confirmation" :class="{ 'is-invalid': errors.password_confirmation }">
				<span v-if="errors.password_confirmation" class="ck-error">{{ errors.password_confirmation }}</span>
			</div>

			<button type="submit" class="button primary-button w-full" :disabled="submitting">
				{{ $t ? $t('Create account') : 'Create account' }}
			</button>

			<div class="ck-auth-links">
				<Link :href="route('login')">{{ $t ? $t('Already have an account? Log in') : 'Already have an account? Log in' }}</Link>
			</div>
		</form>

	</AuthLayout>

</template>

<script>
	import { Link, router } from '@inertiajs/vue3';

	import AuthLayout from '../../layouts/AuthLayout.vue';

	export default {
		name: 'Register',
		components: { AuthLayout, Link },
		data() {
			return {
				form: {
					name: '',
					company_name: '',
					email: '',
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

				if (!this.form.name.trim())
					this.errors.name = this.$t ? this.$t('This field is required') : 'This field is required';

				if (!this.form.company_name.trim())
					this.errors.company_name = this.$t ? this.$t('This field is required') : 'This field is required';

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

				router.post(route('register'), this.form, {
					onError: (errors) => { this.errors = errors; },
					onFinish: () => { this.submitting = false; },
				});
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-auth-links {
		display: flex;
		justify-content: center;
		font-size: .8rem;
		margin-top: 1rem;
	}
</style>
