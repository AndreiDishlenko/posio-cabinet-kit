<template>
    <AuthLayout title="Registration" :back_href="route('login')">

        <div ref="form" class="card-body">


			<div class="v-flex space-y-2">
				<div class="label-group">
					<!-- <label class="form-label">{{ $t('First Name')}}</label> -->
					<input ref="name" type="text" v-model="form_data.name" class="form-control md:form-control-lg" :placeholder="$t('First Name')"/>
					<p v-if="form_data_errors.name" class="form-error" >{{ form_data_errors.name }}</p>
				</div>
				<div class="label-group">
					<!-- <label class="form-label">{{ $t('Your email')}}</label> -->
					<input ref="email" type="text" v-model="form_data.email" class="form-control md:form-control-lg" :placeholder="$t('Your email')"/>
					<p v-if="form_data_errors.email" class="form-error" >{{ form_data_errors.email }}</p>
				</div>
				<div class="label-group">
					<label class="form-label">{{ $t('Password')}}</label>
					<input ref="password" type="password" v-model="form_data.password" class="form-control md:form-control-lg"/>
					<p v-if="form_data_errors.password" class="form-error" >{{ form_data_errors.password }}</p>
				</div>
				<div class="label-group">
					<label class="form-label">{{ $t('Confirmation')}}</label>
					<input ref="password_confirmation" type="password" v-model="form_data.password_confirmation" class="form-control md:form-control-lg"  @keydown.enter="submit()"/>
					<p v-if="form_data_errors.password_confirmation" class="form-error" >{{ form_data_errors.password_confirmation }}</p>
				</div>

				<button
					class="w-full button primary-button button-lg text-md !mt-4"
					:class="$inprogress.value && 'spinner'"
					@click.stop.prevent="submit"
					>{{ $t('Sign up')}}
				</button>
			</div>

			<!-- Divider -->
            <div class="relative flex justify-center items-center w-full !my-5">
				<div class="absolute inset-0 flex items-center">
					<div data-orientation="horizontal" role="none" data-slot="separator" class="shrink-0 bg-[var(--form-control-border-color)] data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px w-full"></div>
				</div>
                <!-- <div class="relative text-xs uppercase bg-card">
                    <span class="px-3 text-sm text-secondary">{{ $t('Continue with') }}</span>
                </div> -->
            </div>

			<!-- Register with Google / Apple -->
			<SocialAuthButtons />

        </div>

    </AuthLayout>
</template>

<script>
    import { Link, router } from '@inertiajs/vue3';      

    import sharedMixins     from '@/js/_sharedMixins.js'
    import _formMixins     from '@/js/_formMixins';

    import AuthLayout          from '../../Layouts/AuthLayout.vue';
    import SocialAuthButtons   from './SocialAuthButtons.vue';

    export default {
        mixins: [sharedMixins, _formMixins],
        components: { AuthLayout, Link, SocialAuthButtons },
        data() {
            return {
                validationRules: {
                    name:                   'required|min:6',
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