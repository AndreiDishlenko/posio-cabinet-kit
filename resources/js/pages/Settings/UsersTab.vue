<template>

	<div class="card">
		<h3 class="card-header">{{ $t ? $t('Users') : 'Users' }}</h3>

		<table class="table table-sm">
			<thead>
				<tr>
					<th>{{ $t ? $t('Name') : 'Name' }}</th>
					<th>{{ $t ? $t('Email') : 'Email' }}</th>
					<th>{{ $t ? $t('Role') : 'Role' }}</th>
					<th v-if="can_manage_account"></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="member in members" :key="member.id">
					<td>{{ member.name }} <span v-if="member.is_owner" class="status-badge badge-muted">{{ $t ? $t('Owner') : 'Owner' }}</span></td>
					<td>{{ member.email }}</td>
					<td>
						<span v-if="member.is_owner">{{ member.role || ownerRoleLabel }}</span>
						<select v-else-if="can_manage_account"
							class="form-control form-select"
							:value="member.role"
							@change="changeRole(member, $event.target.value)"
							>
							<option v-for="role in roles" :key="role.name" :value="role.name">
								{{ $t ? $t(role.name) : role.name }}
							</option>
						</select>
						<span v-else>{{ member.role || defaultRoleLabel }}</span>
					</td>
					<td v-if="can_manage_account">
						<button v-if="!member.is_owner" type="button" class="button ghost-button button-sm" @click="removeMember(member)">
							{{ $t ? $t('Remove') : 'Remove' }}
						</button>
					</td>
				</tr>
			</tbody>
		</table>

		<form v-if="can_manage_account" class="form-group" @submit.prevent="inviteMember">
			<h4 class="card-header">{{ $t ? $t('Invite a user') : 'Invite a user' }}</h4>
			<div class="flex flex-col sm:flex-row gap-3">
				<input
					type="email"
					class="form-control"
					v-model="invite.email"
					:placeholder="$t ? $t('User email') : 'User email'"
					maxlength="70"
					>
				<select class="form-control form-select" v-model="invite.role">
					<option v-for="role in roles" :key="role.name" :value="role.name">
						{{ $t ? $t(role.name) : role.name }}
					</option>
				</select>
				<button type="submit" class="button primary-button button-sm" :disabled="invite.saving">
					{{ $t ? $t('Invite') : 'Invite' }}
				</button>
			</div>
			<span v-if="invite.error" class="form-error">{{ invite.error }}</span>
		</form>
	</div>

</template>

<script>
	import { router } from '@inertiajs/vue3';

	export default {
		name: 'UsersTab',
		props: {
			members: {
				type: Array,
				default: () => [],
			},
			can_manage_account: {
				type: Boolean,
				default: false,
			},
			roles: {
				type: Array,
				default: () => [],
			},
		},
		data() {
			return {
				invite: {
					email: '',
					role: this.roles[0]?.name || 'Administrator',
					error: '',
					saving: false,
				},
			}
		},
		computed: {
			ownerRoleLabel() {
				return this.$t ? this.$t('Account owner') : 'Account owner';
			},
			defaultRoleLabel() {
				return this.$t ? this.$t('User') : 'User';
			},
		},
		methods: {
			changeRole(member, role) {
				if (!role || role === member.role)
					return;

				router.post(route('cabinet-kit.account.member.role'), { user_id: member.id, role }, { preserveScroll: true });
			},
			inviteMember() {
				this.invite.error = '';

				if (!/^\S+@\S+\.\S+$/.test(this.invite.email)) {
					this.invite.error = this.$t ? this.$t('Enter a valid email') : 'Enter a valid email';
					return;
				}

				this.invite.saving = true;

				router.post(route('cabinet-kit.account.member.invite'), {
					email: this.invite.email,
					role: this.invite.role,
				}, {
					preserveScroll: true,
					onSuccess: () => {
						this.invite.email = '';
					},
					onError: (errors) => {
						this.invite.error = errors.email || errors.user_id || errors.error || (this.$t ? this.$t('Could not invite user') : 'Could not invite user');
					},
					onFinish: () => {
						this.invite.saving = false;
					},
				});
			},
			removeMember(member) {
				const confirmed = window.confirm(this.$t ? this.$t('Are you sure you want to remove account member?') : 'Are you sure you want to remove account member?');
				if (!confirmed)
					return;

				router.post(route('cabinet-kit.account.member.remove'), { user_id: member.id }, { preserveScroll: true });
			},
		},
	}
</script>

<style lang="scss">

</style>
