<template lang="">

    <div class="input-container flex items-center" :class="{ 'is-filled': is_filled }" :placeholder="isFloating ? placeholder : null">
        
        <input 
            ref="input"
            :type="inputtype=='password' ? inputtype : 'text'"
            :value="text"

            class="form-control w-full pe-8"
            :class="[
                input_class,
                (inputtype || clearButton)  && !hasDisabledClass ? 'icon-after-padding' : '',
                isFloating && 'floating-input form-input',
                sizeClass
            ]"

            :placeholder="!isFloating ? $t(placeholder) : ''"
            :readonly="disabled ? true : false"
            @input  = "(e) => handleInput(e)"
            @change = "(e) => handleChange(e)" 
            @focus  = "(e) => handleFocus(e)"           
            :tabindex="hasDisabledClass ? -1 : 0"   
            :disabled="hasDisabledClass"
            
            @keydown.up.prevent = "$emit('keydown-up')"
            @keydown.down.prevent = "$emit('keydown-down')"

            @blur = "(e) => { $emit('blur', e) }"
            />

        <Icon v-if="inputtype=='search' && !text.length && !hasDisabledClass" icon="heroicons:magnifying-glass-16-solid" class="input-icon"/>
        <Icon v-if="inputtype=='select' && !text.length && !hasDisabledClass" icon="ep:arrow-down-bold" height="12px" class="input-icon"/>    
        <Icon v-if="inputtype=='select' && text.length && !clearButton && !hasDisabledClass" icon="ep:arrow-down-bold" height="12px" class="input-icon"/>    

        <Icon v-if="text.length && clearButton && !hasDisabledClass" icon="material-symbols:close-rounded" class="input-icon" @click.stop.prevent="clearInput"/>
    </div>

</template>

<script>
    import { Icon } from '@iconify/vue';

    export default {
        components: { Icon },
        props: {
            inputtype: {
                type: String,
                default: ''
            },
            value: {
                type: [String, Number],
                default: undefined,
            },
            modelValue: {
                type: [String, Number],
                default: '',
            },
            placeholder: {
                type: String,
                default: ''
            },
            isFloating: {
                type: Boolean,
                default: false
            },
            input_class: {
                type: String,
                default: ''
            },
            clearButton: {
                type: Boolean,
                default: true
            },
            size: {
                type: String,
                default: ''
            },
            disabled: {
                type: Boolean,
                default: false
            },
            placeholder_color: {
                type: String,
                default: 'var(--placeholder-color)'
            }
        },
        data() {
            return {
                observer: null,
                hasDisabledClass: false,
                preventFocusAction: false
            }
        },
        computed: {
            sizeClass() {
                return this.size ? `form-control-${this.size}` : '';
            },
            // Заполненность поля отмечается классом, а не селектором отношения:
            // подпись должна подниматься и на Apple ниже 15.4, где такой селектор
            // обнуляет всё правило целиком и подпись пропадает вовсе.
            is_filled() {
                return String(this.text ?? '').length > 0;
            },
            // iconSizeClass() {
            //     return this.size ? `icon-${this.size}` : '';
            // },
            text: {
                get() {
                    if (this.modelValue !== '' && this.modelValue !== null && this.modelValue !== undefined) 
                        return this.modelValue;
                    
                    return this.value !== undefined ? this.value : '';
                },
                set(newValue) {
                    this.$emit('update:modelValue', newValue);
                },
            },
        },
        mounted() {       
            this.observeParentClasses();
        },
        beforeUnmount() {
            if (this.observer) 
            this.observer.disconnect();
        },
        methods: {
            focus() {
				// console.log('Input.focus', this.$refs.input);
				             
                this.$refs.input.focus();
            },
            select() {  
                // console.log('sel');
                this.$nextTick(() => {
                    this.$refs.input.select();
                })                           
            },
            handleInput(event) {
                // console.log('hi');               
                const newValue = event.target.value;
                this.text = newValue; 
                // this.$emit('input', event)
            },
            handleChange(event) {
                // console.log('hc');
                // this.$emit('change', event.target.value);
                this.$emit('change', event);
            },
            handleFocus(event) {                
                this.$emit('inputFocus', event)
            },
            clearInput(event) {
                // console.log('clearInput');
                this.preventFocusAction = true;
                this.text = ''
                this.$refs.input.focus()
                this.handleChange(event)
                this.$emit('clear')
                this.$emit('clearInput')
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
            getFocusState() {
                // console.log('getFocusState');                
                return this.preventFocusAction ? false : true;
            },
            setFocusState(state) {
                // console.log('setFocusState');
                return this.preventFocusAction = state ? false : true;
            }
        },
    }
</script>

<style lang="scss" scoped>

    .input-container {
        position: relative;
        width:100%;
        /* @apply
            mt-2; */
        /* margin-top:0.5rem; */
        background: inherit!important;
    }

    .floating-input {
        /* width: 100%; */
        /* padding-top: 20px;
        padding-bottom: 6px; */
        padding-left: 12px;
        padding-right: 2rem;
        outline: none;
        box-sizing: border-box;

    }

    /* Атрибут подписи висит на самом контейнере и появляется только в плавающем
       режиме — этого достаточно, селектор отношения не нужен (и не поддерживается
       Apple ниже 15.4, где он обнулял бы правило вместе с подписью). */
    .input-container[placeholder]::before {
        content: attr(placeholder);
        position: absolute;
        z-index: 10;
        left: 12px;
        top: 50%;
        transform: translateY(-50%)!important; 
        color: var(--text-color-secondary);
        
        white-space: nowrap!important;
        transition: all 0.2s ease;
        pointer-events: none;
        font-size: var(--text-md);
        line-height: 1rem;
        
        width: calc( 100% - 2.5rem );
        overflow:hidden;        
    }

    .input-container[placeholder]:focus-within::before,
    .input-container[placeholder].is-filled::before {
        top: -11px!important;
        left: 10px;
        padding: 3px 1px;
        border-radius: 5px;
        font-size: var(--text-xs)!important;
        transform: translateY(0)!important;
        color: var(--text-color-disabled);
        width: auto;
        /* background: linear-gradient(to bottom, transparent 50%, var(--form-control-background) 50%); */
        /* background: transparent; */
        background-color: inherit!important;
        /* background-color: green!important; */
    }

    /* floating-input-container::placeholder {
        color: red!important;
    } */

    .form-control::placeholder {
        color: v-bind(placeholder_color);
    }

    // input::placeholder,
    // input::-webkit-input-placeholder,
    // input::-moz-placeholder,
    // input:-ms-input-placeholder {
    //     color: red!important;
    // }

    .input-icon {
        color: var(--icon-color, var(--text-color));
        @apply
            absolute
            right-3
            top-1/2
            transform
            -translate-y-1/2
            cursor-pointer
            pointer-events-auto;
    }

    .icon-after-padding {
        padding-right: 28px!important;
    }
</style>