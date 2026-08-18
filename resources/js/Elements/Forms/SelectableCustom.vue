<template lang="">

    <Dropdown ref="dropdown" class="selectable-dropdown" 
        :align="'left'" 
        :downOnClick="true"
        :transition="'dropdown'"
        :buttonclass="'dropdown-button flex items-center p-0'"
        :bg_color="bg_color"
        :dropareaclass="dropareaclass+' rounded-t-none'"
        @cancel="() => {$emit('cancel')}"
        >

        <template #button>
            <Input class="w-full pointer-events-none" 
                ref="inputRef"
                :inputtype="'select'"
                :value="text"        
                :input_class="input_class"                        
                :placeholder="$t(placeholder)"
                :isFloating="isFloating"
                :clearButton = "clearButton"
                :size        = "size"
                :placeholder_color = "is_mandatory ? 'var(--error-color)' : undefined"
                @clear       = "handleClear"
                @inputFocus  = "handleFocus"                 
                />
        </template>

        <template #dropdownitems="{ direction }">
            <!-- <div class="v-flex py-3 text-md"> -->
                <slot name="menuitems" :direction="direction"/>
            <!-- </div> -->
        </template>

    </Dropdown>

</template>

<script>
    import Dropdown from '@/js/Elements/Dropdown.vue';
    import Input    from './Input.vue';
    import {Icon}     from '@iconify/vue';

    export default {    
        components: { Dropdown, Input, Icon },
        props: {
            text: {
                type: String,
                default: ''
            },
            input_class: {
                type: String,
                default: ''
            },
            placeholder: {
                type: String,
                default: ''
            },
            isFloating: {
                type: Boolean,
                default: false
            },
            dropareaclass: {
                type: String,
                default: ''
            },
            // Список телепортируется в body, поэтому переменную с фона поля он не
            // унаследует — цвет области задаётся значением, а не наследованием.
            bg_color: {
                type: String,
                default: 'var(--selectable-background-color)'
            },
            clearButton: {
                type: Boolean,
                default: true
            },
            size: {
                type: String,
                default: ''
            },
            is_mandatory: {
                type: Boolean,
                default: false
            }
        },
        methods: {
            open() {
                this.$refs.dropdown.open();
            },
            close() {
                this.$refs.dropdown.close();
            },
            handleClear(event) {
                this.$emit('clear')
                this.$refs.inputRef.setFocusState(true)
            },
            handleFocus(event) {
                // console.log('SelCustom.handleFocus', event.type);               
                if ( this.$refs.inputRef.getFocusState() )
                    this.open();
            }
        }
    }
</script>

<style lang="scss">
    .selectable-dropdown {
        // Список примыкает снизу — выпрямляем смежные (нижние) углы поля ввода.
        .dropdown-button.opened .form-control {
            border-bottom-left-radius: 0px!important;
            border-bottom-right-radius: 0px!important;
        }

        // Разворот вверх: список примыкает сверху — инвертируем, выпрямляя верхние
        // углы и возвращая нижним обычное скругление.
        .dropdown-button.opened.open-up .form-control {
            border-top-left-radius: 0px!important;
            border-top-right-radius: 0px!important;
            border-bottom-left-radius: 0.5rem!important;
            border-bottom-right-radius: 0.5rem!important;
        }

        .dropdown-area {
            /* border-radius: var(--form-control-border-radius);
            border-top-left-radius: 0px;
            border-top-right-radius: 0px; */
            background-color: var(--selectable-background-color);
            border-width: 0px 1px 1px 1px;
            /* border-color: var(--form-control-border-color); */
            width: 100%;
        }
    }

</style>