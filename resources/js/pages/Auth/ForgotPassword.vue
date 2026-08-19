<template>   
    <AuthLayout title="Forgot Password" :back_href="route('login')">

        <template v-if="!status">
            <div ref="form" class="card-body">
                <div class="text-secondary text-center">
                    {{ $t('forgot-message') }}
                </div>
                <div class="label-group">
                    <!-- <label class="form-label">{{ $t('Your email')}}</label> -->
                    <input ref="email" type="text" v-model="form_data.email" class="form-control md:form-control-lg" @keyup.enter="submit()" autofocus/>
                    <p v-if="form_data_errors.email" class="form-error" >{{ $t(form_data_errors.email) }}</p>
                </div>
            <!-- </div>

            <div class="card-footer !flex-col !space-x-0 space-y-5"> -->
                <button                     
                    class="w-full button primary-button button-lg text-md" 
                    :class="$inprogress.value && 'spinner'" 
                    @click.stop.prevent="submit" 
                    >
                    {{ $t('Send Reset Link') }}
                </button>
                <div class="flex justify-center font-sm font-medium text-secondary">
                    <Link :href="route('login')" class="hover:underline">{{ $t('Remember the password?')}}</Link>
                </div>
            </div>
        </template>

        <div v-else class="card-body text-center mb-4">
            {{ $t(status) }}
        </div>

    </AuthLayout>
</template>

<script>
    import { Link, router } from '@inertiajs/vue3';      

    import sharedMixins     from '@/js/_sharedMixins.js'
    import _formMixins     from '@/js/_formMixins';

    import AuthLayout      from '../../Layouts/AuthLayout.vue';

    export default {
        mixins: [sharedMixins, _formMixins],
        components: { AuthLayout, Link },
        props: {
            status: {
                type: String,
            },
        },
        data: function() {
            return {
                validationRules: {
                    email: 'required|email'
                },
            }
        },
        mounted() {
            var lastemail = localStorage.getItem('lastemail');
            if (lastemail) 
                this.form_data.email = lastemail;

            this.$refs.email.focus();
        },
        methods: {
            submit: async function(e) {
                if ( !await this.validateForm() )
                    return false;

                router.post(route('password.email'), this.form_data, {
                    // locale: this.$i18n.locale,
                    onSuccess: (response) => {
                        console.log('success', response);                        
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
        }
    }
</script>