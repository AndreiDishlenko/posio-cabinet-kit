<template lang="">
    <div ref="form" class="card !space-y-5">
        <h2 class="text-yellow">{{ $t('Connected companies')}}</h2>

        <div class="card-body !mt-0 mx-2 text-secondary">
            <div v-for="(account, index) in accounts" class="w-full flex justify-between items-center space-x-4">
                <div class="w-1/3">{{ account.number }}</div>
                <div class="grow">{{ account.name }}</div>
                <!-- <Selectable
                    v-model="account.role"
                    :in_data="user_roles"
                    :placeholder="'Administrator'"
                    /> -->

                <button class="button" @click="leaveGuestAccount(index)">{{ $t('Leave') }}</button>
            </div>
        </div>     

        <!-- Join request -->
        <div class="label-group space-y-3">
            <h3>{{ $t('Join a third-party company')}}</h3>
            <div class="flex items-center space-x-4">
                <label class="form-label !ms-0">{{ $t('Enter the owner\'s email address')}}:</label>
                <input ref="account_owner_email" type="text" v-model="account_owner_email" class="form-control" maxlength="70"/>
                <button class="button primary-button" 
                    :class="[
                        !this.account_owner_email ? 'disabled' : ''
                    ]" 
                    @click="sendJoinAccountRequest()">
                        {{ $t('Request') }}
                </button>
            </div>
            <p v-if="form_data_errors.account_owner_email" class="form-error" >{{ form_data_errors.account_owner_email }}</p>
        </div>                

</div>
</template>

<script>
    import { router }       from '@inertiajs/vue3'; 
    import { validate }     from 'vee-validate'; 
    import { Icon }         from '@iconify/vue'

    import Selectable       from '@/js/Elements/Forms/Selectable.vue';

    export default {
        // mixins: [formMixins],
        components: { Icon, Selectable },
        props: {
            accounts: {
                type: Array,
                default: []
            }
        },
        data() {
            return {
                user_roles: [
                    { id:1, name:'User'},
                    { id:1, name:'Administrator'},
                ],
                account_owner_email: '',
                form_data_errors: {}
            }
        },
        mounted() {
        },
        methods: {
            async sendJoinAccountRequest() {
                let result = await validate(this.account_owner_email, 'email');
                if ( !result.valid ) {
                    this.form_data_errors.account_owner_email = result.errors
                    return false;
                }

                this.form_data_errors = {}

                result = await this.$apiClient.post( route('cabinet.account.joinrequest'), { email: this.account_owner_email } )
                if ( result.error )
                    return this.$toast.error( result.error )

                this.account_owner_email = ''
                this.$toast.success( this.$t('Request was sent') )
            },
            async leaveGuestAccount(account_index) {
                // console.log('leaveGuestAccount', account_index);
                
                let result = await this.$popup.confirm_yn( this.$t('Are you sure you want to leave account?'), { danger: true } );
                if ( !result )
                    return false;

                let account = this.accounts[account_index]

                result = await this.$apiClient.post( route('cabinet.account.leave'), { number: account.number });
                if ( result.error )
                    return this.$toast.error(result.error)

                this.accounts.splice(account_index, 1);


                return this.$toast.success( this.$t("You have logged out of account.", { account_name : 'account.name' } ) );
            },
        }
    }
</script>

<style lang="scss" scoped>
    
</style>