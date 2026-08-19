<template lang="">

	<Head :title="$t ? $t(title || 'Cabinet') : (title || 'Cabinet')"/>

	<div class="v-flex !min-h-dvh-100" :class="$inprogress.value ? 'disabled' : ''">

        <!-- <ParticlesBackground /> -->

        <div class="grow flex flex-col relative z-10">

            <div class="grow flex items-center justify-center min-h-[70px]">
                <!-- <a :href="route('home', { locale: $i18n.locale })"> -->
                    <img src="/main-assets/images/logo_white.png" alt="Logo" class="h-[25px] w-auto opacity-25" />
                <!-- </a> -->
            </div>

            <div class="flex justify-center">
                <div class="card w-full max-w-[90%] sm:max-w-md !space-y-8 !p-8 text-sm">

                    <!-- <div class="hidden">
                        <ThemeSelector />
                    </div> -->

                    <div class="card-header !mt-0 !mb-2 relative flex items-center justify-center">
                        <Link v-if="back_href" :href="back_href" class="absolute left-0 text-secondary hover:text-primary flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </Link>
                        <span class="h2 !text-2xl text-secondary">
                            {{ $t(title)}}
                        </span>
                    </div>

                    <slot />

                </div>
            </div>

            <div class="grow"></div>

        </div>

	</div>


</template>

<script>
	import { Link } from '@inertiajs/vue3';
	import { Head } from '@inertiajs/vue3';

	// import ParticlesBackground from '../Components/ParticlesBackground.vue';
    // import ThemeSelector    from '@/js/Custom/_ThemeSelector.vue';

    export default {
		components: { Link, Head }, //, ParticlesBackground, ThemeSelector },
        props: {
            title: {
                type: String,
                default: ''
            },
            back_href: {
                type: String,
                default: null
            },
        }
    }
</script>

<style>
    body {
        background-color: #1f1f1f;
    }
</style>

<style lang="scss" scoped>
    .card {
        background-color: #292929;
    }
</style>
