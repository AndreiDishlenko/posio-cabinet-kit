<template>
    <AuthLayout title="Email Verification">

        <div class="card-body">
            <div v-if="status === 'verification-link-sent'" class="text-center">
                {{ $t('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
            <div v-else-if="error" class="text-center text-error">
                {{ $i18n.tNumbered(error) }}
            </div>
            <div v-else class="text-center text-secondary text-sm">
                {{ $t('hellow-message') }}
            </div>
        </div>

        <div class="card-footer !flex-col !space-x-0 space-y-5">
            <button
                class="w-full button button-lg text-md"
                :class="[
                    $inprogress.value && 'spinner',
                    (status === 'verification-link-sent' || cooldownRemaining > 0) ? 'disabled' : ''
                ]"
                @click.stop.prevent="resendVerification"
                >
                <span v-if="cooldownRemaining > 0">{{ $t('Resend Verification Email')}} ({{ cooldownFormatted }})</span>
                <span v-else>{{ $t('Resend Verification Email')}}</span>
            </button>
            <div class="card-footer-text !justify-center">
                <Link as="button" method="post" :href="route('logout')">
                    {{ $t('Logout')}}
                </Link>
            </div>
        </div>

    </AuthLayout>
</template>

<script>
    import { Link, router } from '@inertiajs/vue3';

    import sharedMixins     from '@/js/_sharedMixins.js'
    
    import AuthLayout      from '../../Layouts/AuthLayout.vue';

    export default {
        mixins: [sharedMixins],
        components: { Link, AuthLayout },
        props: {
            status: {
                type: String,
                default: ''
            },
            error: {
                type: String,
                default: ''
            },
        },
        data: function() {
            return {
                cooldownRemaining: 120,
                cooldownTimer: null,
            }
        },
        computed: {
            cooldownFormatted() {
                const m = Math.floor(this.cooldownRemaining / 60);
                const s = this.cooldownRemaining % 60;
                return m + ':' + String(s).padStart(2, '0');
            }
        },
        mounted() {
            this.startCooldown();
        },
        beforeUnmount() {
            clearInterval(this.cooldownTimer);
        },
        methods: {
            startCooldown() {
                clearInterval(this.cooldownTimer);
                this.cooldownRemaining = 120;
                this.cooldownTimer = setInterval(() => {
                    this.cooldownRemaining--;
                    if (this.cooldownRemaining <= 0) {
                        this.cooldownRemaining = 0;
                        clearInterval(this.cooldownTimer);
                    }
                }, 1000);
            },
            resendVerification() {
                // this.form_data.locale = this.$i18n.locale;
                router.post( route('verification.send'), {}, {
                    onError: (errors) => {
                        if (errors.error)
                            this.$toast.error(errors.error);
                    },
                    preserveScroll: true,
                    preserveState: true,
                });
                this.startCooldown();
            }
        }
    }
</script>