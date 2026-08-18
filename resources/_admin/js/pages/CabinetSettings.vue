<template>
    <CabinetLayout :space_y="2" :page_name="current_tab_title">

        <Tabs
            v-model="active_tab"
            :tabs="tabs"
            storage-key="settings"
        >
            <!-- User settings -->
            <template #settings>
                <div class="v-flex items-stretch space-y-6 pb-6">

                    <!-- Avatar -->
                    <div class="v-flex relative">
                        <div class="card min-h-[85px]"></div>
                        <div class="user-avatar flex items-start z-[2]">
                            <Avatar :src="profile.avatar" :user_name="profile.name" class="avatar-image" size="xxl"/>
                        </div>
                        <div v-if="!is_system_user" class="photo-button icon-label relative ">
                            <input
                                type="file"
                                ref="avatarInput"
                                @change="handleFileChange"
                                accept="image/*"
                                class="hidden"
                                />
                            <Icon icon="cuida:edit-outline" size="25px" @click="$refs.avatarInput.click()"/>

                        </div>
                        <span class="profile-since text-sm text-secondary">{{ $t('Posio user since') }} {{ profile.registered }}</span>
                    </div>

                    <UserProfileTab
                        :in_data="profile"
                        :disabled="is_system_user"
                        />

                </div>
            </template>

            <!-- Account -->
            <template #account>
                <div class="v-flex items-stretch space-y-6 pb-6">
                    <template v-if="Object.keys(own_account).length">
                        <AccountTab
                            :in_data="own_account"
                            :users="account_users"
                            :can_edit="can_manage_members"
                            />

                        <!-- Delete account — owner only -->
                        <div v-if="is_owner" class="card disabled">
                            <h2 class="text-yellow">{{ $t('Delete Account') }}</h2>
                            <span class="text-sm">* {{ $t('After making deletion request, you will have 6 months to restore this account.') }}</span>
                            <span class="text-sm">{{ $t("To permanently erase your Posio account, click the button below. This implies that you won't have access to your enterprises, accounting and personal financial data.") }}</span>

                            <div class="card-footer d-center text-sm">
                                <button class="button">{{ $t('Process') }}</button>
                            </div>
                        </div>
                    </template>

                    <div v-else class="d-center py-10 rounded-lg border border-dashed border-stone-700">
                        <button class="button primary-button disabled">{{ $t('Create own company') }}</button>
                    </div>
                </div>
            </template>

            <!-- Users — owner / manager only (tab gated by can_manage_members) -->
            <template #users>
                <div class="v-flex items-stretch space-y-6 pb-6">
                    <h2 v-if="account_name" class="account-scope-name">{{ account_name }}</h2>
                    <AccountUsersTab
                        :in_data="own_account"
                        :users="account_users"
                        :roles="assignable_roles"
                        :can_edit="can_manage_account_users"
                        />
                </div>
            </template>

            <!-- Integrations — owner / manager only (tab gated by can_manage_members) -->
            <template #integrations>
                <div class="v-flex items-stretch space-y-6 pb-6">
                    <template v-if="Object.keys(own_account).length">
                        <h2 v-if="account_name" class="account-scope-name">{{ account_name }}</h2>
                        <IntegrationsTab
                            :in_data="own_account.integrations"
                            :account_name="own_account.name"
                            />
                    </template>

                    <div v-else class="d-center py-10 rounded-lg border border-dashed border-stone-700">
                        <button class="button primary-button disabled">{{ $t('Create own company') }}</button>
                    </div>
                </div>
            </template>

        </Tabs>

    </CabinetLayout>
</template>

<script>
    import sharedMixins     from '@/js/_sharedMixins'
    
    import { router }       from '@inertiajs/vue3';  
    import { Icon }         from '@iconify/vue';

    import CabinetLayout    from '@/_admin/js/layouts/CabinetLayout.vue';
    import Tabs             from '@/js/Elements/Tabs.vue';

    import { buildSettingsTabs } from './Settings/settingsTabs.js';

    import UserProfileTab   from './Settings/CabinetSettingsUserProfileTab.vue';
    import AccountTab   	from './Settings/CabinetSettingsAccountTab.vue';
    import AccountUsersTab     from './Settings/CabinetSettingsAccountUsersTab.vue';
    import IntegrationsTab   from './Settings/CabinetSettingsIntegrationsTab.vue';

    // import AccountUsersProfile   from '../../../../_backup/AccountUsersProfile.vue';
    import GuestAccounts    from './Settings/GuestAccounts.vue';

    import Avatar           from '@/js/Elements/Avatar.vue';

    export default {
        mixins: [sharedMixins],
        components: { Icon, CabinetLayout, Tabs, UserProfileTab, AccountTab, AccountUsersTab, IntegrationsTab, GuestAccounts, Avatar },
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
        },
        data() {
            return {
                active_tab: 'settings',
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
            // Account-scoped tabs surface the account name as a highlighted heading at the
            // top of the tab content instead of in the page header / Title.
            account_name() {
                return this.own_account?.name;
            },
        },

        methods: {
            getImageDimensions(file) {
                return new Promise((resolve, reject) => {
                    const img = new Image();
                    img.src = URL.createObjectURL(file);
                    img.onload = () => {
                        resolve({ width: img.width, height: img.height });
                        URL.revokeObjectURL(img.src); // Освобождаем память
                    };
                    img.onerror = () => {
                        reject(new Error('Не удалось загрузить изображение'));
                    };
                });
            },

            async handleFileChange(event) {
                // console.log('handleFileChange', event.target.files[0])
                const file = event.target.files[0];
                this.$refs.avatarInput.value = '';
                if (!file) return;

                // Проверяем, что файл является изображением
                if (!file.type.startsWith('image/')) {
                    this.$toast.error('Please, select image');
                    return;
                }

                const maxSizeInBytes = 2 * 1024 * 1024;
                if (file.size > maxSizeInBytes) {
                    this.$toast.error('File size should not exceed 2 MB');
                    return;
                }

                const { width, height } = await this.getImageDimensions(file);
                if (width < 150 || height < 150) {
                    this.$toast.error('The image must be at least 150x150 pixels');
                    return;
                }
                if (width > 1000 || height > 1000) {
                    this.$toast.error('The image should be no larger than 1000x1000 pixels');
                    return;
                }

                const formData = new FormData();
                formData.append('photo', file);

                router.post( route('cabinet.settings.avatar'), formData, {
                    onError: (errors) => {
                        this.$toast.error('Error loading photo');
                    },
                    preserveScroll: true,
                    preserveState: true
                });

            },

        }
    }
</script>

<style lang="scss" scoped>

    // Highlighted account name shown at the top of account-scoped tabs.
    .account-scope-name {
        @apply text-lg font-semibold self-start;
        padding: 0.25rem 0.75rem;
        border-left: 3px solid var(--primary-button-bg);
        color: var(--text-color);
    }

    .user-avatar {
        position: absolute;
        top: 36px;
        left: 30px;

        font-size: var(--text-xl);
        @apply
            absolute 
    }

    .avatar-image {
        border: 4px solid var(--background-color);
    }

    .photo-button {
        position: absolute;
        top: 15px;
        right: 5px;
        color: var(--text-color-secondary);
        cursor: pointer;
    }

    // Desktop: floats in the bottom-right corner of the banner card.
    .profile-since {
        position: absolute;
        bottom: 0.5rem;
        right: 1rem;
    }

    // Mobile: drop into normal flow under the overhanging avatar so the date
    // never overlaps it and the banner fully contains the avatar (no bleed
    // into the profile form below).
    @media (max-width: 639px) {
        .profile-since {
            position: static;
            display: block;
            margin-top: 3.25rem;
            padding-right: 0.25rem;
            text-align: right;
        }
    }
</style>
