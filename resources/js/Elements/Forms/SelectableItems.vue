<template lang="">
    
    <!-- Items wrapper -->
    <div class="items-wrapper w-full v-flex"
        ref="wrapper"
        tabindex="0"
        :class="[
            isReversed ? 'reverse-up' : null,
        ]"
		@wheel.native.stop.prevent="smoothScroll"
        @keydown="onKeydown"
        >

        <!-- Row items -->
        <template v-for="(item, index) of in_data">
            <span class="select-item flex items-center space-x-2"
                :class="[
                    items_class,
                    index==effectiveIndex ? 'selected' : null,
                    item.disabled ? 'disabled' : null
                ]"
                @click.stop.prevent = "(e) => { $emit('selectItem', e, item) }"
                >
                    
                    <template v-if="custom_view">
                        <template v-for="key of Object.keys(item)">
                            <span v-if="key != 'id'">
                                {{ item[key] ? $t(item[key]) : item[key] }}
                            </span>
                        </template>
                    </template>
                    <template v-else>
                        <Icon v-if="item.icon" :icon="item.icon" class="icon"/>
                        <span>{{ item[this.text_field] ? $t(item[this.text_field]) : '' }}</span>
                    </template>

            </span>
        </template>

    </div>
</template>

<script>
    import { Icon } from '@iconify/vue';

    export default {
        components: { Icon },
        props: {
            'in_data': {
                type: Object,
                default: []
            },
            'text_field': {
				type: String,
				required: false,
				default: 'name'
			},
            'items_class': {
                type: String,
                default: ''
            },            
            'selected_index': {
                type: Number,
                default: null
            },
            'custom_view': {
                type: Boolean,
                default: false
            },
            'size': {
                type: String,
                default: 'base'
            },
            // Open direction of the parent dropdown: 'down' (list below the
            // button) or 'up' (list above it).
            'direction': {
                type: String,
                default: 'down'
            },
            // When the dropdown opens upward, render the list bottom-to-top so the
            // first item sits nearest the input (at the bottom). Opt-in — only the
            // search input wants this; plain selects keep their natural order.
            'reverse_when_up': {
                type: Boolean,
                default: false
            },
            // Opt-in self-managed keyboard navigation (up/down/enter/esc), the
            // default mechanism for dropdownitems that have no external input
            // driving selection (e.g. SelectableButton). Consumers that already
            // steer `selected_index` themselves (SelectableInput) keep this off.
            'keyboard': {
                type: Boolean,
                default: false
            },
            // Overridable background of the list. Empty -> current default token.
            'bg_color': {
                type: String,
                default: ''
            },
            // Overridable font size of the items. Empty -> current size-based token.
            'font_size': {
                type: String,
                default: ''
            },
            // Overridable horizontal padding of the items. Empty -> current 12px.
            // Consumers pass their button's --ui-px-{size} token so item text lines
            // up with the button text (e.g. SelectableButton).
            'padding_x': {
                type: String,
                default: ''
            }
        },
        data() {
            return {
                // Highlighted row when this component drives its own keyboard
                // navigation (keyboard=true). Otherwise selection is external.
                activeIndex: null,
            }
        },
        computed: {
            itemHeight() {
                const s = this.size || 'base';
                return `calc(var(--ui-h-${s}) + 2px)`;
            },
            fontSize() {
                if ( this.font_size )
                    return this.font_size;
                const s = this.size || 'base';
                return `var(--text-${s})`;
            },
            bgColor() {
                return this.bg_color || 'var(--selectable-background-color)';
            },
            paddingX() {
                return this.padding_x || '12px';
            },
            isReversed() {
                return this.reverse_when_up && this.direction === 'up';
            },
            // Which row is highlighted: internal when self-navigating, otherwise
            // the externally controlled `selected_index`.
            effectiveIndex() {
                return this.keyboard ? this.activeIndex : this.selected_index;
            }
        },
        watch: {
            // A reversed (upward) list lays index 0 out at the bottom, nearest the
            // input. When the results change (or the list flips up), the scroll
            // origin still sits at the top (last results) — pull it to the bottom
            // so the first, most relevant results are visible next to the input.
            in_data() {
                if ( this.isReversed )
                    this.scrollToNearest();
            },
            isReversed(val) {
                if ( val )
                    this.scrollToNearest();
            }
        },
        mounted() {
            // The list mounts fresh each time the dropdown opens (teleport v-if),
            // so grab focus here to receive keys straight away — no page jump.
            if ( this.keyboard )
                this.$nextTick(() => this.$refs.wrapper?.focus({ preventScroll: true }));
        },
        methods: {
            // Default keyboard mechanism (mirrors SelectableInput's up/down/enter/esc)
            // for dropdownitems that manage their own highlight.
            onKeydown(e) {
                if ( !this.keyboard )
                    return;

                switch ( e.key ) {
                    case 'ArrowDown': e.preventDefault(); e.stopPropagation(); this.move(1);          break;
                    case 'ArrowUp':   e.preventDefault(); e.stopPropagation(); this.move(-1);         break;
                    case 'Enter':     e.preventDefault(); e.stopPropagation(); this.enterActive(e);   break;
                    case 'Escape':    e.preventDefault(); e.stopPropagation(); this.$emit('close', e); break;
                }
            },
            // Move the highlight one visual step (visualDir: +1 down / -1 up),
            // skipping disabled rows and stopping at the edges. A reversed layout
            // (upward + reverse_when_up) flips which DOM index a visual step means.
            move(visualDir) {
                const items = this.in_data;
                const len   = items.length;
                if ( !len )
                    return;

                const step = this.isReversed ? -visualDir : visualDir;
                let idx = this.activeIndex == null ? (step > 0 ? -1 : len) : this.activeIndex;

                for ( let n = 0; n < len; n++ ) {
                    idx += step;
                    if ( idx < 0 || idx > len - 1 )
                        return;                       // hit an edge, no enabled row beyond
                    if ( !items[idx]?.disabled ) {
                        this.activeIndex = idx;
                        this.scrollToSelected();
                        return;
                    }
                }
            },
            enterActive(e) {
                // Nothing highlighted yet — highlight the first row (like SelectableInput).
                if ( this.activeIndex == null )
                    return this.move(1);

                const item = this.in_data[this.activeIndex];
                if ( !item || item.disabled )
                    return;

                this.$emit('selectItem', e, item);
            },
            scrollToNearest() {
                this.$nextTick(() => {
                    const wrapper = this.$refs.wrapper;
                    const scrollContainer = wrapper?.closest('.dropdown-area');
                    if ( scrollContainer )
                        scrollContainer.scrollTop = scrollContainer.scrollHeight;
                });
            },
            smoothScroll(e) {
                // Scrolling now happens on the ancestor .dropdown-area (Dropdown.vue),
                // not this wrapper itself — it no longer sets its own overflow.
                const scrollContainer = e.currentTarget.closest('.dropdown-area') || e.currentTarget;
                scrollContainer.scrollBy({
                    top: e.deltaY,
                    behavior: 'smooth'
                });
            },
            scrollToSelected() {
                this.$nextTick(() => {
                    const wrapper = this.$refs.wrapper;
                    if (!wrapper)
                        return false;

                    // Scrolling now happens on the ancestor .dropdown-area
                    // (Dropdown.vue) — this wrapper no longer sets its own overflow.
                    const scrollContainer = wrapper.closest('.dropdown-area') || wrapper;

                    const activeRow = wrapper.querySelector('.selected');
                    if (!activeRow)
                        return false;

                    // Прокручуємо лише всередині scrollContainer, не зачіпаючи батьківські
                    // контейнери. scrollIntoView() прокручував би сторінку/панель,
                    // коли виділений рядок виходить за нижню межу екрана, а цей
                    // зовнішній скрол закривав дропдаун (Dropdown.handleParentScroll).
                    const containerRect = scrollContainer.getBoundingClientRect();
                    const rowRect       = activeRow.getBoundingClientRect();

                    if (rowRect.top < containerRect.top)
                        scrollContainer.scrollTop -= containerRect.top - rowRect.top;
                    else if (rowRect.bottom > containerRect.bottom)
                        scrollContainer.scrollTop += rowRect.bottom - containerRect.bottom;
                });
            },
        }
    }
</script>

<style lang="scss" scoped>
    .items-wrapper {
        background-color: v-bind(bgColor);
        // Повторюємо форму батьківського .dropdown-area (Dropdown.vue) — той сам
        // рахує асиметричне заокруглення (area-up, rounded-t/b-none тощо), тож
        // items-wrapper просто успадковує вже готове значення для кожного кута.
        border-radius: inherit;
        @apply py-0;
    }

    // Upward dropdown: lay items out bottom-to-top so index 0 (the first result)
    // sits nearest the input at the bottom. Corner rounding is intentionally NOT
    // done here — the scroll container (.dropdown-area) clips content to its own
    // border-radius, so rounded corners stay put instead of scrolling with the list.
    .items-wrapper.reverse-up { flex-direction: column-reverse; }

    // Focus is set programmatically (mounted(), for keyboard-navigated lists) purely
    // to receive key events — the active row is already shown via .selected below,
    // so the native focus ring is just noise flashing in a moment after open().
    .items-wrapper:focus {
        outline: none;
    }

    .select-item {
        background-color: inherit;
        white-space: nowrap;
        cursor: pointer;
        height: v-bind(itemHeight);
        font-size: v-bind(fontSize);
        padding-left: v-bind(paddingX);
        padding-right: v-bind(paddingX);
        @apply py-1.5;
    }

    .select-item:hover {
        background-color: var(--selectable-hover-items)!important;
    }
    // .select-item:first-child {
    //     border-top-left-radius: 8px;
    //     border-top-right-radius: 8px;
    //     background-color: var(--first-item-bg);
    // }

    // .select-item:last-child {
    //     border-bottom-left-radius: 8px;
    //     border-bottom-right-radius: 8px;
    //     background-color: var(--last-item-bg);
    // }


    .selected {
        background-color: var(--selectable-hover-items)!important;
        // border: 1px solid yellow;
    }

    // .items-wrapper {
    //     border: 1px solid red;
    // }


</style>