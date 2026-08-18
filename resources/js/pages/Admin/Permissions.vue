<template>
	<CabinetLayout :page_name="title">
		<div class="ck-card ck-admin-panel">
			<form class="ck-toolbar" @submit.prevent="addPermission">
				<input v-model="newPermission" class="ck-input ck-search" type="text" placeholder="New permission" maxlength="80">
				<button type="submit" class="button primary-button button-sm">Add</button>
			</form>

			<table class="ck-admin-table">
				<thead>
					<tr>
						<th>Role</th>
						<th v-for="permission in permissions" :key="permission.id">
							<button type="button" class="ck-permission-name" @dblclick="rename(permission)">
								{{ permission.name }}
							</button>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="role in roles" :key="role.id">
						<th>{{ role.name }}</th>
						<td v-for="permission in permissions" :key="permission.id">
							<input
								type="checkbox"
								:checked="role.permission_ids.includes(permission.id)"
								:disabled="role.id === protected_role_id"
								@change="toggle(role, permission, $event.target.checked)"
							>
						</td>
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
		name: 'AdminPermissions',
		components: { CabinetLayout },
		props: {
			title: { type: String, default: 'Permissions' },
			is_system: { type: Boolean, default: true },
			roles: { type: Array, default: () => [] },
			permissions: { type: Array, default: () => [] },
			protected_role_id: { type: Number, default: 0 },
		},
		data() {
			return { newPermission: '' };
		},
		methods: {
			toggle(role, permission, granted) {
				router.post(route('cabinet-kit.permissions.toggle'), {
					role_id: role.id,
					permission_id: permission.id,
					granted,
				}, { preserveScroll: true });
			},
			addPermission() {
				const name = this.newPermission.trim();
				if (!name) return;

				router.post(route('cabinet-kit.permissions.store'), {
					name,
					is_system: this.is_system,
				}, {
					preserveScroll: true,
					onSuccess: () => { this.newPermission = ''; },
				});
			},
			rename(permission) {
				const name = window.prompt('Permission name', permission.name);
				if (!name || name === permission.name) return;

				router.put(route('cabinet-kit.permissions.rename'), {
					id: permission.id,
					name,
				}, { preserveScroll: true });
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-admin-panel { overflow: auto; }
	.ck-toolbar { display: flex; align-items: center; gap: .75rem; margin-bottom: 1rem; }
	.ck-search { max-width: 320px; }
	.ck-admin-table { width: 100%; min-width: 760px; border-collapse: collapse; }
	.ck-admin-table th, .ck-admin-table td { text-align: center; padding: .55rem; border-bottom: 1px solid var(--ck-border-color, #e5e7eb); }
	.ck-admin-table th:first-child { text-align: start; position: sticky; left: 0; background: var(--ck-card-bg, #fff); }
	.ck-permission-name { max-width: 180px; font-size: .78rem; overflow-wrap: anywhere; text-align: center; }
</style>
