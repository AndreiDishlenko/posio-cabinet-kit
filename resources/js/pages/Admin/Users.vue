<template>
	<CabinetLayout page_name="Користувачі">
		<div class="ck-card ck-admin-panel">
			<div class="ck-toolbar">
				<input v-model="search" class="ck-input ck-search" type="search" placeholder="Search">
				<span class="ck-muted">{{ filteredUsers.length }} / {{ users.length }}</span>
			</div>

			<table class="ck-admin-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>Email</th>
						<th>Phone</th>
						<th>System role</th>
						<th>Registered</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="user in filteredUsers" :key="user.id">
						<td><input v-model="forms[user.id].name" class="ck-input"></td>
						<td>{{ user.email }}</td>
						<td><input v-model="forms[user.id].phone" class="ck-input"></td>
						<td>
							<select v-model="forms[user.id].role_id" class="ck-input" :disabled="!permissions.roles">
								<option :value="null">-</option>
								<option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
							</select>
						</td>
						<td>{{ formatDate(user.created_at) }}</td>
						<td class="ck-actions">
							<button type="button" class="button primary-button button-sm" :disabled="forms[user.id].saving" @click="save(user)">
								Save
							</button>
							<button type="button" class="button ghost-button button-sm" :disabled="forms[user.id].saving" @click="setPassword(user)">
								Password
							</button>
						</td>
					</tr>
					<tr v-if="!filteredUsers.length">
						<td colspan="6" class="ck-empty">No users</td>
					</tr>
				</tbody>
			</table>
		</div>
	</CabinetLayout>
</template>

<script>
	import { router } from '@inertiajs/vue3';
	import CabinetLayout from '../../layouts/CabinetLayout.vue';

	export default {
		name: 'AdminUsers',
		components: { CabinetLayout },
		props: {
			users: { type: Array, default: () => [] },
			roles: { type: Array, default: () => [] },
			permissions: { type: Object, default: () => ({ users: false, roles: false }) },
		},
		data() {
			return {
				search: '',
				forms: Object.fromEntries(this.users.map(user => [user.id, this.formFor(user)])),
			}
		},
		computed: {
			filteredUsers() {
				const needle = this.search.trim().toLowerCase();
				if (!needle) return this.users;

				return this.users.filter(user => [user.name, user.email, user.phone, user.role_name]
					.filter(Boolean)
					.some(value => String(value).toLowerCase().includes(needle)));
			},
		},
		methods: {
			formFor(user) {
				return {
					id: user.id,
					name: user.name || '',
					phone: user.phone || '',
					role_id: user.role_id || null,
					password: '',
					password_confirmation: '',
					saving: false,
				};
			},
			save(user) {
				const form = this.forms[user.id];
				form.saving = true;

				router.put(route('cabinet-kit.users.update'), {
					id: form.id,
					name: form.name,
					phone: form.phone,
					role_id: form.role_id,
					password: form.password || null,
					password_confirmation: form.password_confirmation || null,
				}, {
					preserveScroll: true,
					onSuccess: () => {
						form.password = '';
						form.password_confirmation = '';
					},
					onFinish: () => { form.saving = false; },
				});
			},
			setPassword(user) {
				const password = window.prompt(`New password for ${user.email}`);
				if (!password) return;

				const confirmation = window.prompt('Confirm password');
				if (password !== confirmation) return;

				this.forms[user.id].password = password;
				this.forms[user.id].password_confirmation = confirmation;
				this.save(user);
			},
			formatDate(value) {
				return value ? new Date(value).toLocaleDateString() : '';
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-admin-panel { overflow: auto; }
	.ck-toolbar { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; }
	.ck-search { max-width: 320px; }
	.ck-muted { opacity: .55; font-size: .85rem; }
	.ck-admin-table { width: 100%; min-width: 860px; border-collapse: collapse; }
	.ck-admin-table th, .ck-admin-table td { text-align: start; padding: .55rem; border-bottom: 1px solid var(--ck-border-color, #e5e7eb); vertical-align: middle; }
	.ck-admin-table th { font-size: .8rem; background: var(--ck-table-header-bg, #f6f7f9); }
	.ck-actions { width: 170px; display: flex; gap: .35rem; }
	.ck-empty { text-align: center; opacity: .55; padding: 1.5rem; }
</style>
