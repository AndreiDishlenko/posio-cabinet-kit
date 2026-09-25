<template>

    <Head :title="$t(page_name ? page_name : ($page.props.currentPage?.name || 'Cabinet'))"/>

    <ProductTour
        v-if="showProductTour"
        @finished="onTourFinished"
    />

    <!-- Разовые подсветки не спорят с обучающим туром за внимание -->
    <SpotlightHints v-if="!showProductTour && $page.props.onboarding?.spotlight_hints" />

    <!-- Поздравление с первым чеком: показывается один раз, после обучения и
         не поверх него. -->
    <FirstReceiptCongrats v-if="!showProductTour && $page.props.first_receipt_congrats" />

    <div class="page-wrapper flex flex-row h-full overflow-y-hidden" :class="$inprogress.value ? 'disabled' : ''">
		<!-- scrollbar-thin -->

        <!-- Место под боковое меню: сама панель смонтирована вне страницы (иначе
             переход пересоздавал бы её вместе со страницей), поэтому ширину в
             потоке держит распорка по той же переменной. -->
        <div class="page-menu-space"></div>

        <div class="page-layout relative grow min-w-0 flex flex-col" >

            <CabinetHeader class="min-h-0 px-3 sm:px-5" :page_name="page_name" :header_title="header_title" :page_menu="page_menu">
                <template v-if="$slots.header_tools" #tools>
                    <slot name="header_tools" />
                </template>
            </CabinetHeader>

			<!-- Боковые поля живут на прокручиваемом слое, а не здесь: иначе полоса
			     прокрутки встаёт рядом с правым полем и сдвигает край содержимого. -->
			<div class="page-content-wrapper py-2 lg:py-4 flex flex-col overflow-hidden "
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
    import { Head, router } from '@inertiajs/vue3';
    
    import CabinetHeader    from "./CabinetHeader.vue"
    import CabinetBody      from "./CabinetBody.vue"
    import BottomTabBar     from "@/_admin/js/components/ui/Elements/BottomTabBar.vue"
    import ProductTour      from "./ProductTour.vue"
    import SpotlightHints     from "./SpotlightHints.vue"
    import FirstReceiptCongrats from "./FirstReceiptCongrats.vue"
    // import LoadingScreen    from "./LoadingScreen.vue"

    import Loader           from '@/js/Elements/PreloaderBars.vue';

    import { cabinetShellPresence } from './cabinetShellPresence.js'
    
    // import ModalForm        from '@/js/Elements/ModalForm.vue';
    // import InitCard         from '../Initial/InitCard.vue';

    export default {
        name: "CabinetLayout",
        // У шаблона несколько корневых узлов (Head + .page-wrapper) — это фрагмент,
        // поэтому Vue не может автоматически наследовать на него атрибуты (class и т.п.).
        // Отключаем авто-наследование, чтобы не было предупреждений Extraneous non-props attributes.
        inheritAttrs: false,
        components: { Head, CabinetHeader, CabinetBody, Loader, BottomTabBar, ProductTour, SpotlightHints, FirstReceiptCongrats },
        props: {
            page_name: {
                type: String,
                default: ''
            },
            // Название всей страницы для шапки — нужно страницам, которых нет в меню
            // кабинета: для них сервер название не знает.
            header_title: {
                type: String,
                default: ''
            },
            // Действия страницы, которые дополняют панель пользователя перед настройками:
            // { name, icon?, action | href, disabled?, in_burger? }.
            page_menu: {
                type: Array,
                default: () => []
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
        provide() {
            return {
                // Вложенные блоки (вкладки страницы) не видят шапку и регистрируют
                // свои действия здесь — забираются в момент открытия панели.
                pageMenuRegistry: {
                    register:   (source) => {
                        if ( !this.page_menu_sources.includes(source) )
                            this.page_menu_sources.push(source);
                    },
                    unregister: (source) => {
                        this.page_menu_sources = this.page_menu_sources.filter(item => item !== source);
                    },
                    collect:    () => this.collectPageMenu(),
                },
            }
        },
        data() {
            return {
                if_pause: false,
                page_menu_sources: [],
                // Тур запускаем при входе на любую страницу кабинета, пока пользователь
                // не прошёл первоначальное обучение (users.settings."tour_done"),
                // и только пока обучение вообще включено настройкой сервиса.
                // Сценарий привязан к пунктам меню (глобальны на всех страницах).
                showProductTour: !!this.$page.props.onboarding?.product_tour && !this.$page.props.user?.tour_done,
            }
        },
        watch: {
            disable_menu(value) {
                cabinetShellPresence.menu_disabled = value;
            },
            // Список первых шагов не спорит с обучающим туром за внимание, но
            // смонтирован вне страницы — о ходе обучения узнаёт только отсюда.
            showProductTour: {
                handler(value) {
                    cabinetShellPresence.tour_active = value;
                },
                immediate: true,
            },
        },
        methods: {
            // Вкладки страницы остаются смонтированными после переключения, поэтому
            // в меню попадают действия только той, что сейчас на экране.
            collectPageMenu() {
                return this.page_menu_sources
                    .filter(source => source.$el && typeof source.$el.getClientRects === 'function' && source.$el.getClientRects().length)
                    .flatMap(source => source.page_menu || []);
            },
            async onTourFinished(result) {
                this.showProductTour = false;
                // 'skipped' и '1' оба truthy — проверка tour_done не ломается,
                // а бэкенд по значению различает вехи tour_skipped / tour_finished
                await this.$apiClient.post(route('cabinet.api.user.setsetting'), { key: 'tour_done', value: result?.skipped ? 'skipped' : '1' });

                // Чеклист впервые появляется сразу после тура — сворачивание с прошлого
                // раза (если было) не должно скрывать его в этот ключевой момент. Снимаем
                // и отметку автопоказа: этот показ и есть тот единственный, после которого
                // список ждёт в углу свёрнутым.
                this.$settings.removeItem('checklist_collapsed');
                this.$settings.removeItem('checklist_auto_shown');

                // Список первых шагов отдаётся только прошедшим обучение — забираем
                // его состояние сразу, чтобы он появился без перехода на другую страницу.
                router.reload({ only: ['first_steps_checklist'] });
            },
        },
        created() {
            // Заявляем о меню до первой отрисовки: слой меню рендерится следом за
            // страницей и должен увидеть его нужным уже в этом кадре, иначе распорка
            // на мгновение схлопнется и контент дёрнется.
            cabinetShellPresence.attach(this.disable_menu);
        },
        mounted() {
            // this.$nextTick(() => {
            //     if ( this.$page.props.user?.new_user )
            //         this.$refs.welcomeCard.open()
            // });
        },
		beforeUnmount() {
            cabinetShellPresence.detach();
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
	
	// Ширину задаёт та же переменная, по которой меню считает занятое им место,
	// поэтому распорка сворачивается и разворачивается вместе с панелью, а на
	// мобильном (панель выезжает поверх) обнуляется.
	.page-menu-space {
		flex-shrink: 0;
		width: var(--cabinet-menu-width, 0px);
		transition: width var(--gm-ease-dur) var(--gm-ease);
	}

	.page-content-wrapper {
		// Поле по бокам содержимого страницы; раздаётся вниз, потому что его
		// правую половину занимает полоса прокрутки.
		--page-side-padding: 0.5rem;

		@media (min-width: 1024px) {
			--page-side-padding: 1rem;
		}

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

	.page-content-inner-scroller {
		padding-left:  var(--page-side-padding);
		padding-right: var(--page-side-padding);

		// Прокручиваемый вариант отдаёт правое поле под полосу прокрутки: она
		// стоит в поле, а не рядом с ним, поэтому зазоры слева и справа от
		// содержимого одинаковы. Полоса зарезервирована всегда, скачка нет.
		&.scrolled-wrapper {
			padding-right: calc( var(--page-side-padding) - var(--scrollbar-size-thin) );
		}
	}

	.page-bottom-spacer {
		min-height: var(--bottom-tab-bar-total);
	}
</style>
