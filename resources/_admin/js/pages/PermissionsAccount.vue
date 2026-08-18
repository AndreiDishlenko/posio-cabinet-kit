<template>

	<CabinetLayout :page_name="'Account roles'">

		<PermissionsMatrixTable
			:title="'Account roles and permissions'"
			:subtitle="'Manage which permissions each account role has'"
			:roles="roles"
			:permissions="permissions"
			:protected_role_id="protected_role_id"
			:can_add="false"
			/>

	</CabinetLayout>

</template>

<script>
	import CabinetLayout          from '@/_admin/js/layouts/CabinetLayout.vue';
	import PermissionsMatrixTable from '@/_admin/js/components/ui/PermissionsMatrixTable.vue';

	export default {
		name: 'PermissionsAccount',
		components: { CabinetLayout, PermissionsMatrixTable },
		props: {
			roles: {
				type: Array,
				default: () => []
			},
			permissions: {
				type: Array,
				default: () => []
			},
			protected_role_id: {
				type: Number,
				default: 0
			},
		},
	}
</script>
