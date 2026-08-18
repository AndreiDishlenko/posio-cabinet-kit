<template lang="">

	<!-- Accordion wrapper -->
    <div class="accordion-item rounded" :class="{
			'space-y-2': state,
			'collapsed': !state,
			'fullwidth': fullwidth
		}">

		<!-- Accordion header -->
        <div class="accordion-item-header" 
			role="button" 
			tabindex="0"
			@click="toggleAccordion()" 
			@keydown.enter="toggleAccordion()" 
			@keydown.space.prevent="toggleAccordion()"
            >
			<div class="v-flex">

				<!-- Header -->
				<slot name="header">
					<span :class="header_classes">{{ $t(header) }}</span>
				</slot>

				<!-- Sub header -->
				<div v-if="subheader" class="!mt-0 text-xs text-secondary">
					{{ subheader }}
				</div>

			</div>
            <span class="accordion-item-icon">
                <Icon icon="ep:arrow-left-bold" class=""/>
            </span>
        </div>

		<!-- Accordion body -->
        <div ref="container" class="accordion-item-container mx-0 mb-4">
            <div class="accordion-item-content-wrapper">
                <slot></slot>
            </div>
        </div>
    </div>

</template>

<script>
    import { Icon } from "@iconify/vue";

    export default {
        components: {Icon},
        props: {
            header: {
                type: String,
                default: ''
            },
            header_classes: {
                type: String,
                default: ''
            },

            isOpened: {
                type: Boolean,
                default: false
            },
            fullwidth: {
                type: Boolean,
                default: false
            },
			subheader: {
				type: String,
				default: ''
			}
        },
        data() {
            return {
                state: this.isOpened,
            }
        },
        mounted() {
            this.toggleAccordion(this.state)           
        },
        methods: {
            toggleAccordion(manualState=null) {
                if (manualState!=null)
                    this.state = !manualState

                if (this.state) {                    
                    this.$refs.container.style.height = '0px';
                } else {
                    let height = this.$refs.container.scrollHeight+1;
                    this.$refs.container.style.height = height+'px';
                    // this.$refs.container.style.removeProperty('max-height');// = this.$refs.container.scrollHeight+'px';
                }
                
                this.state = !this.state;
            }
        },
    }
</script>

<style lang="scss" scoped>
    // Обычный поток вместо flex: класс-карточка снаружи может задавать промежуток между
    // детьми, и в свёрнутом состоянии он остаётся зазором под шапкой — свёрнутое
    // содержимое не исчезает, у него лишь нулевая высота.
    .accordion-item {
        display: block;
    }

    .accordion-item:not(.fullwidth) {
        // background-color: var(--form-control-background);
        // border-radius: var(--border-radius);
    }

    .accordion-item-header {
        @apply
            w-full 
            flex 
            justify-between 
            items-center;
        background-color: var(--form-control-background);
        border-radius: var(--border-radius);
    }

    .accordion-item-icon {
        transform: rotate(-90deg) translateY(-0%);
        @apply
            relative 
            transition-transform 
            duration-300;
    }

    .accordion-item-container {
        @apply
            overflow-hidden 
            transition-all 
            duration-300 
            ease-in-out;
    }

    .fullwidth .accordion-item-container {
        @apply
            mt-4;
    }

    .accordion-item:not(.fullwidth) .accordion-item-content-wrapper {
        // @apply
        //     px-5
        //     pb-5
    }

    .collapsed {
        .accordion-item-container {
            @apply 
                m-0
                transition-all 
                duration-300 
                ease-in-out;
        }

        .accordion-item-icon {
            transform: rotate(0deg) translateY(-0%);
        }
    }

</style>