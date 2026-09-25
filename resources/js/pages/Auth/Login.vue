<template>

    <AuthLayout title="Sign in account">

        <div class="card-body">

			<!-- Итог перехода по ссылке из письма: подтверждение почты или протухшее письмо -->
			<div v-if="status"
				class="text-center text-sm mb-10"
				:class="status_is_error ? 'text-error' : 'text-secondary'">
				{{ $t(status) }}
			</div>

			<!-- Continue with email -->
			<form class="v-flex space-y-2" @submit.prevent="submit">

				<div class="label-group">
					<label class="form-label" for="login-email">{{ $t('Your email')}}</label>
					<input id="login-email" ref="email" type="email" autocomplete="email" v-model="form_data.email" class="form-control md:form-control-lg" @change.stop.prevent="nextField('email', 'password')"/>
					<p v-if="form_data_errors.email" class="form-error" >{{ $t(form_data_errors.email) }}</p>
				</div>

				<div class="label-group">
					<label class="form-label" for="login-password">{{ $t('Password')}}</label>
					<PasswordInput class="w-full form-control md:form-control-lg"
						id="login-password"
						ref="password"
						autocomplete="off"
						v-model="form_data.password"
						maxlength="20"
						:placeholder="'********'"
						/>
					<p v-if="form_data_errors.password" class="form-error" >{{ $t(form_data_errors.password) }}</p>
				</div>

				<button
					type="submit"
					class="w-full button primary-button button-lg text-md"
					:class="$inprogress.value && 'spinner'"
					>{{ $t('Continue')}}
				</button>

			</form>

			<!-- Forgot / Register links -->
            <div class="card-footer-text sm:px-4 text-secondary">
                <Link as="button" :href="route('password.request')" class="hover:underline ">{{ $t('Forgot password?')}}</Link>
                <Link v-if="$page.props.registration_open" as="button" :href="route('register')" class="hover:underline ">{{ $t('Sign up')}}</Link>
            </div>

			<!-- Login with Google / Apple -->
			<SocialAuthButtons />

        </div>

    </AuthLayout>

</template>

<script>
    import { Link, router } from '@inertiajs/vue3';
    import axios            from 'axios'; 

    import sharedMixins     from '@/js/_sharedMixins.js'
    import _formMixins     from '@/js/_formMixins';

    import AuthLayout          from '../../layouts/AuthLayout.vue';
    import SocialAuthButtons   from './SocialAuthButtons.vue';
    import PasswordInput       from '@/js/Elements/Forms/PasswordInput.vue';

    export default {
        mixins: [sharedMixins, _formMixins],
        components: { AuthLayout, Link, SocialAuthButtons, PasswordInput },
        props: {
            email: {
                type: String,
                default: ''
            },
            status: {
                type: String,
                default: ''
            },

        },
        data() {
            return {
                validationRules: {
                    email: 'required|email',
                    password: 'required'
                },
                responseInterceptor: null
            }
        },
        computed: {
            status_is_error() {
                return ['verification-link-invalid', 'verification-link-broken', 'social-auth-failed'].includes(this.status);
            }
        },
        mounted() {
            // console.log('login.mnt', this.email);
            // Email autocomplete
            if (this.email)
                var lastemail = this.email
            else
                var lastemail = localStorage.getItem('lastemail');

            if (lastemail) {
                this.form_data.email = lastemail;
                // this.$nextTick(() => {
                //     this.$refs.password.focus();
                // });
            } else {
                this.$nextTick(() => {
                    // this.$refs.email.focus();
                });
            }

            // Clear browser-autofilled password — Chrome fills DOM directly, bypassing Vue
            this.$nextTick(() => {
                setTimeout(() => {
                    if (this.$refs.password)
                        this.$refs.password.clear();

                    // Вход по ссылке из письма — адрес получателя важнее сохранённого браузером логина.
                    if (this.email)
                        this.form_data.email = this.email;
                }, 200);
            });

            // Catch 409 redirect and save email
            this.responseInterceptor = axios.interceptors.response.use(
                response => response,
                error => {
                    if (error.response && error.response.status === 409) 
                        this.saveEmail();
                    return Promise.reject(error);
                }
            );
        },
        beforeUnmount() {
            // Disable 409 check
            if (this.responseInterceptor !== null) 
                axios.interceptors.response.eject(this.responseInterceptor);
        },
        methods: {
            saveEmail() {
                localStorage.setItem('lastemail', this.form_data.email);
            },
            submit: async function(e) {
                // console.log('submit', this.validateForm());
                if ( !await this.validateForm() )
                    return false;

                this.saveEmail();
                router.post( route('login'), { ...this.form_data, remember: true }, {
                    onError: (errors) => {
                        if (errors.error) {
                            this.$toast.error(this.$t(errors.error));
                            this.form_data.password='';
                            this.$refs.password.focus();
                        }
                        this.outputErrors(errors)
                        return false;  
                    },
                })
            }
        }
    }
</script>

<style lang="scss" scoped>
	.bg-card {
		background-color: #292929;
	}
</style>
