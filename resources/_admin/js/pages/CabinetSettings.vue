<template>
    <CabinetLayout :space_y="2" :page_name="current_tab_title">

        <Tabs
            v-model="active_tab"
            :tabs="tabs"
            storage-key="settings"
        >
            <!-- Содержимое таба целиком лежит в его компоненте: страница только
                 раздаёт данные, поэтому отсутствующий файл таба ничего не ломает. -->
            <template v-for="tab in tabs" #[tab.id]>
                <component :is="tabComponent(tab.file)" v-bind="tab_props[tab.id]" />
            </template>

        </Tabs>

    </CabinetLayout>
</template>

<script>
    import sharedMixins     from '@/js/_sharedMixins'

    import CabinetLayout    from '@/_admin/js/layouts/CabinetLayout.vue';
    import Tabs             from '@/js/Elements/Tabs.vue';

    import { buildSettingsTabs } from './Settings/settingsTabs.js';

    // Компоненты табов подхватываются по факту наличия файла, а не жёстким
    // импортом: страницу можно перенести в другой проект с любым подмножеством
    // табов — сборка не упадёт на несуществующем пути.
    const tab_components = {};
    for (const [path, module] of Object.entries(import.meta.glob('./Settings/CabinetSettings*Tab.vue', { eager: true })))
        tab_components[path.replace('./Settings/', '')] = module.default;

    export default {
        mixins: [sharedMixins],
        components: { CabinetLayout, Tabs },
        props: {
            profile: {
                type: Object,
                default: {}
            },
            own_account: {
                type: Object,
                default: {}
            },
            account_integrations: {
                type: Object,
                default: {}
            },
            account_users: {
                type: Array,
                default: []
            },
            assignable_roles: {
                type: Array,
                default: []
            },
            can_manage_members: {
                type: Boolean,
                default: false
            },
            can_manage_account_users: {
                type: Boolean,
                default: false
            },
            is_owner: {
                type: Boolean,
                default: false
            },
            is_system_user: {
                type: Boolean,
                default: false
            },
            guest_accounts: {
                type: Array,
                default: []
            },
            account_licenses: {
                type: Array,
                default: () => []
            },
            license_summary: {
                type: Object,
                default: () => null
            }
        },
        data() {
            return {
                active_tab: '',
            }
        },

        computed: {
            // Owner / manager (manage-members) see member management plus the
            // account-wide configuration tabs; other roles only own settings + account.
            // Состав табов вынесен в общий модуль settingsTabs.js — тот же список
            // подтягивает выпадающее меню настроек в SideMenu.
            tabs() {
                return buildSettingsTabs(this.can_manage_members);
            },
            current_tab_title() {
                const tab = this.tabs.find(t => t.id === this.active_tab);
                return tab ? tab.label : '';
            },
            // Account-scoped tabs (Users, Integrations, Cash flow items, Cash
            // accounts) surface the account name as a highlighted heading at the
            // top of the tab content instead of in the page header / Title.
            account_name() {
                return this.own_account?.name;
            },
            has_own_account() {
                return !!Object.keys(this.own_account || {}).length;
            },
            // Данные для каждого таба. Ключи, для которых нет файла таба, просто
            // не используются.
            tab_props() {
                return {
                    settings: {
                        in_data:  this.profile,
                        disabled: this.is_system_user,
                    },
                    account: {
                        in_data:    this.own_account,
                        users:      this.account_users,
                        can_edit:   this.can_manage_members,
                        can_delete: this.is_owner,
                    },
                    cash_accounts: {
                        account_name: this.account_name,
                    },
                    users: {
                        in_data:      this.own_account,
                        users:        this.account_users,
                        roles:        this.assignable_roles,
                        can_edit:     this.can_manage_account_users,
                        account_name: this.account_name,
                    },
                    licenses: {
                        licenses:     this.account_licenses,
                        summary:      this.license_summary,
                        account_name: this.account_name,
                    },
                    integrations: {
                        in_data:      this.own_account.integrations,
                        account_name: this.account_name,
                        has_account:  this.has_own_account,
                    },
                    cashflow_items: {
                        account_name: this.account_name,
                    },
                };
            },
        },

        created() {
            this.active_tab = this.tabs.length ? this.tabs[0].id : '';
        },

        methods: {
            tabComponent(file) {
                return tab_components[file];
            },
        }
    }
</script>
