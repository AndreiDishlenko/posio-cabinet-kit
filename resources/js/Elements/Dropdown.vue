<template lang="">

    <component :is="as" ref="dropdownwrapper" class="dropdown-wrapper" 
        @mouseenter="() => {if (!downOnClick && downOnHover) open()}" 
        @mouseleave="() => {if (!downOnClick && downOnHover) close()}"   
        >

        <div
            ref="dropdownbutton"
            class="dropdown-button "
            :class="[buttonclass, isMenuOpen && 'opened', computedDirection === 'up' && 'open-up']"
            title=""            
            aria-haspopup="true" 
            :aria-expanded="isMenuOpen"
            @click = "switchByClick()"            
            >
            <slot name="button"/>
        </div>

        <teleport v-if="isMenuOpen" to="body">

            <transition
                :appear="transition === 'menu'"
                :enter-active-class="transitionProps.enterActiveClass"
                :enter-from-class="transitionProps.enterFromClass"
                :enter-to-class="transitionProps.enterToClass"
                :leave-active-class="transitionProps.leaveActiveClass"
                :leave-from-class="transitionProps.leaveFromClass"
                :leave-to-class="transitionProps.leaveToClass"
                >

                <!-- Список телепортируется в body, поэтому его слой соперничает с плавающими
                     панелями (карточка бургер-меню, выезжающий лист): держим его выше них,
                     иначе выпадающий список открывается под собственной панелью. -->
                <div class="dropdown-area z-[1200] scrolled-wrapper scrollbar-thin"
                    tabindex="-1"
                    ref="dropdownarea"
                    :class="[
                        dropareaclass, isMenuOpen && 'opened',
                        computedDirection === 'up' && !offset && 'area-up',
                        // $modal_inprogress.value ? 'disabled' : ''
                        ]"
                    role="menu" 
                    :style="dropdownStyle" 
                    @wheel.stop=""     
                    >

                    <slot name="dropdownitems" :direction="computedDirection"/>
                    
                </div>

            </transition>
			
        </teleport>

    </component>

</template>

<script>
    export default {
        props: {
            defaultState: {
                type: Boolean,
                default: false
            },
            as: {
                type: String,
                default: 'div', 
                validator: (value) => ['div', 'li'].includes(value), 
            },
            align: {
                type: String,
                default: 'right',
            },
            // Vertical open direction: 'down' (default) drops below the button;
            // 'up' opens above it (for buttons pinned to the bottom of the screen,
            // e.g. the table FAB CTA).
            direction: {
                type: String,
                default: 'down',
            },
            title: {
                type: String,
                default: '',
            },           
            downOnHover: {
                type: Boolean,
                default: false
            },
            downOnClick: {
                type: Boolean,
                default: false
            },
            transition: {
                type: String,
                default: ''
            },
            buttonclass: {
                type: String,
                default: ''
            },
            dropareaclass: {
                type: String,
                default: ''
            },

            width: {
                type: String,
                default: '48',
            },
            // stick_side: {
            //     type: String,
            //     default: ''
            // },
            // top: {
            //     type: String,
            //     default: '100%'
            // },
            isChild: {
                type: Boolean,
                default: false
            },
			max_height: {
				type: Number,
				default: 0
			},
            // Вертикальний зазор (px) між кнопкою і дропдауном. Для 'down' зсуває
            // список нижче нижнього краю кнопки, для 'up' — вище верхнього.
            offset: {
                type: Number,
                default: 0
            },
            // Corner radius of the drop area (any CSS length). Empty keeps the
            // default 0.375rem. Consumers pass their button's radius so the menu
            // matches it (e.g. SelectableButton).
            area_radius: {
                type: String,
                default: ''
            },
            // Overridable background of the drop area. Empty keeps the CSS default
            // (var(--dropdown-background-color)). Set as an inline style (like
            // area_radius) so it always wins — a global `.dropdown-area {...}` rule
            // in a consumer's own <style> block races other such rules on cascade
            // order and can silently lose, leaving the area out of sync with its
            // content's actual background (e.g. SelectableButton's items list).
            bg_color: {
                type: String,
                default: ''
            },
            // Overridable z-index of the drop area (inline style, wins over the
            // default z-[1200] utility class). Empty keeps the default. Needed for
            // anchors that themselves sit above that layer (e.g. the cabinet
            // side menu), otherwise their own dropdown would render underneath them.
            area_zindex: {
                type: [Number, String],
                default: ''
            }
            // disableDefaultAction: {
            //     type: Boolean,
            //     default: false
            // }
        },
        data() {
            return {
                isMenuOpen: false,
                observer: null,
                hasDisabledClass: false,

                area_top: 0,
                area_button_top: 0,
                area_left: 0,
                area_right: 0,
                area_width: 0,

                // Actual direction/align used for the current opening — may differ
                // from the `direction`/`align` props when auto-flipped (see
                // resolveDirection() / resolveAlign()).
                computedDirection: this.direction,
                computedAlign: this.align,
            }
        },
        computed: {
            // Transition classes for the drop area. The default keeps the previous
            // behaviour untouched for every existing consumer; transition="menu"
            // opts into the Material `mat-menu` motion (scale 0.8 -> 1 + fade,
            // origin at the anchor corner) — used by SelectableButton.
            transitionProps() {
                if ( this.transition === 'menu' ) {
                    return {
                        enterActiveClass: 'dropdown-menu-enter-active',
                        enterFromClass:   'dropdown-menu-enter-from',
                        enterToClass:     'dropdown-menu-enter-to',
                        leaveActiveClass: 'dropdown-menu-leave-active',
                        leaveFromClass:   'dropdown-menu-leave-from',
                        leaveToClass:     'dropdown-menu-leave-to',
                    };
                }

                return {
                    enterActiveClass: 'transition ease-out duration-100',
                    enterFromClass:   this.computedStyles.leavefromclass,
                    enterToClass:     this.computedStyles.entertoclass,
                    leaveActiveClass: 'transition ease-in duration-75',
                    leaveFromClass:   this.computedStyles.leavefromclass,
                    leaveToClass:     this.computedStyles.leavetoclass,
                };
            },
            // Anchor the mat-menu scale to the corner nearest the button, so it
            // unfolds from the button regardless of the resolved direction/align.
            menuTransformOrigin() {
                const vertical   = this.computedDirection === 'up'    ? 'bottom' : 'top';
                const horizontal = this.computedAlign     === 'right' ? 'right'  : 'left';
                return `${vertical} ${horizontal}`;
            },
            computedStyles() {
                let dropdown_enterfromclass = 'transform opacity-0 scale-95'    + (this.computedAlign === 'left' ? ' -translate-x-4 -translate-y-4' : '');
                let dropdown_entertoclass   = 'transform opacity-100 scale-100' + (this.computedAlign === 'left' ? ' translate-x-0 translate-y-0' : '');
                let dropdown_leavefromclass = 'transform opacity-100 scale-100' + (this.computedAlign === 'left' ? ' translate-x-0 translate-y-0' : '');
                let dropdown_leavetoclass   = 'transform opacity-0 scale-95'    + (this.computedAlign === 'left' ? ' -translate-x-4 -translate-y-4' : '');

                if ( this.transition=='dropdown' ) {
                    let dropdown_enterfromclass = 'opacity-0 -translate-y-4';
                    let dropdown_entertoclass = 'opacity-100 translate-y-0';
                    let dropdown_leavefromclass = 'opacity-100 translate-y-0';
                    let dropdown_leavetoclass = 'opacity-0 -translate-y-4';
                }

                return {
                    left: this.computedAlign === 'left' ? '0px' : 'auto',
                    right: this.computedAlign === 'right' ? '0px' : 'auto',
                    enterfromclass: dropdown_enterfromclass,
                    entertoclass: dropdown_entertoclass,
                    leavefromclass: dropdown_leavefromclass,
                    leavetoclass: dropdown_leavetoclass,
                };
            },
            dropdownStyle() {
                const viewportWidth = window.innerWidth
                const viewportHeight = window.innerHeight

                let x_position = {};
                const edge_margin = 5;

                if (this.computedAlign == "left") {
                    // Check right edge
                    let adjustedLeft = this.area_left
                    if (adjustedLeft + this.area_width > viewportWidth - edge_margin)
                        adjustedLeft = viewportWidth - this.area_width - edge_margin
                    // Предохранитель: якір близько до лівого краю екрана — не дати піти в мінус.
                    if (adjustedLeft < edge_margin)
                        adjustedLeft = edge_margin
                    x_position.left = adjustedLeft + 'px';
                } else {
                    let adjustedRight = viewportWidth - this.area_right
                    // Предохранитель: якір близько до лівого краю екрана — вміст (що росте
                    // вліво від правого краю) не повинен вилазити за лівий край екрана.
                    if (viewportWidth - adjustedRight - this.area_width < edge_margin)
                        adjustedRight = viewportWidth - this.area_width - edge_margin
                    if (adjustedRight < edge_margin)
                        adjustedRight = edge_margin
                    x_position.right = adjustedRight + 'px';
                }
                
                const open_up = this.computedDirection === 'up';

                // Available vertical space and the anchor edge depend on direction:
                // 'down' grows below the button's bottom; 'up' grows above its top.
                let dropdownHeight = 655
                if ( open_up ) {
                    if ( this.area_button_top < dropdownHeight )
                        dropdownHeight = this.area_button_top - 10
                } else if ( this.area_top + dropdownHeight > viewportHeight ) {
                    dropdownHeight = viewportHeight - this.area_top - 10
                }

				dropdownHeight = this.max_height && dropdownHeight > 200 ? this.max_height : dropdownHeight

                // 'up': anchor the dropdown's bottom edge to the button's top edge
                // by placing its top there and shifting it up by its own height.
                // offset розсуває список від кнопки: вниз (+) для 'down', вгору (−) для 'up'.
                const y_position = open_up
                    ? { top: (this.area_button_top - this.offset) + 'px', transform: 'translateY(-100%)' }
                    : { top: (this.area_top + this.offset) + 'px' };

                return {
                    position: 'absolute',
                    // zIndex: 9999,
                    ...y_position,
                    ...x_position,
                    // left: adjustedLeft + 'px',
                    minWidth: this.area_width + 'px',
                    maxHeight: dropdownHeight + 'px',
                    ...(this.transition === 'menu' ? { transformOrigin: this.menuTransformOrigin } : {}),
                    ...(this.area_radius ? { '--dropdown-area-radius': this.area_radius } : {}),
                    ...(this.bg_color ? { backgroundColor: this.bg_color } : {}),
                    ...(this.area_zindex ? { zIndex: this.area_zindex } : {})
                }
            }
        },
        mounted() {
            // Добавляем слушатель события клика на document           
            // document.addEventListener('click', this.handleClickOutside);
            // document.addEventListener('scroll', this.handleClickOutside);
            this.$emitter.on('close_dropdowns', ({el, isChild}) => { 
                // console.log('close_dropdowns', el, isChild);  
                if ( el != this && !isChild && this.isMenuOpen ) 
                    this.close()
            });

            this.observeParentClasses();
			
        },
        beforeUnmount() {
            this.$emitter.off('close_dropdowns'); 
            document.removeEventListener('mousedown', this.handleClickOutside);
            document.removeEventListener('scroll', this.handleClickOutside);
			this.removeScrollListeners();

            if (this.observer) 
                this.observer.disconnect();

        },
        beforeDestroy() {
			this.removeScrollListeners();
        },
        methods: {
            isOpened() {
                return this.isMenuOpen;
            },
            open(e) {
                // console.log('Dropdown.open')
                this.$emitter.emit('close_dropdowns', {el: this, isChild: this.isChild});
                this.isMenuOpen=true;

                this.updatePosition()

                this.$nextTick(() => {
                    document.addEventListener('mousedown', this.handleClickOutside);
                    document.addEventListener('scroll', this.handleClickOutside);
					this.addScrollListeners();
                    // console.log('ff', this.$refs.dropdownarea);
					// area_width спочатку береться з кнопки-якоря (updatePosition) — цього
					// замало, якщо реальний вміст дропдауна ширший за кнопку. Домірюємо його
					// тут, щоб edge-guard в dropdownStyle рахував відступи від справжньої ширини.
					if ( this.$refs.dropdownarea ) {
						this.area_width = Math.max(this.area_width, this.$refs.dropdownarea.scrollWidth);
						// Ширину контенту ми знаємо лише тепер — перевіряємо align ще раз
						// на її основі (початкова оцінка в updatePosition() бралась із
						// ширини кнопки-якоря, яка може бути вужчою за реальний вміст).
						this.computedAlign = this.resolveAlign();
					}
                    this.$emit('changeState', this.isMenuOpen)
					this.$emit('opened')
                });
            },
            close() {
                // console.log('Dropdown.close')   
                this.isMenuOpen=false;
                this.$nextTick(() => {                    
                    document.removeEventListener('mousedown', this.handleClickOutside);
					this.removeScrollListeners();
                    this.$emit('changeState', this.isMenuOpen)
					this.$emit('closed')
                }); 
            },
            focusOut(e) {
				console.log('Dropdown.focusOut');
				
                // console.log('e', this.$refs.dropdownwrapper);                
                // if (this.$refs.dropdownwrapper && this.$refs.dropdownwrapper.contains(e.target))
                //     return true;
                this.close()
            },
            switchState() {
                if ( !this.isMenuOpen )
                    this.open()
				else 
                    this.close() 
            },
            switchByClick() {
                // console.log('sbc', this.downOnClick);               
                if ( this.downOnClick ) 
                    this.switchState()

            },
            cancel() {
                // console.log('cancel');                
                this.close();
                this.$emit('cancel');
            },
            handleClickOutside(event) {
                // console.log('scroll', event.type, event.target, this.$refs.dropdownarea.contains(event.target));
                if (
                    event.type=="mousedown" && 
                    this.$refs.dropdownwrapper && 
                    !this.$refs.dropdownwrapper.contains(event.target) &&
                    this.$refs.dropdownarea && 
                    !this.$refs.dropdownarea.contains(event.target) &&
                    event.target.closest('.dropdown-area') == null      // Do not close dropdown in dropdown
                ) 
                    this.cancel();

                if (
                    event.type=="scroll" && 
                    this.$refs.dropdownwrapper && 
                    !this.$refs.dropdownwrapper.contains(event.target) &&
                    this.$refs.dropdownarea && 
                    !this.$refs.dropdownarea.contains(event.target)
                ) 
                    this.cancel();
            },
            observeParentClasses() {
                const target = this.$refs.input;
                if (!target) return;

                const checkDisabledInParents = (el) => {
                    while (el && el !== document.body) {
                        if (el.classList && el.classList.contains('disabled')) {
                            return true;
                        }
                        el = el.parentElement;
                        }
                    return false;
                };

                const updateState = () => {
                    this.hasDisabledClass = checkDisabledInParents(target);
                };

                this.observer = new MutationObserver(updateState);
                
                let el = target.parentElement;
                while (el && el !== document.body) {
                    this.observer.observe(el, { attributes: true, attributeFilter: ['class'] });
                    el = el.parentElement;
                }

                updateState(); 
            },
            updatePosition() {
                // console.log('updatePosition', this.stick_side);

                const rect = this.$refs.dropdownbutton.getBoundingClientRect()
                // console.log('source_rect', rect.left, rect.right, document.documentElement.clientWidth);

                let scrollbar_width = window.innerWidth - document.documentElement.clientWidth

                this.area_top = rect.bottom + window.scrollY
                this.area_button_top = rect.top + window.scrollY
                this.area_left = rect.left + window.scrollX + scrollbar_width
                this.area_right = rect.right + window.scrollX + scrollbar_width + 1
                this.area_width = rect.width

                this.computedDirection = this.resolveDirection(rect);
                this.computedAlign     = this.resolveAlign();
            },
            // Auto-flip 'down' -> 'up' when there isn't enough room below the
            // button (e.g. a table row near the bottom of a short screen), so the
            // list opens where more space is available instead of overflowing off
            // the viewport. An explicit direction="up" (e.g. the table FAB button)
            // is always respected as-is.
            resolveDirection(rect) {
                if ( this.direction === 'up' )
                    return 'up';

                const edge_margin = 10;
                const min_open_height = 150;

                const spaceBelow = window.innerHeight - rect.bottom - edge_margin;
                const spaceAbove = rect.top - edge_margin;

                if ( spaceBelow < min_open_height && spaceAbove > spaceBelow )
                    return 'up';

                return 'down';
            },
            // Auto-flip 'left' <-> 'right' when the natural anchor would push the
            // list past the opposite screen edge and the flipped anchor actually
            // fits — mirrors resolveDirection() but for the horizontal axis. Uses
            // area_left/area_right/area_width (already in updatePosition()'s
            // coordinate space); re-run once more after the real content width is
            // known (see the nextTick in open()), since the initial area_width is
            // only the anchor button's width.
            resolveAlign() {
                const edge_margin = 10;
                const viewportWidth = window.innerWidth;

                if ( this.align === 'left' ) {
                    const overflowsRight = (this.area_left + this.area_width) > (viewportWidth - edge_margin);
                    const flippedFits    = (this.area_right - this.area_width) >= edge_margin;
                    return (overflowsRight && flippedFits) ? 'right' : 'left';
                }

                const overflowsLeft = (this.area_right - this.area_width) < edge_margin;
                const flippedFits   = (this.area_left + this.area_width) <= (viewportWidth - edge_margin);
                return (overflowsLeft && flippedFits) ? 'left' : 'right';
            },

			addScrollListeners() {
				this._scrollParents = [];
				let el = this.$refs.dropdownwrapper?.parentElement;
				while (el && el !== document.body) {
					const overflow = getComputedStyle(el).overflow + getComputedStyle(el).overflowY;
					if (/auto|scroll/.test(overflow)) {
						el.addEventListener('scroll', this.handleParentScroll, { passive: true });
						this._scrollParents.push(el);
					}
					el = el.parentElement;
				}
				window.addEventListener('scroll', this.handleParentScroll, { passive: true });
				document.addEventListener('scroll', this.handleParentScroll, { passive: true, capture: true });
			},

			removeScrollListeners() {
				(this._scrollParents || []).forEach(el => {
					el.removeEventListener('scroll', this.handleParentScroll);
				});
				this._scrollParents = [];
				window.removeEventListener('scroll', this.handleParentScroll);
				document.removeEventListener('scroll', this.handleParentScroll, true);
			},

			// handleParentScroll() {
			// 	console.log('handleParentScroll');
				
			// 	if (this.isMenuOpen) 
			// 		this.updatePosition();
			// },

			
			handleParentScroll(event) {
				if (!this.isMenuOpen) return;

				const target = event?.target;
				if (
					(target && this.$refs.dropdownarea?.contains(target)) ||
					(target && this.$refs.dropdownwrapper?.contains(target))
				) {
					return;
				}

				this.cancel();
			},
        }
    }
</script>

<style lang="scss">
    .dropdown-wrapper {
        position: relative;
        // background: inherit!important;
    }

    .dropdown-button {
        cursor: pointer;
        background: inherit!important;
    }

    .dropdown-button.opened {
        border-radius: 0.375rem 0.375rem 0 0 ;
    }

    // Opening upward: square the top corners instead, so the button joins the
    // dropdown sitting above it into one cohesive element.
    .dropdown-button.opened.open-up {
        border-radius: 0 0 0.375rem 0.375rem;
    }

    .dropdown-area {
        // min-width: 100%;
        position: fixed;
        // z-index: 10;

        display: flex;
        flex-direction: column;
        align-items: stretch;

        // top: v-bind(top);
        left: 0;

        background-color: var(--dropdown-background-color);

        left: v-bind('computedStyles.left');
        right: v-bind('computedStyles.right');

        transform-origin: top right;
        border-radius: var(--dropdown-area-radius, 0.375rem);

        --tw-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --tw-shadow-colored: 0 10px 15px -3px var(--tw-shadow-color), 0 4px 6px -4px var(--tw-shadow-color);
        box-shadow: var(--tw-ring-offset-shadow, 0 0 #0000), var(--tw-ring-shadow, 0 0 #0000), var(--tw-shadow);
        
        --tw-ring-color: rgb(0 0 0 / 0.05);
        --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
        --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
        box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
    }

    // Всплывающий список сам подбирает высоту под содержимое, поэтому место под
    // полосу резервируется только когда она реально нужна: постоянный запас
    // выглядит пустой колонкой подложки справа. Селектор из двух классов — чтобы
    // перебить роль контейнера прокрутки независимо от порядка подключения стилей.
    .dropdown-area.scrolled-wrapper {
        overflow-y: auto;
    }

    // Opening upward: the list sits above the button, so round the TOP corners
    // and square the bottom ones to merge with the button below it. Explicit
    // longhand + !important so it beats the consumer's `rounded-t-none` utility
    // (which is meant for the downward case). The container clips its scrolling
    // content to these corners, so they stay put instead of scrolling with the list.
    .dropdown-area.area-up {
        border-top-left-radius: var(--dropdown-area-radius, 0.375rem) !important;
        border-top-right-radius: var(--dropdown-area-radius, 0.375rem) !important;
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
    }

    /* .dropdown-area.opened {
        border-radius: 0 0 0.375rem 0.375rem;
    } */

    .dropdown-item {
        display: flex;
        justify-content: left;
        align-items: center;
    }

    // Material `mat-menu` open/close motion (transition="menu"). The area scales
    // up from the anchor corner (transform-origin set inline per direction/align)
    // while fading in; closing just fades out. Mirrors Gemini's gem-menu unfold.
    .dropdown-menu-enter-active {
        transition:
            opacity   120ms cubic-bezier(0, 0, 0.2, 1),
            transform 120ms cubic-bezier(0, 0, 0.2, 1);
    }

    .dropdown-menu-enter-from {
        opacity: 0;
        transform: scale(0.8);
    }

    .dropdown-menu-enter-to {
        opacity: 1;
        transform: scale(1);
    }

    .dropdown-menu-leave-active {
        transition: opacity 100ms linear 25ms;
    }

    .dropdown-menu-leave-from {
        opacity: 1;
    }

    .dropdown-menu-leave-to {
        opacity: 0;
    }
</style>
