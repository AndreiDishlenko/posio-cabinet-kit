<template>

	<div class="ck-profile-tab">

		<div class="ck-card">
			<h3 class="ck-card-title">{{ $t ? $t('Profile') : 'Profile' }}</h3>

			<form @submit.prevent="submitProfile">
				<div class="ck-form-row">
					<label class="ck-field-label">{{ $t ? $t('Name') : 'Name' }}</label>
					<input type="text" class="ck-input" v-model="profileForm.name" :class="{ 'is-invalid': profileErrors.name }">
					<span v-if="profileErrors.name" class="ck-error">{{ profileErrors.name }}</span>
				</div>

				<div class="ck-form-row">
					<label class="ck-field-label">{{ $t ? $t('Email') : 'Email' }}</label>
					<input type="email" class="ck-input" v-model="profileForm.email" :class="{ 'is-invalid': profileErrors.email }">
					<span v-if="profileErrors.email" class="ck-error">{{ profileErrors.email }}</span>
				</div>

				<button type="submit" class="button primary-button button-sm" :disabled="savingProfile">
					{{ $t ? $t('Save') : 'Save' }}
				</button>
			</form>
		</div>

		<div class="ck-card">
			<h3 class="ck-card-title">{{ $t ? $t('Change password') : 'Change password' }}</h3>

			<form @submit.prevent="submitPassword">
				<div class="ck-form-row">
					<label class="ck-field-label">{{ $t ? $t('Current password') : 'Current password' }}</label>
					<input type="password" class="ck-input" v-model="passwordForm.current_password" :class="{ 'is-invalid': passwordErrors.current_password }">
					<span v-if="passwordErrors.current_password" class="ck-error">{{ passwordErrors.current_password }}</span>
				</div>

				<div class="ck-form-row">
					<label class="ck-field-label">{{ $t ? $t('New password') : 'New password' }}</label>
					<input type="password" class="ck-input" v-model="passwordForm.password" :class="{ 'is-invalid': passwordErrors.password }">
					<span v-if="passwordErrors.password" class="ck-error">{{ passwordErrors.password }}</span>
				</div>

				<div class="ck-form-row">
					<label class="ck-field-label">{{ $t ? $t('Confirm new password') : 'Confirm new password' }}</label>
					<input type="password" class="ck-input" v-model="passwordForm.password_confirmation" :class="{ 'is-invalid': passwordErrors.password_confirmation }">
					<span v-if="passwordErrors.password_confirmation" class="ck-error">{{ passwordErrors.password_confirmation }}</span>
				</div>

				<button type="submit" class="button primary-button button-sm" :disabled="savingPassword">
					{{ $t ? $t('Update password') : 'Update password' }}
				</button>
			</form>
		</div>

	</div>

</template>

<script>
	import { router, usePage } from '@inertiajs/vue3';

	export default {
		name: 'ProfileTab',
		data() {
			const user = usePage().props.user || {};

			return {
				profileForm: {
					name: user.name || '',
					email: user.email || '',
				},
				profileErrors: {},
				savingProfile: false,

				passwordForm: {
					current_password: '',
					password: '',
					password_confirmation: '',
				},
				passwordErrors: {},
				savingPassword: false,
			}
		},
		methods: {
			validateProfileForm() {
				this.profileErrors = {};

				if (!this.profileForm.name.trim())
					this.profileErrors.name = this.$t ? this.$t('This field is required') : 'This field is required';

				if (!/^\S+@\S+\.\S+$/.test(this.profileForm.email))
					this.profileErrors.email = this.$t ? this.$t('Enter a valid email') : 'Enter a valid email';

				return Object.keys(this.profileErrors).length === 0;
			},
			submitProfile() {
				if (!this.validateProfileForm())
					return;

				this.savingProfile = true;

				router.put(route('cabinet-kit.profile.update'), this.profileForm, {
					preserveScroll: true,
					onError: (errors) => { this.profileErrors = errors; },
					onFinish: () => { this.savingProfile = false; },
				});
			},
			validatePasswordForm() {
				this.passwordErrors = {};

				if (!this.passwordForm.current_password)
					this.passwordErrors.current_password = this.$t ? this.$t('This field is required') : 'This field is required';

				if (!this.passwordForm.password || this.passwordForm.password.length < 8)
					this.passwordErrors.password = this.$t ? this.$t('Password must be at least 8 characters') : 'Password must be at least 8 characters';

				if (this.passwordForm.password !== this.passwordForm.password_confirmation)
					this.passwordErrors.password_confirmation = this.$t ? this.$t('Passwords do not match') : 'Passwords do not match';

				return Object.keys(this.passwordErrors).length === 0;
			},
			submitPassword() {
				if (!this.validatePasswordForm())
					return;

				this.savingPassword = true;

				router.put(route('cabinet-kit.profile.password'), this.passwordForm, {
					preserveScroll: true,
					onSuccess: () => {
						this.passwordForm.current_password = '';
						this.passwordForm.password = '';
						this.passwordForm.password_confirmation = '';
					},
					onError: (errors) => { this.passwordErrors = errors; },
					onFinish: () => { this.savingPassword = false; },
				});
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-profile-tab {
		display: flex;
		flex-direction: column;
		gap: 1.25rem;
	}
</style>
