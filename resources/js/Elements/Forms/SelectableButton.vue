<template lang="">

    <button v-if="singleAction"
        class="button flex items-center"
        :class="[ typeClass, size ? 'button-'+size : null ]"
        @click="typeof current_action.action === 'function' ? current_action.action($event) : $emit(current_action.action)"
        >
        <slot :current_action="current_action"/>
        <span class="action-name">{{ $t(current_action.name) }}</span>
    </button>

    <Dropdown v-else ref="dropdown" class="selectable-dropdown"
        :align="'left'"
        :direction="direction"
        :offset="offset"
        :area_radius="buttonRadius"
        :bg_color="bgColor"
        :downOnClick="false"
        :transition="'menu'"
        :buttonclass="'dropdown-button flex items-center p-0'"
        :dropareaclass="dropareaclass + (offset ? '' : (direction === 'up' ? ' rounded-b-none' : ' rounded-t-none'))"
        @cancel="() => {$emit('cancel')}"
        @changeState="(dd_state) => { state=dd_state }"
        >

        <template #button>

            <div class="button flex space-x-2 items-center pe-0" :class="[ typeClass, size ? 'button-'+size : null, (state && !offset) ? (direction === 'up' ? 'rounded-t-none' : 'rounded-b-none') : null ]">

                <span class='flex items-center pt-[1px]'
                    @click="typeof current_action.action === 'function' ? current_action.action($event) : $emit(current_action.action)"
                    >
                    <slot :current_action="current_action"/>
                    <span class="action-name">{{ $t(current_action.name) }}</span>
                </span>
                <div class="button-button h-[70%] border-l flex items-center ps-2 pe-3"
                    @click="$refs.dropdown.switchState()"
                    >
                    <Icon icon="gridicons:dropdown" class="icon icon-md !me-0"/>
                </div>
            </div>

        </template>

        <template #dropdownitems>

            <SelectableItems class="py-1"
                :in_data="actions_with_id"
                :text_field="'name'"
                :items_class="items_class"
                :size="size"
                :direction="direction"
                :keyboard="true"
                :bg_color="bgColor"
                :font_size="font_size"
                :padding_x="pxToken"
                @selectItem="(e, item)=>{
                    selected_id = item.id;
                    $refs.dropdown.close();
                    if (typeof item.action === 'function') item.action(e);
                }"
                @close="$refs.dropdown.close()"
                />

        </template>

    </Dropdown>

</template>

<script>
    import Dropdown         from '../Dropdown.vue';
    import SelectableItems  from './SelectableItems.vue';

    import { Icon }         from '@iconify/vue';

    export default {
        components: { Dropdown, Icon, SelectableItems },
        props: {
            actions: {
                type: Array,
                default: [{id:1, name:'Print', action:'Print'}]
            },
            dropareaclass: {
                type: String,
                default: ''
            },
            items_class: {
                type: String,
                default: ''
            },
            size: {
                type: String,
                default: ''
            },
            type: {
                type: String,
                default: 'primary'
            },
            // Vertical open direction of the actions dropdown, forwarded to Dropdown:
            // 'down' (default) or 'up' (for bottom-pinned buttons like the table FAB).
            direction: {
                type: String,
                default: 'down'
            },
            // Gap (px) between the button and the dropdown menu, forwarded to
            // Dropdown. 0 keeps the current attached look.
            offset: {
                type: Number,
                default: 0
            },
            // Overridable dropdown look — empty keeps the current defaults.
            bg_color: {
                type: String,
                default: ''
            },
            font_size: {
                type: String,
                default: ''
            },
        },
        data() {
            return {
                state: false,
                selected_id: 0,
                // source_data: [
                //     { id:1, name:'aaa'},
                //     { id:2, name:'aaa'},
                //     { id:3, name:'aaa'}
                // ]
            }
        },
        computed: {
            actions_with_id() {
                return this.actions.map( (item, index) => {
                    return {
                        id: index,
                        ...item
                    }
                })
            },
            current_action() {
                return this.actions[this.selected_id];
            },
            typeClass() {
                return (this.type || 'primary') + '-button';
            },
            // Same size tokens the button itself uses, so the dropdown area lines
            // up with it exactly — corner radius (--ui-radius-{size}) and content
            // padding (--ui-px-{size}). The base .button (no size) falls back to
            // Tailwind's rounded-lg / px-6.
            buttonRadius() {
                return this.size ? `var(--ui-radius-${this.size}, 0.5rem)` : '0.5rem';
            },
            pxToken() {
                return this.size ? `var(--ui-px-${this.size}, 1.5rem)` : '1.5rem';
            },
            // Same fallback SelectableItems uses internally — computed once here so
            // Dropdown's area and the items list always resolve to the identical
            // color, instead of syncing through two separate CSS rules that can
            // drift (e.g. when bg_color is overridden by a consumer).
            bgColor() {
                return this.bg_color || 'var(--selectable-background-color)';
            },
            singleAction() {
                return this.actions.length <= 1;
            }
        },
        mounted() {
        },
        beforeUnmount() {
        },
        beforeDestroy() {
        },
        methods: {
            // selectAction() {
            //     console.log('selectAction');
            //     this.;                
            // }
        }
    }
</script>

<style lang="scss">
    .button-button {
        border-color: #51738C; //var(--primary-button-hover-background);
    }
</style>