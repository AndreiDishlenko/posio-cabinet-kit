<template>

	<div class="card overflow-hidden">

		<div class="flex items-center justify-between gap-3 mb-4">
			<div>
				<h2 class="text-yellow">{{ $t(title) }}</h2>
				<span class="text-sm text-secondary">{{ $t(subtitle) }}</span>
			</div>
			<button v-if="can_add" class="button primary-button button-sm whitespace-nowrap" @click="startAdd()">
				<Icon icon="mdi:plus" class="icon icon-sm" />
				{{ $t('Add permission') }}
			</button>
		</div>

		<div class="overflow-x-auto scrollbar-thin">
			<table class="permissions-matrix">
				<thead>
					<tr>
						<th class="text-left">{{ $t('Permission') }}</th>
						<th v-for="role in ordered_roles" :key="role.id" class="role-col">{{ $t(role.name) }}</th>
						<th class="action-col"></th>
					</tr>
				</thead>
				<tbody>

					<tr v-if="adding" class="edit-row">
						<td>
							<input
								ref="newPermissionInput"
								v-model="new_permission_name"
								class="form-control"
								:placeholder="$t('Permission name')"
								maxlength="64"
								@keyup.enter="saveNew()"
								@keyup.esc="cancelAdd()"
								/>
						</td>
						<td :colspan="local_roles.length"></td>
						<td class="action-col">
							<div class="flex items-center justify-end gap-2">
								<Icon icon="mdi:check" class="icon text-success cursor-pointer" @click="saveNew()" />
								<Icon icon="mdi:close" class="icon text-secondary cursor-pointer" @click="cancelAdd()" />
							</div>
						</td>
					</tr>

					<tr v-for="permission in local_permissions" :key="permission.id">
						<td>
							<input
								v-if="editing_id === permission.id"
								v-model="edit_name"
								class="form-control"
								maxlength="64"
								@keyup.enter="saveEdit(permission)"
								@keyup.esc="cancelEdit()"
								/>
							<span v-else class="font-medium">{{ permission.name }}</span>
						</td>
						<td v-for="role in ordered_roles" :key="role.id" class="role-col">
							<input
								type="checkbox"
								class="cursor-pointer"
								:checked="hasPermission(role, permission)"
								:disabled="role.id === protected_role_id"
								@change="toggle(role, permission, $event.target.checked)"
								/>
						</td>
						<td class="action-col">
							<div class="flex items-center justify-end gap-2">
								<template v-if="editing_id === permission.id">
									<Icon icon="mdi:check" class="icon text-success cursor-pointer" @click="saveEdit(permission)" />
									<Icon icon="mdi:close" class="icon text-secondary cursor-pointer" @click="cancelEdit()" />
								</template>
								<Icon v-else-if="can_add" icon="mdi:pencil-outline" class="icon text-secondary cursor-pointer" @click="startEdit(permission)" />
							</div>
						</td>
					</tr>

				</tbody>
			</table>
		</div>

	</div>

</template>

<script>
	import { Icon }      from '@iconify/vue';

	import sharedMixins  from '@/js/_sharedMixins';

	export default {
		name: 'PermissionsMatrixTable',
		mixins: [sharedMixins],
		components: { Icon },
		props: {
			title: {
				type: String,
				default: 'Roles and permissions',
			},
			subtitle: {
				type: String,
				default: 'Manage which permissions each role has',
			},
			roles: {
				type: Array,
				default: () => [],
			},
			permissions: {
				type: Array,
				default: () => [],
			},
			protected_role_id: {
				type: Number,
				default: 0,
			},
			// Only the system (sa-only) matrix may create/rename permission definitions.
			can_add: {
				type: Boolean,
				default: false,
			},
		},
		data() {
			return {
				// Local mutable copy of the role→permission matrix
				local_roles: this.roles.map(role => ({ ...role, permission_ids: [...role.permission_ids] })),
				local_permissions: this.permissions.map(permission => ({ ...permission })),
				adding: false,
				new_permission_name: '',
				editing_id: null,
				edit_name: '',
				// Display order of roles, left → right by descending importance
				role_order: {
					'SAdmin':               1,
					'System administrator': 2,
					'System user':          3,
					'Account owner':        4,
					'Administrator':        5,
					'Manager':              6,
					'User':                 7,
				},
			}
		},
		computed: {
			ordered_roles() {
				return [...this.local_roles].sort(
					(a, b) => (this.role_order[a.name] ?? 99) - (this.role_order[b.name] ?? 99)
				);
			},
		},
		watch: {
			roles(value) {
				this.local_roles = value.map(role => ({ ...role, permission_ids: [...role.permission_ids] }));
			},
			permissions(value) {
				this.local_permissions = value.map(permission => ({ ...permission }));
			}
		},
		methods: {
			hasPermission(role, permission) {
				const local = this.local_roles.find(r => r.id === role.id);
				return local ? local.permission_ids.includes(permission.id) : false;
			},

			async toggle(role, permission, granted) {
				const local = this.local_roles.find(r => r.id === role.id);
				if ( !local )
					return;

				// Optimistic update
				if ( granted )
					local.permission_ids.push(permission.id);
				else
					local.permission_ids = local.permission_ids.filter(id => id !== permission.id);

				const result = await this.$apiClient.post(
					route('admin.role.togglepermission'),
					{ role_id: role.id, permission_id: permission.id, granted }
				);

				if ( result.error ) {
					// Revert
					if ( granted )
						local.permission_ids = local.permission_ids.filter(id => id !== permission.id);
					else
						local.permission_ids.push(permission.id);
					return this.$toast.error( this.$t(result.error) );
				}

				return this.$toast.success( this.$t('Permission access updated') );
			},

			startAdd() {
				this.cancelEdit();
				this.adding = true;
				this.new_permission_name = '';
				this.$nextTick(() => this.$refs.newPermissionInput?.focus());
			},

			cancelAdd() {
				this.adding = false;
				this.new_permission_name = '';
			},

			async saveNew() {
				const name = this.new_permission_name.trim();
				if ( !name )
					return;

				const result = await this.$apiClient.post(
					route('admin.permission.store'),
					{ name }
				);

				if ( result.error )
					return this.$toast.error( this.$t(result.error) );

				this.local_permissions.push(result.data.permission);

				// A new permission is granted to the super-admin role on the backend by
				// default — reflect that immediately so its (disabled) box shows checked.
				const super_admin_id = result.data.super_admin_role_id;
				const super_admin = this.local_roles.find(r => r.id === super_admin_id);
				if ( super_admin && !super_admin.permission_ids.includes(result.data.permission.id) )
					super_admin.permission_ids.push(result.data.permission.id);

				this.cancelAdd();
				return this.$toast.success( this.$t('Permission added') );
			},

			startEdit(permission) {
				this.cancelAdd();
				this.editing_id = permission.id;
				this.edit_name = permission.name;
			},

			cancelEdit() {
				this.editing_id = null;
				this.edit_name = '';
			},

			async saveEdit(permission) {
				const name = this.edit_name.trim();
				if ( !name || name === permission.name )
					return this.cancelEdit();

				const result = await this.$apiClient.post(
					route('admin.permission.update'),
					{ id: permission.id, name }
				);

				if ( result.error )
					return this.$toast.error( this.$t(result.error) );

				permission.name = result.data.permission.name;
				this.cancelEdit();
				return this.$toast.success( this.$t('Permission updated') );
			},
		}
	}
</script>

<style lang="scss" scoped>
	.permissions-matrix {
		width: 100%;
		border-collapse: collapse;

		th, td {
			padding: 0.5rem 0.75rem;
			border-bottom: 1px solid rgba(127, 127, 127, 0.15);
			vertical-align: middle;
		}

		thead th {
			font-weight: 600;
			white-space: nowrap;
		}

		.role-col {
			text-align: center;
			width: 1%;
			white-space: nowrap;
		}

		.action-col {
			width: 1%;
			white-space: nowrap;
		}
	}
</style>
