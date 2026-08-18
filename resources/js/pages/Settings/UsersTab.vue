<template>

	<div class="ck-card">
		<h3 class="ck-card-title">{{ $t ? $t('Users') : 'Users' }}</h3>

		<table class="ck-simple-table">
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
					<td>{{ member.name }} <span v-if="member.is_owner" class="ck-badge">{{ $t ? $t('Owner') : 'Owner' }}</span></td>
					<td>{{ member.email }}</td>
					<td>
						<span v-if="member.is_owner">{{ member.role || ownerRoleLabel }}</span>
						<select v-else-if="can_manage_account"
							class="ck-input ck-role-select"
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

		<form v-if="can_manage_account" class="ck-invite-form" @submit.prevent="inviteMember">
			<h4 class="ck-card-subtitle">{{ $t ? $t('Invite a user') : 'Invite a user' }}</h4>
			<div class="ck-invite-row">
				<input
					type="email"
					class="ck-input"
					v-model="invite.email"
					:placeholder="$t ? $t('User email') : 'User email'"
					maxlength="70"
					>
				<select class="ck-input ck-role-select" v-model="invite.role">
					<option v-for="role in roles" :key="role.name" :value="role.name">
						{{ $t ? $t(role.name) : role.name }}
					</option>
				</select>
				<button type="submit" class="button primary-button button-sm" :disabled="invite.saving">
					{{ $t ? $t('Invite') : 'Invite' }}
				</button>
			</div>
			<span v-if="invite.error" class="ck-error">{{ invite.error }}</span>
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

<style lang="scss" scoped>
	.ck-simple-table {
		width: 100%;
		border-collapse: collapse;

		th, td {
			text-align: start;
			padding: .5rem;
			border-bottom: 1px solid var(--ck-border-color, #e5e7eb);
		}
	}

	.ck-badge {
		font-size: .7rem;
		opacity: .6;
		margin-inline-start: .35rem;
	}

	.ck-role-select {
		min-width: 150px;
	}

	.ck-invite-form {
		margin-top: 1.25rem;
		padding-top: 1rem;
		border-top: 1px solid var(--ck-border-color, #e5e7eb);
	}

	.ck-card-subtitle {
		font-size: .95rem;
		font-weight: 600;
		margin-bottom: .75rem;
	}

	.ck-invite-row {
		display: flex;
		flex-wrap: wrap;
		gap: .75rem;
		align-items: center;

		.ck-input {
			width: auto;
		}

		input.ck-input {
			min-width: min(260px, 100%);
			flex: 1 1 220px;
		}
	}
</style>
