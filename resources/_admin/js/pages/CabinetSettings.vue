<template>
    <CabinetLayout :space_y="2" :page_name="current_tab_title" header_title="Settings">

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

    import { defineAsyncComponent } from 'vue';
    import { buildSettingsTabs, settingsTabLoader } from './Settings/settingsTabs.js';

    // Компоненты табов подхватываются по факту наличия файла, а не жёстким
    // импортом: страницу можно перенести в другой проект с любым подмножеством
    // табов — сборка не упадёт на несуществующем пути. Грузятся лениво: код таба
    // приходит, только когда его открыли.
    const tab_components = {};

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
            },
            // Предупреждение о завершении лицензии — то же, о чём уходит письмо владельцу.
            license_notice: {
                type: Object,
                default: () => null
            },
            license_plans: {
                type: Array,
                default: () => []
            },
            // Варианты срока для формы заявки менеджеру (ключи $t).
            license_terms: {
                type: Array,
                default: () => []
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
                    cash_accounts: {},
                    users: {
                        in_data:  this.own_account,
                        users:    this.account_users,
                        roles:    this.assignable_roles,
                        can_edit: this.can_manage_account_users,
                    },
                    licenses: {
                        licenses: this.account_licenses,
                        summary:  this.license_summary,
                        notice:   this.license_notice,
                        plans:    this.license_plans,
                        terms:    this.license_terms,
                        // Заявку заполняет тот, кто её открыл, — его контакты и подставляем.
                        contact: {
                            name:  this.profile?.name,
                            phone: this.profile?.phone,
                            email: this.profile?.email,
                        },
                    },
                    integrations: {
                        in_data:     this.own_account.integrations,
                        has_account: this.has_own_account,
                    },
                    cashflow_items: {},
                };
            },
        },

        created() {
            this.active_tab = this.tabs.length ? this.tabs[0].id : '';
        },

        methods: {
            // Одна обёртка на файл: новая при каждом рендере пересоздавала бы таб.
            tabComponent(file) {
                const loader = settingsTabLoader(file);
                if (!loader)
                    return null;

                return tab_components[file] ??= defineAsyncComponent(loader);
            },
        }
    }
</script>
