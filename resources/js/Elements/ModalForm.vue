<template>

    <VueFinalModal 
        v-model="isOpen"
        class="modal d-center"
        :contentClass="[
            'modal-content v-center '+cardclasses,
            { disabled: $modal_inprogress.value }
        ]"
        :focusTrap="false"
        :clickToClose="outsideClickClose"
        :escToClose="escToClose"
        
        overlay-transition="vfm-fade"
        content-transition="vfm-fade"
        overlay-style="background-color: rgba(0, 0, 0, 0.7);"
        @beforeOpen="$emit('opened')"
        @opened="$emit('afterOpen')"
        >

            <!-- <div v-if="isSlimScreen || scrolled" class="scrolled-wrapper">
                <slot />
            </div> -->

            <slot />
            
            <!-- <div class="card-footer">
                <button v-if="!buttons.length" class="button btn-primary" @click="$emit('save')">{{ $t('Save') }}</button>
                <template v-else>
                    <button v-for="button in buttons" class="button" :class="button.classes" @click="$emit(button.event)">{{ $t(button.name) }}</button>
                </template>
                <button class="button" @click="close">{{ $t('Cancel') }}</button>
            </div> -->

    </VueFinalModal>

</template>
  
<script>
    import { VueFinalModal }    from 'vue-final-modal'
    import ScrolledWrapper      from '@/js/Elements/ScrolledWrapper.vue';

    export default {
        components: { VueFinalModal, ScrolledWrapper },
        props: {
            scrolled: {
                type: Boolean,
                default: false
            },
            escToClose: {
                type: Boolean,
                default: false
            },
            outsideClickClose: {
                type: Boolean,
                default: false
            },
            buttons: {
                type: Array,
                default: []
            },
            cardclasses: {
                type: String,
                default: ''
            }
        },
        emits: ['save', 'close', 'opened', 'afterOpen'],
        data() {
            return {
                isOpen: false,
                isSlimScreen: false,
            }
        },
        mounted() {
            this.isSlimScreen = (window.matchMedia('(max-width: 768px)')).matches
            if (this.escClose)
                window.addEventListener('keydown', this.handleEsc);
        },
        unmounted() {
            if (this.escClose)
                window.removeEventListener('keydown', this.handleEsc);
        },
        methods: {
            open() {
                this.isOpen = true;
                // this.$nextTick(() => {
                //     this.$emit('opened');
                // })
                
            },
            close() {
                this.isOpen = false;
            },
            handleEsc(event) {
                if (event.key === 'Escape' || event.keyCode === 27) {
                    this.isOpen = false; 
                }
            },
        }
    }
</script>

<style lang="scss">
    .modal {
        // Выше выезжающего бокового меню (.folded-menu — z-index 10000 на мобильных),
        // но ниже оверлея «в процессе» (_disabled_shared — z-index 100001).
        z-index: 10001;

        // Кабинетное боковое меню (SideMenu) реально занятую ширину пишет в эту
        // переменную (--cabinet-menu-width, 0px нигде кроме кабинета не задаётся).
        // Отступ сдвигает и оверлей (inset:0 считается от padding-box контейнера),
        // и центрируемый контент — модалка открывается посреди ОСТАВШЕЙСЯ area,
        // не наезжая на развёрнутое меню.
        padding-inline-start: var(--cabinet-menu-width, 0px);
        transition: padding-inline-start var(--gm-ease-dur, 250ms) var(--gm-ease, ease);

        // Предел ширины модалки: оставшаяся область справа от меню, с полем по краям.
        // Карточки берут ту же переменную (класс во всю ширину) — свои размеры в
        // единицах всего экрана иначе уводят их левым краем под меню.
        --modal-max-width: calc(90vw - var(--cabinet-menu-width, 0px));
    }

    .modal-content {
        position: relative;
        z-index: 10002;
        min-width: 300px;

        max-width: var(--modal-max-width, 90vw);
        // Статическая единица — фолбэк для Apple ниже 15.4.
        max-height: 95vh;
        max-height: 95dvh;

        /* padding: 20px;
        border-radius: 8px; */

        /* background: var(--background-light-color)!important;         */
    }

    // Карточка внутри не шире того же предела: собственная ширина в единицах всего
    // экрана иначе вылезает из ограниченного контейнера наружу.
    .modal-content > * {
        max-width: var(--modal-max-width, 90vw);
    }
</style>