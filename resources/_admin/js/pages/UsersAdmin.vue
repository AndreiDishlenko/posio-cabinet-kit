<template lang="">

    <CabinetLayout>

        <!-- {{users}} -->

        <Table class="grow table-md"
            :settings  = "table_settings"
            :in_data   = "display_rows"
			:selects   = "dynamic_selects"
            @rowSelect = "(row) => openTableRecord( row )"
            @onOpen    = "(row) => openTableRecord( row )"
            />    

        <ModalForm ref="modalform" >
            <UserCard
                ref="usercard"
                :in_data="currentRow"
                :roles="roles"
                :perms="permissions"
                route_prefix="cabinet-kit.users"
                @close="closeTableModal()"
                />
        </ModalForm>

    </CabinetLayout>
	
</template>

<script>
    import sharedMixins     from '@/js/_sharedMixins';
    import tableformMixins  from '@/js/_tableformMixins.js';

    import CabinetLayout    from '@/_admin/js/layouts/CabinetLayout.vue';
    import ModalForm        from '@/js/Elements/ModalForm.vue';

    import UserCard         from '@/_admin/js/components/cards/DictCards/UserCard.vue'
    
    export default {
        mixins: [sharedMixins, tableformMixins],
        components: { CabinetLayout, ModalForm, UserCard },
        props: {
            users: {
                type: Object,
                default: []
            },
            roles: {
                type: Object,
                default: []
            },
            permissions: {
                type: Object,
                default: () => ({ users: false, roles: false, accounts: false })
            },
        },
		computed: {
			dynamic_selects() {
				return {
					"role_id":  this.roles
				}
			},
			display_rows() {
				return this.users.map(user => Object.assign(user, {
					// Ждёт допуска администратора (режим одобрения регистрации);
					// approval_requested_at приходит только при включённом режиме.
					pending_approval: user.approval_requested_at && !user.approved_at ? 1 : 0,
				}));
			},
		},
        data: function () {
            return {
				// route_prefix 	: 'cabinet.user',

                table_settings: {
                    columns: [
                        { field: 'id' },
                        { field: 'registered',   title: 'Registred',        width:'min-content' },
                        { field: 'email',        title: 'E-mail' },
                        { field: 'name',         title: 'First Name' },
                        { field: 'role_id',      title: 'Role name',    type:'select'},
						// Кнопка видна только у ждущих допуска — иначе пустая ячейка.
						// Право на клик проверяет и бэкенд (sysper-users на маршруте).
						...(this.permissions.users ? [{
							field:   'pending_approval',
							title:   'Approval',
							type:    'button',
							// column.label рендерится без $t (в отличие от title) — переводим сразу.
							label:   this.$t('Approve'),
							getter:  (row) => !!row.pending_approval,
							onClick: (row) => this.approveRegistration(row),
						}] : []),
                    ],
                    rowbar: [
                        { event: 'onOpen', icon: 'material-symbols:folder-open-outline' }
                    ],
                }
            }
        },
        methods: {
			async approveRegistration(row) {
				const confirmed = await this.$popup.confirm_yn(
					this.$t('Approve registration of {email}?', { email: row.email })
				);
				if ( !confirmed )
					return;

				const result = await this.$apiClient.post(route('cabinet-kit.users.approve'), { id: row.id });
				if ( result.error )
					return this.$toast.error(result.error);

				row.approved_at = result.data.approved_at;
				row.pending_approval = 0;
				this.$toast.success(this.$t('Registration approved'));
			},
        }
    }
</script>

<style lang="scss">    
</style>