<template lang="">

    <CabinetLayout page_name="Users">

        <!-- {{users}} -->

        <Table class="grow" size="md"
            :settings  = "table_settings"
            :in_data   = "display_rows"
			:selects   = "dynamic_selects"
			:filters   = "dynamic_filters"
            @rowSelect = "(row) => openTableRecord( row )"
            @onOpen    = "(row) => openTableRecord( row )"
            >

			<template #tools>
				<Selectable class="role-filter !min-w-48"
					input_class = "form-control-md"
					v-model     = "role_filter"
					:in_data    = "roles"
					:placeholder= "$t('All roles')"
					:addall     = "true"
					/>
			</template>

		</Table>

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
    import Selectable       from '@/js/Elements/Forms/Selectable.vue';

    import UserCard         from '@/_admin/js/components/cards/DictCards/UserCard.vue'

    export default {
        mixins: [sharedMixins, tableformMixins],
        components: { CabinetLayout, ModalForm, UserCard, Selectable },
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
			// Строка поиска живёт в настройках панели инструментов таблицы —
			// здесь только удобный доступ к ней для фильтра.
			search_string() {
				return (this.table_settings.panelitems.search.model || '').trim().toLowerCase();
			},
			only_without_account() {
				return !!this.table_settings.custom_tools.no_account.model;
			},
			display_rows() {
				return this.users.map(user => Object.assign(user, {
					// Одна плоская строка под подстрочный поиск: почта, имя и компании
					// сразу — фильтры таблицы сравнивают по одному полю за раз.
					search_str: [user.email, user.name, user.account_names]
						.filter(Boolean)
						.join(' ')
						.toLowerCase(),
					has_account: user.account_names ? 1 : 0,
					// Ждёт допуска администратора (режим одобрения регистрации);
					// approval_requested_at приходит только при включённом режиме.
					pending_approval: user.approval_requested_at && !user.approved_at ? 1 : 0,
				}));
			},
			dynamic_filters() {
				return [
					['search_str', 'like', this.search_string],
					['role_id', this.role_filter || ''],
					// Пустое значение фильтра означает «не задан» — снятый флажок
					// не должен ограничивать список.
					['has_account', this.only_without_account ? 0 : ''],
				]
			},
		},
        data: function () {
            return {
				// route_prefix 	: 'cabinet.user',

				role_filter: '',

                table_settings: {
                    columns: [
                        { field: 'id' },
                        { field: 'registered',   title: 'Registred',        width:'min-content' },
                        { field: 'email',        title: 'E-mail' },
                        { field: 'name',         title: 'First Name' },
                        { field: 'account_names', title: 'Companies',   type:'string' },
                        { field: 'role_id',      title: 'Role name',    type:'select'},
						// Признак подтверждения почты отдаётся только держателям права на пользователей.
						...(this.permissions.users ? [
							{ field: 'email_verified', title: 'Email verified', type: 'dot', width: 'min-content', align: 'center' },
							{
								field:   'verify_email',
								title:   'Verification',
								type:    'button',
								label:   this.$t('Confirm'),
								getter:  (row) => !row.email_verified,
								onClick: (row) => this.verifyEmail(row),
							},
						] : []),
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
					panelitems: {
						search: {
							type: 'search',
							placeholder: 'Search by e-mail, name or company',
							model: '',
						},
					},
					custom_tools: {
						no_account: {
							type: 'checkbox_button',
							name: 'Without company',
							icon: 'mdi:domain-off',
							model: false,
						},
					},
                    rowbar: [
                        { event: 'onOpen' }
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
			async verifyEmail(row) {
				const confirmed = await this.$popup.confirm_yn(
					this.$t('Confirm e-mail {email}?', { email: row.email })
				);
				if ( !confirmed )
					return;

				const result = await this.$apiClient.post(route('cabinet-kit.users.verifyemail'), { id: row.id });
				if ( result.error )
					return this.$toast.error(result.error);

				row.email_verified = 1;
				// Подтверждение выдаёт системную роль — показываем её без перезагрузки списка.
				if ( result.data.role_id )
					row.role_id = result.data.role_id;
				this.$toast.success(this.$t('Email verified'));
			},
        }
    }
</script>

<style lang="scss">
</style>
