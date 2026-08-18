<template >

    <div v-if="isVisible" class="loading-screen">
		<div class="flex flex-col justify-center items-center w-full h-full">
			<div class="start-logo h-60">
				<img src="/cabinet-assets/label1.svg" style="background-color: transparent;">
			</div>
			<div class="waiting-preloader h-40">
				<Preloader :wait_text="this.$t('Please wait')+'...'"/>
                <div class="pt-3">
                    {{ $t('Please wait') }}...
                </div>
			</div>
		</div>        
    </div>

</template>

<script>
    import Preloader from '@/js/Components/PreloaderDotsLine.vue'

    export default {
        components: {
            Preloader
        },
        data() {
            return {
                isVisible: false
            }
        },
        mounted() {
            this.$emitter.on('show_loading_screen', () => {
                this.show();
            })
            this.$emitter.on('hide_loading_screen', () => {
                this.hide();
            })
        },
		methods: {
			show() {
                this.isVisible = true;
            },

            hide() {
                this.isVisible = false;
            }
		}
    }
	
</script>

<style lang="scss" scoped>

	.loading-screen {
		position: absolute;
		left: 0px;
		top: 0px;
		width: 100vw;
		// Статическая единица не учитывает тулбары iOS — экран загрузки выходит
		// за пределы вьюпорта; динамическая перекрывает её там, где понята.
		height: 100vh;
		height: 100dvh;
		color: #cccccc;
		z-index: 100;
		background-color: var(--background-color);
	}

    .line-preloader .dot {
        background-color: #cccccc!important;
    }

    .start-logo {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
    }

    .start-logo img {
        /* width: calc( 200px + (100vw - 320px) / (1920 - 320) * 100 ); */
        width: 200px;
    }

    .waiting-preloader {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

</style>
