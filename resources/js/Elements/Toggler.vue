<template lang="">
    
    <div class="toggler-wrapper flex items-center">
        
        <button class="toggler"
            :class="[
                modelValue ? 'state_on' : 'state_off',
                disabled ? 'is-disabled' : ''
            ]"
            :disabled="disabled"
            @click="toggle()"
            >
            <span class="thumb">
                <Icon v-if="modelValue"  :icon="icon_on" class="icon icon-sm rounded-full"/>
                <Icon v-if="!modelValue" :icon="icon_off" class="icon icon-sm rounded-full"/>
            </span>
        </button>
    </div>

</template>

<script>
    import { Icon } from "@iconify/vue";

    export default {
        components: {Icon},
        props: {
            modelValue: {
                type: [Boolean, String],
                default: false,
            },
            icon_off: {
                type: String,
                default: ''
            },
            icon_on: {
                type: String,
                default: ''
            },
            disabled: {
                type: Boolean,
                default: false,
            },
            thumb_on_color: {
                type: String,
                default: 'var(--success-color)'
            },
            bg_on_color: {
                type: String,
                default: 'var(--success-color)'
            },
        },
        data: function() {
            return {
            }
        },
        mounted() {
        },
        methods: {
            toggle: function() {
                // console.log('toggle', this.modelValue);
                if (this.disabled)
                    return;
                this.$emit('update:modelValue', !this.modelValue);
                this.$emit('change');
                if (!this.modelValue)
                    this.$emit('enable')
                else 
                    this.$emit('disable')
            },
        }
    }
</script>

<style lang="scss" scoped>
    .toggler-wrapper {
        height: 40px;
    }

    .toggler {
        position: relative;      
        display: block; 

        height: 22px;
        min-height: 22px;
        max-height: 22px;

        width: 38px;

        border-radius: 16px;

        transition: border-color .25s !important;
        background-color: var(--toggler-background);
    }

    .thumb {
        position: absolute;
        top: 3px;
        left: 3px;

        width: 16px;
        height: 16px;
        border-radius: 50%;
        
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 1px 2px rgba(0, 0, 0, .06);
        display: flex;
        justify-content: center;
        align-items: center;
        transition: transform .25s, background-color .25s !important;

        background-color: var(--text-color);
    }

    .state_on.toggler {
        background-color: v-bind(bg_on_color);
    }

    .toggler.is-disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    .state_on .thumb {
        transform: translate(15px);            
    }

    .toggler-lg {
        .toggler {
            height: 32px!important;
            min-height: 32px!important;
            max-height: 32px!important;

            width: 52px!important;

            border-radius: 16px!important;
        }
        .thumb {
            top: 6px!important;
            left: 6px!important;
            width: 20px!important;
            height: 20px!important;
        }

        .state_on .thumb {
            transform: translate(20px)!important; 
        }
    }




</style>