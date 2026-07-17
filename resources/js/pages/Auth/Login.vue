<template>

	<AuthLayout page_name="Log in">

		<form @submit.prevent="submit">
			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Email') : 'Email' }}</label>
				<input type="email" class="ck-input" v-model="form.email" :class="{ 'is-invalid': errors.email }" autofocus>
				<span v-if="errors.email" class="ck-error">{{ errors.email }}</span>
			</div>

			<div class="ck-form-row">
				<label class="ck-field-label">{{ $t ? $t('Password') : 'Password' }}</label>
				<input type="password" class="ck-input" v-model="form.password" :class="{ 'is-invalid': errors.password }">
				<span v-if="errors.password" class="ck-error">{{ errors.password }}</span>
			</div>

			<label class="ck-remember">
				<input type="checkbox" v-model="form.remember">
				{{ $t ? $t('Remember me') : 'Remember me' }}
			</label>

			<button type="submit" class="button primary-button w-full" :disabled="submitting">
				{{ $t ? $t('Log in') : 'Log in' }}
			</button>

			<div class="ck-auth-links">
				<Link :href="route('password.request')">{{ $t ? $t('Forgot your password?') : 'Forgot your password?' }}</Link>
				<Link :href="route('register')">{{ $t ? $t('Create an account') : 'Create an account' }}</Link>
			</div>
		</form>

	</AuthLayout>

</template>

<script>
	import { Link, router } from '@inertiajs/vue3';

	import AuthLayout from '../../layouts/AuthLayout.vue';

	export default {
		name: 'Login',
		components: { AuthLayout, Link },
		data() {
			return {
				form: {
					email: '',
					password: '',
					remember: false,
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

				if (!this.form.password)
					this.errors.password = this.$t ? this.$t('This field is required') : 'This field is required';

				return Object.keys(this.errors).length === 0;
			},
			submit() {
				if (!this.validateForm())
					return;

				this.submitting = true;

				router.post(route('login'), this.form, {
					onError: (errors) => { this.errors = errors; },
					onFinish: () => { this.submitting = false; },
				});
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-remember {
		display: flex;
		align-items: center;
		gap: .4rem;
		font-size: .85rem;
		margin-bottom: 1rem;
	}

	.ck-auth-links {
		display: flex;
		justify-content: space-between;
		font-size: .8rem;
		margin-top: 1rem;
	}
</style>
