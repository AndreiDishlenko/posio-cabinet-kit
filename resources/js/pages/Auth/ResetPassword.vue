<template>
    <AuthLayout title="Reset Password" :back_href="route('login')">

        <template v-if="!is_expired">
            <div ref="form" class="card-body">
                <div class="label-group !mt-1">
                    <label class="form-label">{{ $t('Your email')}}</label>
                    <input ref="email" type="text" v-model="form_data.email" class="form-control md:form-control-lg"/>
                    <p v-if="form_data_errors.email" class="form-error" >{{ form_data_errors.email }}</p>
                </div>
                <div class="label-group">
                    <label class="form-label">{{ $t('Password')}}</label>
                    <input ref="password" type="password" v-model="form_data.password" class="form-control md:form-control-lg"/>
                    <p v-if="form_data_errors.password" class="form-error" >{{ form_data_errors.password }}</p>
                </div>
                <div class="label-group !mt-1">
                    <label class="form-label">{{ $t('Confirmation')}}</label>
                    <input ref="password_confirmation" type="password" v-model="form_data.password_confirmation" class="form-control md:form-control-lg" @keyup.enter="submit()"/>
                    <p v-if="form_data_errors.password_confirmation" class="form-error" >{{ form_data_errors.password_confirmation }}</p>
                </div>
            </div>

            <div class="card-footer">
                <button 
                    class="w-full button primary-button button-lg text-md" 
                    :class="$inprogress.value && 'spinner'" 
                    @click.stop.prevent="submit" 
                    >
                    {{ $t('Reset Password')}}
                </button>
            </div>
        </template>

        <template v-else>
            <div class="card-body text-center">
                {{ $t('invalid_token') }}
            </div>

            <div class="card-footer !flex-col !space-x-0 space-y-5">
                <Link as="button" :href="route('password.request')" class="hover:underline text-secondary">{{ $t('Forgot password?')}}</Link>
            </div>
        </template>

    </AuthLayout>
</template>

<script>
    import { Link, router }       from '@inertiajs/vue3'; 
    import axios            from 'axios';      

    import sharedMixins     from '@/js/_sharedMixins.js'
    import _formMixins      from '@/js/_formMixins';

    import AuthLayout       from '../../Layouts/AuthLayout.vue';

    export default {
        mixins: [sharedMixins, _formMixins],
        components: { Link, AuthLayout },
        props: {
            email: {
                type: String,
                default: '',
            },
            token: {
                type: String,
                default: '',
            },
            is_expired: {
                type: Boolean,
                default: false
            }
        },
        data() {
            return {
                validationRules: {
                    email:      'required|email',
                    password:   'required|password',
                    password_confirmation: 'required|confirmed:password'
                },
                responseInterceptor: null
            }
        },
        mounted() {
            if (this.is_expired)
                return false;

            this.$nextTick(() => {
                this.form_data.email = this.email;
                this.form_data.token = this.token;
                this.$refs.password.focus();
            });

            // Catch 302 redirect and save email
            // this.responseInterceptor = axios.interceptors.response.use(
            //     response => {
            //         console.log('intercept', response);
                    
            //         if (response.status === 302) {
            //             console.log('302');
                        
            //             this.saveEmail();
            //         }

            //         return response;
            //     }
            // );
        },
        // beforeUnmount() {
        //     // Disable 302 check
        //     if (this.responseInterceptor !== null) 
        //         axios.interceptors.response.eject(this.responseInterceptor);
        // },
        methods: {
            saveEmail() {
                localStorage.setItem('lastemail', this.form_data.email);
            },
            submit: async function(e) {
                if ( !await this.validateForm() )
                    return false;

                router.post( route('password.store'), this.form_data, {
                    onSuccess: (page) => {
                        this.saveEmail()
                    },
                    onError: (errors) => {
                        if (errors.error)
                            this.$toast.error(errors.error);
                        this.outputErrors(errors);                   
                    },
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        },

    }
</script>
