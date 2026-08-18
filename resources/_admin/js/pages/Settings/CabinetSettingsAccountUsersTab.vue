<template lang="">
    <div ref="form" class="v-flex items-stretch space-y-6">

        <!-- Account owner -->
        <div v-if="owner" class="card">
            <h2 class="text-yellow">{{ $t('Account owner') }}</h2>
            <div class="flex flex-col gap-1 text-secondary sm:flex-row sm:items-center sm:space-x-4 sm:gap-0 sm:mx-2">
                <div class="text-color truncate sm:w-1/3">{{ owner.name }}</div>
                <div class="grow truncate">{{ owner.email }}</div>
                <div class="text-sm sm:w-40 sm:text-right">{{ $t('Account owner') }}</div>
            </div>
        </div>

        <!-- Members -->
        <div class="card">
            <div class="card-body">
                <h2 class="text-yellow">{{ $t('Account Users') }}</h2>

                <div v-if="!guests.length" class="text-secondary mx-2">{{ $t('No connected users yet') }}</div>

                <div
                    v-for="(user, index) in guests"
                    :key="user.id"
                    class="flex flex-col gap-3 text-secondary sm:flex-row sm:items-center sm:space-x-4 sm:mx-2"
                    >
                    <div class="flex flex-col min-w-0 grow sm:flex-row sm:items-center sm:space-x-4">
                        <div class="text-color truncate sm:w-1/3">{{ user.name }}</div>
                        <div class="grow truncate">{{ user.email }}</div>
                    </div>

                    <div class="flex items-center justify-between gap-3 sm:justify-end">
                        <Selectable
                            class="w-40"
                            
                            :in_data="ordered_roles"
                            :model-value="user.role_id"
                            :placeholder="$t('Administrator')"
                            @onChange="(role_id) => changeRole(user, role_id)"
                            />

                        <template v-if="can_edit">
                            <button class="button lt-sm:hidden" @click="removeMember(index)">{{ $t('Disconnect') }}</button>
                            <Icon icon="mdi:trash-outline" class="icon text-secondary sm:hidden" @click="removeMember(index)" />
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invite user -->
        <div class="card label-group space-y-3">
            <h3>{{ $t('Invite a user') }}</h3>
            <span class="text-sm text-secondary">{{ $t('invite_description') }}</span>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:space-x-4 sm:gap-0">
                <input
                    ref="invite_email"
                    type="text"
                    v-model="invite_email"
                    :placeholder="$t('Enter the user\'s email address')"
                    class="form-control"
                    maxlength="70"
                    />
                <button class="button primary-button lt-sm:w-full"
                    :class="(invite_email && can_edit) ? '' : 'disabled'"
                    @click="invite()"
                    >{{ $t('Invite') }}
                </button>
            </div>
            <p v-if="invite_error" class="form-error">{{ invite_error }}</p>
        </div>

    </div>
</template>

<script>
    import { Icon }         from "@iconify/vue";
    import { validate }     from 'vee-validate';

    import Selectable       from '@/js/Elements/Forms/Selectable.vue';

    export default {
        components: { Icon, Selectable },
        props: {
            in_data: {
                type: Object,
                default: () => ({})
            },
            users: {
                type: Array,
                default: () => []
            },
            roles: {
                type: Array,
                default: () => []
            },
            // Whether the current user may edit account users (invite / change
            // role / remove). Mirrors the manage-account permission and is
            // also enforced on the backend routes.
            can_edit: {
                type: Boolean,
                default: false
            }
        },
        data() {
            return {
                members: this.users.map(user => ({ ...user })),
                invite_email: '',
                invite_error: '',
                // Display order of assignable roles
                role_order: {
                    'Administrator': 1,
					'Manager':       2,
                    'User':          3
                }
            }
        },
        computed: {
            owner() {
                return this.members.find(user => user.is_owner) || null;
            },
            guests() {
                return this.members.filter(user => !user.is_owner);
            },
            ordered_roles() {
                return [...this.roles].sort(
                    (a, b) => (this.role_order[a.name] ?? 99) - (this.role_order[b.name] ?? 99)
                );
            }
        },
        watch: {
            users(value) {
                this.members = value.map(user => ({ ...user }));
            }
        },
        methods: {
            isSelf(user) {
                return Number(user.id) === Number(this.$page.props.user?.id);
            },

            async changeRole(user, role_id) {
                // Requires the manage-account permission (also enforced
                // on the backend). A user must not change their own role; the
                // system (root) user's per-account role may be changed — only
                // its identity/security fields and removal stay locked.
                if ( !this.can_edit || this.isSelf(user) )
                    return;

                if ( !role_id || role_id === user.role_id )
                    return;

                const previous = user.role_id;
                user.role_id = role_id;

                const result = await this.$apiClient.post(
                    route('cabinet.account.member.role'),
                    { email: user.email, role_id }
                );

                if ( result.error ) {
                    user.role_id = previous;
                    return this.$toast.error(result.error);
                }

                const role = this.roles.find(r => r.id === role_id);
                if ( role )
                    user.role_name = role.name;

                return this.$toast.success( this.$t('Member role updated') );
            },

            async invite() {
                if ( !this.can_edit )
                    return false;

                const validation = await validate(this.invite_email, 'email');
                if ( !validation.valid ) {
                    this.invite_error = validation.errors[0];
                    return false;
                }
                this.invite_error = '';

                const result = await this.$apiClient.post(
                    route('cabinet.account.member.invite'),
                    { email: this.invite_email }
                );
                if ( result.error )
                    return this.$toast.error(result.error);

                this.invite_email = '';
                return this.$toast.success( this.$t('Invitation was sent') );
            },

            async removeMember(index) {
                if ( !this.can_edit )
                    return false;

                const user = this.guests[index];

                const confirmed = await this.$popup.confirm_yn( this.$t('Are you sure you want to remove account member?'), { danger: true } );
                if ( !confirmed )
                    return false;

                const result = await this.$apiClient.post(
                    route('cabinet.account.member.remove'),
                    { email: user.email }
                );
                if ( result.error )
                    return this.$toast.error(result.error);

                const member_index = this.members.findIndex(m => m.id === user.id);
                if ( member_index !== -1 )
                    this.members.splice(member_index, 1);

                return this.$toast.success( this.$t('Member was removed successfully') );
            }
        }
    }
</script>

<style lang="scss">

</style>
