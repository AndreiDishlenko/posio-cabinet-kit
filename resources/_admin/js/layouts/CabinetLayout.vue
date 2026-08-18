<template>

    <Head :title="$t(page_name ? page_name : $page.props.currentPage.name)"/>

    <div class="page-wrapper flex flex-row h-full overflow-y-hidden" :class="$inprogress.value ? 'disabled' : ''">
		<!-- scrollbar-thin -->

        <CabinetMenu class="page-menu max-h-dvh-100 h-dvh-100 "
            :class="disable_menu ? 'disabled' : null"
            :disabled="disable_menu"
            ref="sideMenu"
            >
            Menu
        </CabinetMenu>

        <div class="page-layout relative grow min-w-0 flex flex-col" >

            <CabinetHeader class="min-h-0 px-3 sm:px-5" :page_name="page_name"/>

			<div class="page-content-wrapper p-2 lg:p-4 flex flex-col overflow-hidden " 
				:class="['space-y-'+space_y]"
				>
				<!-- scrollbar -->

				<div class="page-content-inner-scroller grow overflow-y-hidden flex flex-col"
					:class="{
						'scrolled-wrapper scrollbar scrollbar-thin' : scrolled
					}">
					<!-- flex -->

					<slot />

					<Loader v-if="if_pause" />

				</div>

			</div>
                
            <!-- <CabinetBody ref="cabinet_body" class="max-h-full cabinet-body grow border-yellow"> -->
            <!-- </CabinetBody> -->

			<BottomTabBar />

        </div>

    </div>

    <!-- <LoadingScreen ref="LoadingScreen"/> -->

    <!-- <ModalForm ref="welcomeCard" :escToClose="false" :outsideClickClose="false">
        <InitCard @close="$refs.welcomeCard.close()"/>
    </ModalForm> -->

</template>

<script>
    import { Head } from '@inertiajs/vue3';
    
    import CabinetMenu      from "./CabinetMenu.vue"
    import CabinetHeader    from "./CabinetHeader.vue"
    import CabinetBody      from "./CabinetBody.vue"
    import BottomTabBar     from "@/_admin/js/components/ui/Elements/BottomTabBar.vue"
    // import LoadingScreen    from "./LoadingScreen.vue"

    import Loader           from '@/js/Elements/PreloaderBars.vue';
    
    // import ModalForm        from '@/js/Elements/ModalForm.vue';
    // import InitCard         from '../Initial/InitCard.vue';

    export default {
        name: "CabinetLayout",
        // У шаблона несколько корневых узлов (Head + .page-wrapper) — это фрагмент,
        // поэтому Vue не может автоматически наследовать на него атрибуты (class и т.п.).
        // Отключаем авто-наследование, чтобы не было предупреждений Extraneous non-props attributes.
        inheritAttrs: false,
        components: { Head, CabinetMenu, CabinetHeader, CabinetBody, Loader, BottomTabBar },
        props: {
            page_name: {
                type: String,
                default: ''
            },
            disable_menu: {
                type: Boolean,
                default: false
            },
            space_y: {
                type: Number,
                default: 5
            },
			body_wrapper_classes: {
				type: String,
				default: ''
			},
			scrolled: {
				type: Boolean,
				default: true
			}
        },
        data() {
            return {
                if_pause: false,
            }
        },
        mounted() {
            // this.$nextTick(() => {
            //     if ( this.$page.props.user?.new_user ) 
            //         this.$refs.welcomeCard.open()
            // });
        },
		beforeUnmount() {
        },
        beforeDestroy() {
            // this.$emitter.off('pause_application')
            // this.$emitter.off('unpause_application')
        }
    }
</script>

<style lang="scss">
	.page-wrapper {
	// 	max-height: calc( 100dvh - 5px );
	// 	// border: 1px solid red;
		// height:100%;
	}
	
	.page-content-wrapper {
		// Отступ под BottomTabBar — только когда бар виден (телефон в портретной ориентации, ширина < md)
		@media (max-width: 767.98px) and (orientation: portrait) {
			// height: calc( 100% + var(--bottom-tab-bar-height) );
			// Считаем от полного занятого места, включая безопасный отступ под
			// индикатором жеста, — иначе последняя строка таблицы уходит под бар.
			padding-bottom: calc( var(--bottom-tab-bar-total) + 10px );
		}
		// border: 1px solid red;
		// padding-bottom: 200px;
		height: 100%;
		
	}

	.page-bottom-spacer {
		min-height: var(--bottom-tab-bar-total);
	}
</style>
