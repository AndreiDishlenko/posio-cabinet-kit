
<template>

	<!-- Table scrolled wrapper -->

	 <!-- hide-scrollbar scrollbar -->
	<div ref="wrapperEl" class="t-wrapper flex h-full w-full grow min-h-0 flex-col scrollbar-thin items-stretch !relative"
        :class="{
				// 'overflow-y-auto': scrolled && !sticky_header,
				// 'rounded-table' : rounded,
				'fit-container': fit_container,
				// Horizontal scroll mode: own scroll box (x + y) so wide tables
				// compact and scroll instead of truncating their cells.
				'overflow-x-auto x-scroll': x_scroll,
				// Vertical-only scroll box: the body scrolls inside the table's own
				// height and the header sticks to it (x_scroll already covers both axes).
				'overflow-y-auto y-scroll': y_scroll && !x_scroll
			}">
        
        <div class="t-block grid" 
            :class="{ 
					table_classes, 
					// 'rounded-table' : rounded 
				}" 
            :style="columnsWidthes"
            >
			<!-- h-full grow min-h-0-->
			 <!-- w-full content-start  -->
            <slot />

        </div>

		<slot name="centred-button" />

    </div>

</template>

<script>
    export default {
        emits: ['scrolledChange'],
        props: {
            settings: {
                type: Object,
                default: {}
            },
            seenColumns: {
                type: Array,
                default: []
            },
            scrolled: {
                type: Boolean,
                default: true
            },
			sticky_header: {
                type: Boolean,
                default: true
            },
            rounded: {
                type: Boolean,
                default: false
            },
            table_classes: {
                type: String,
                default: ''
            },
            row_header: {
                type: Boolean,
                default: false
            },
			fit_container: {
				type: Boolean,
				default: false
			},
			// Non-empty enables the subordinate-rows toggle column.
			slave_key: {
				type: String,
				default: ''
			},
			// Adds the leading selection-checkbox column to the grid track list.
			selector: {
				type: Boolean,
				default: false
			},
			// Adds the trailing rowbar column to the grid track list (computed by Table).
			show_rowbar: {
				type: Boolean,
				default: false
			},
			// Horizontal-scroll mode: columns size to their content (min-content floor)
			// so the table stays as compact as possible without truncating; the wrapper
			// scrolls left/right once the total width exceeds the available space.
			x_scroll: {
				type: Boolean,
				default: false
			},
			// Vertical-scroll mode: the body scrolls inside the table's own bounded
			// height instead of pushing the page scroller, so several tables side by
			// side scroll independently and each keeps its header pinned to its top.
			y_scroll: {
				type: Boolean,
				default: false
			}
        },
        data() {
            return {
                // The element that actually scrolls (this .t-wrapper or an ancestor
                // page scroller, depending on table config) and whether it's past
                // the threshold. Owned here so Table only consumes the emitted state.
                scrollContainer: null,
                isScrolled: false,
            }
        },
        computed: {
            columnsWidthes() {
				// console.log('colWidthes', this.seenColumns);

                let widthes = [];

                // Row header width — content-sized (min-content) with a minimum floor
                // so it stays compact for single-digit indexes but grows for wider ones.
                if ( this.row_header )
                    widthes.push( 'minmax(25px, min-content)' )

				// Selection checkbox column (matches TableHeader / row order)
				if ( this.selector )
					widthes.push( 'min-content' )

				// Slave toggle column
				if ( this.slave_key )
					widthes.push( 'min-content' )

                this.seenColumns?.forEach(column => {
                    // console.log('column', column, this.colWidth(column));                    
                    widthes.push( this.colWidth(column) );
                });

                // Rowbar width
                if (this.show_rowbar)
                    widthes.push( 'min-content' )

				let gridTemplated = {
                    'grid-template-columns': widthes.join(' ')
                };
				
				// console.log('grid templates', gridTemplated);				

                return gridTemplated;
            },
        },
        mounted() {
            this.$nextTick(() => this.setupScrollWatch());
        },
        updated() {
            this.$nextTick(() => this.setupScrollWatch());
        },
        beforeUnmount() {
            this.teardownScrollWatch();
        },
        methods: {
            // ─── Scroll tracking ────────────────────────────────────────
            // The actual scroll box differs per config: with the default it's this
            // .t-wrapper; with sticky_header the table grows and an ancestor page
            // scroller (.scrolled-wrapper) scrolls instead. Walk up from this root
            // to find the nearest scrollable element so it works in both modes.
            findScrollContainer() {
                // TableWrapper.findScrollContainer
                let el = this.$refs.wrapperEl;
                while ( el && el !== document.body && el !== document.documentElement ) {
                    if ( !(el instanceof Element) ) {
                        el = el.parentElement;
                        continue;
                    }

                    // The cabinet page scroller is marked .scrolled-wrapper; accept it
                    // explicitly so detection doesn't hinge on the overflow cascade.
                    if ( el.classList.contains('scrolled-wrapper') )
                        return el;

                    const overflow_y = window.getComputedStyle(el).overflowY;
                    if ( overflow_y === 'auto' || overflow_y === 'scroll' )
                        return el;
                    el = el.parentElement;
                }
                return null;
            },
            // Idempotent: re-running on update() rebinds only if the element changed.
            setupScrollWatch() {
                // TableWrapper.setupScrollWatch
                const el = this.findScrollContainer();
                if ( !el || this.scrollContainer === el )
                    return;

                this.teardownScrollWatch();
                this.scrollContainer = el;
                el.addEventListener('scroll', this.onScroll, { passive: true });
                this.onScroll();
            },
            teardownScrollWatch() {
                // TableWrapper.teardownScrollWatch
                if ( !this.scrollContainer )
                    return;

                this.scrollContainer.removeEventListener('scroll', this.onScroll);
                this.scrollContainer = null;
            },
            onScroll() {
                // TableWrapper.onScroll
                const el = this.scrollContainer;
                if ( !el )
                    return;

                const next = el.scrollTop > 200;
                if ( next !== this.isScrolled ) {
                    this.isScrolled = next;
                    this.$emit('scrolledChange', next);
                }
            },
            // Public: smooth-scroll the detected container back to the top.
            scrollToTop() {
                // TableWrapper.scrollToTop
                this.scrollContainer?.scrollTo({ top: 0, behavior: 'smooth' });
            },
            colWidth(column) {
                if (column.show=='xs' && (window.matchMedia('(max-width: 480px)')).matches)
                    return '0px'
                if (column.show=='sm' && (window.matchMedia('(max-width: 640px)')).matches)
                    return '0px'
                if (column.show=='md' && (window.matchMedia('(max-width: 768px)')).matches)
                    return '0px'
                if (column.show=='lg' && (window.matchMedia('(max-width: 1024px)')).matches)
                    return '0px'
                if (column.show=='xl' && (window.matchMedia('(max-width: 1280px)')).matches)
                    return '0px'
                if (column.show=='xxl' && (window.matchMedia('(max-width: 1536px)')).matches)
                    return '0px'

                // Ширина від заданого px-значення (мінімум) до 1fr (максимум):
                // width: '300px-1fr' → minmax(300px, 1fr). Підтримує px/rem/em/%.
                // Обробляємо до обгортки fit_container нижче, інакше вийде
                // невалідний minmax(0, 300px-1fr) і вся grid-template-columns відкидається.
                const widthFromTo = String(column.width).match(/^(\d+(?:\.\d+)?(?:px|rem|em|%)?)-1fr$/);
                if (widthFromTo)
                    return `minmax(${widthFromTo[1]}, 1fr)`;

                // Horizontal-scroll mode: content-sized tracks with a min-content floor
                // (compact, never truncated) that still stretch to fill via 1fr when
                // there's room. Once the sum exceeds the wrapper, it scrolls instead.
                if (this.x_scroll) {
                    if (column.width=='min')
                        return 'min-content';
                    if (column.width=='auto-1/2')
                        return 'minmax(min-content, 0.5fr)';
                    if (column.width=='auto' || !column.width)
                        return 'minmax(min-content, 1fr)';
                    if (String(column.width).endsWith('fr'))
                        return `minmax(min-content, ${column.width})`;
                    return column.width;
                }

                if (column.width=='auto-1/2')
                    return 'minmax(0, 0.5fr)';

                if (column.width=='auto' || !column.width)
                    return 'minmax(0, 1fr)';

                if (column.width=='min')
                    return 'minmax(0, min-content)';

				if (this.fit_container)
					return `minmax(0, ${column.width})`;

                return column.width;
            },
        },
    }
</script>

<style lang="scss" scoped>

	// Block with scrollbar
    .t-wrapper {
		background-color: var(--table-body-background);
    }

	.t-wrapper.fit-container {
		min-width: 0;
	}

	// Horizontal-scroll mode = single scroll box on BOTH axes.
	// `overflow-x: auto` (from the .overflow-x-auto class) forces `overflow-y` to
	// `auto` too (CSS spec): the wrapper becomes the scroll container for X and Y.
	// That is intentional here — it makes the wrapper the nearest scrollable
	// ancestor for the sticky header (`.header-cell { position: sticky; top: 0 }`),
	// so the header sticks to the wrapper's top while the body scrolls inside it.
	// We must therefore keep the bounded height from the base classes
	// (`h-full min-h-0 grow`) — do NOT collapse it to content height, or there is
	// no vertical overflow for the header to stick against.
	.t-wrapper.x-scroll {
		min-width: 0;          // shrink to the flex parent so the grid can overflow on X
	}

	// Vertical-only scroll box. The grid inside must keep its full content height
	// (flex `min-height: auto` guarantees it) so the sticky header has the whole
	// list to travel along; the bounded height comes from the base classes.
	.t-wrapper.y-scroll {
		min-width: 0;
	}

    .t-block {
		background-color: var(--table-body-background);

		grid-auto-rows: min-content;
  		align-content: start;
    }

    .rounded-table {
        border-radius: var(--table-border-radius);
    }

    // ::v-deep(.grid > *) {
    //     position: relative;
    // }

    // ::v-deep(.grid > *:not(:last-child)::after) {
    //     content: '';
    //     position: absolute;
    //     left: 0;
    //     right: 0;
    //     bottom: 0;
    //     height: 1px;
    //     background: green;
    // }

    // .table > *:not(:last-child)::after {
    //     content: '';
    //     position: absolute;
    //     bottom: 0;
    //     left: 0;
    //     right: 0;
    //     height: 1px;
    //     background: red; /* Цвет как у border-gray-300 */
    // }
    // ::v-deep(.table > div:not(:nth-last-child(-n + 3))) {
    //     position: relative!important;
    // }

    ::v-deep(.table .row-cell::after) {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: var(--table-border-color); /* Цвет как у border-gray-300 */
    }
</style>

<!-- <div class="t-block-wrapper v-flex min-h-0 h-full" >
		
		<div class="t-header">
			<slot name="t-header" />
		</div>

		<div class="t-body hide-scrollbar scrollbar-thin !relative"
			:class="{
					'scrolled-wrapper' : scrolled,
					'rounded-table' : rounded
				}">

			<div class="table grid gap-y-[1px] h-full" 
				:class="{ 
						table_classes, 
						'rounded-table' : rounded 
					}" 
				:style="columnsWidthes"
				>

				<slot name="t-body" />
			</div>

		</div>

		<div class="t-footer"></div>

    </div> -->

	<!-- <div class="t-block-wrapper h-full hide-scrollbar scrollbar-thin !relative grid gap-y-[1px]" 
        :class="{
				'scrolled-wrapper' : scrolled,
				'rounded-table' : rounded
			}">
        
		<div class="t-header">
			<slot name="t-header" />
		</div>

        <div class="table h-full" 
            :class="{ 
					table_classes, 
					'rounded-table' : rounded 
				}" 
            :style="columnsWidthes"
            >

            <slot name="t-body" />

        </div>        
    </div> -->

	
    <!-- <div 
        class="t-block-wrapper h-full hide-scrollbar scrollbar-thin !relative" 
        :class="{
				'scrolled-wrapper' : scrolled,
				'rounded-table' : rounded
			}">
        
        <div class="table grid gap-y-[1px] h-full" 
            :class="{ 
					table_classes, 
					'rounded-table' : rounded 
				}" 
            :style="columnsWidthes"
            >

            <slot />

        </div>        
    </div> -->
