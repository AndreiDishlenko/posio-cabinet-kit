<template lang="">

    <CabinetLayout>

        <!-- {{users}} -->

        <Table class="grow table-md"
            :settings  = "table_settings" 
            :in_data   = "users" 
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
                    ],
                    rowbar: [
                        { event: 'onOpen', icon: 'material-symbols:folder-open-outline' }
                    ],
                }
            }
        },
        methods: {
        }
    }
</script>

<style lang="scss">    
</style>