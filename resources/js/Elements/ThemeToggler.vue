<template lang="">
    
    <button class="toggler form-control" 
        :class="[
            modelValue ? 'state_on' : 'state_off'
        ]" 
        @click="toggle()"
        >
        <span class="thumb">
            <Icon v-if="modelValue"  :icon="icon_on" class="thumb-icon"/>
            <Icon v-if="!modelValue" :icon="icon_off" class="thumb-icon"/>
        </span>
    </button>

</template>

<script>
    import { Icon } from "@iconify/vue";

    export default {
        components: {Icon},
        props: {
            modelValue: {
                type: [Boolean,String],
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
            thumb_color: {
                type: String,
                default: 'var(--color-green)'
            }
        },
        data: function() {
            return {
            }
        },
        mounted() {
        },
        methods: {
            toggle: function() {
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
    .toggler {
        /* position: relative; */
        border-radius: 11px;
        display: block;
        width: 40px;
        height: 23px!important;
        min-height: 23px!important;
        max-height: 23px!important;
        padding: 0px!important;
        top: 0px;
        /* flex-shrink: 0; */
        /* border: 1px solid var(--input-border-color); */
        /* background-color: rgba(142, 150, 170, .14); */
        transition: border-color .25s !important;
    }

    .toggler.state_on {
        /* border-color: green; */
    }

    .thumb {
        position: absolute;
        top: 2px;
        left: 2px!important;
        width: 17px;
        height: 17px;
        border-radius: 50%;
        background-color: var(--form-control-border-color);
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 1px 2px rgba(0, 0, 0, .06);
        display: flex;
        justify-content: center;
        align-items: center;
        transition: transform .25s, background-color .25s !important;
    }

    .state_on .thumb {
        transform: translate(17px);
        background-color: v-bind(thumb_color);        
    }

    .thumb-icon {
        width: 12px;
        max-width: 12px;
        height: 12px;
        max-height: 12px;
        border-radius: 50%;
    }

</style>