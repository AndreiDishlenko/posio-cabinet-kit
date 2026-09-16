<template>
    <AuthLayout title="Registration" :back_href="route('login')">

        <div ref="form" class="card-body">


			<form class="v-flex space-y-2" @submit.prevent="submit">
				<div class="label-group">
					<label class="form-label" for="register-name">{{ $t('First Name')}}</label>
					<input id="register-name" ref="name" type="text" autocomplete="name" v-model="form_data.name" class="form-control md:form-control-lg"/>
					<p v-if="form_data_errors.name" class="form-error" >{{ form_data_errors.name }}</p>
				</div>
				<div class="label-group">
					<label class="form-label" for="register-email">{{ $t('Your email')}}</label>
					<input id="register-email" ref="email" type="email" autocomplete="email" v-model="form_data.email" class="form-control md:form-control-lg"/>
					<p v-if="form_data_errors.email" class="form-error" >{{ form_data_errors.email }}</p>
				</div>
				<div class="label-group">
					<label class="form-label" for="register-password">{{ $t('Password')}}</label>
					<PasswordInput id="register-password" ref="password" autocomplete="new-password" v-model="form_data.password" v-model:visible="password_visible" class="form-control md:form-control-lg" aria-describedby="register-password-hint"/>
					<!-- Требование к паролю видно до ошибки, а не появляется вместо неё после отправки. -->
					<p id="register-password-hint" class="text-sm text-secondary">{{ $t('must be at least {length} characters', { length: 8 }) }}</p>
					<p v-if="form_data_errors.password" class="form-error" >{{ form_data_errors.password }}</p>
				</div>
				<div class="label-group">
					<label class="form-label" for="register-password-confirmation">{{ $t('Confirmation')}}</label>
					<PasswordInput id="register-password-confirmation" ref="password_confirmation" autocomplete="new-password" v-model="form_data.password_confirmation" :reveal="false" :visible="password_visible" class="form-control md:form-control-lg"  @keydown.enter="submit()"/>
					<p v-if="form_data_errors.password_confirmation" class="form-error" >{{ form_data_errors.password_confirmation }}</p>
				</div>

				<button
					type="submit"
					class="w-full button primary-button button-lg text-md !mt-4"
					:class="$inprogress.value && 'spinner'"
					>{{ $t('Sign up')}}
				</button>
			</form>

			<!-- Register with Google / Apple -->
			<SocialAuthButtons />

        </div>

    </AuthLayout>
</template>

<script>
    import { Link, router } from '@inertiajs/vue3';      

    import sharedMixins     from '@/js/_sharedMixins.js'
    import _formMixins     from '@/js/_formMixins';

    import AuthLayout          from '../../layouts/AuthLayout.vue';
    import SocialAuthButtons   from './SocialAuthButtons.vue';
    import PasswordInput       from '@/js/Elements/Forms/PasswordInput.vue';

    export default {
        mixins: [sharedMixins, _formMixins],
        components: { AuthLayout, Link, SocialAuthButtons, PasswordInput },
        data() {
            return {
                password_visible: false,
                validationRules: {
                    // Без нижней границы длины: реальные короткие имена (Ян, Лев) — не ошибка.
                    name:                   'required',
                    email:                  'required|email',
                    password:               'required|password',
                    password_confirmation:  'required|confirmed:password'
                },
            }
        },
        mounted() {
            this.$nextTick(() => {
                // this.$refs.name.focus();
            });
            // this.form_data.name="Andrew"
            // this.form_data.email="sergps7@gmail.com"
            // this.form_data.password="12345678"
            // this.form_data.password_confirmation="12345678"
        },
        methods: {
            submit: async function(e) {
                if ( !await this.validateForm() )
                    return false;

                this.form_data.locale = this.$i18n.locale;
                router.post( route('register'), this.form_data, {
                    onError: (errors) => {
                        if (errors.error)
                            this.$toast.error(errors.error);
                        this.outputErrors(errors);                   
                    },
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        }
    }
</script>
