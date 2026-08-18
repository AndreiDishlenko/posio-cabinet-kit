<template lang="">

	<div :class="accordion_class" class="accordion-wrapper border-test">

		{{state}}

		<div @click="toggleItem()" :class="button_class" :style="button_styles" class="acc-header cursor-pointer flex items-center pe-2" >		
			<div class="grow">
				<slot name="acc_button" />
			</div>
			
			<Icon v-if="button_arrow" icon="ep:arrow-down-bold" height="12px"
				class="acc-arrow input-icon inline-block transition-transform duration-200 ease-in-out"
				:class="{'rotate-90': !state}"/>
		</div>		

		<!-- <Transition
			enter-active-class="transition duration-1500 ease-out"
			enter-from-class="-translate-y-10 opacity-0"
			enter-to-class="translate-y-0 opacity-100"
			leave-active-class="transition duration-0 ease-in"
			leave-from-class="translate-y-0 opacity-100"
			leave-to-class="-translate-y-10 opacity-0"
			> -->

			<div v-if="state" :class="body_class" class="acc_block contents">
				<slot name="acc_block" />
			</div>
	
		<!-- </Transition> -->
			
	</div>

</template>

<script>
	import { Icon } from "@iconify/vue";

	export default {
		components: {Icon},
		props: {
			is_opened: {
                type: Boolean,
                default: true
            },
			button_arrow: {
				type: Boolean,
				default: true
			},
			accordion_class: {
				type: String,
				default: ''
			},
			button_class: {
				type: String,
				default: ''
			},
			button_styles: {
				type: Object,
				default: {}
			},
			body_class: {
				type: String,
				default: ''
			},
			// can_opens: {
			// 	type: Boolean,
			// 	default: true
			// }
		},
		data() {
            return {
                state:  false,
            }
        },
		methods: {
			toggleItem() {
				// console.log('toggleGroup', index);

				if (!this.state)
					this.$emit('before_open')
				else
					this.$emit('before_close')


				// this.$nextTick(() => {
					this.state = this.can_opens && !this.state ? true : false
				// })
				

				this.$nextTick(() => {
					if (this.state)
						this.$emit('opened')
					else
						this.$emit('closed')
				})

				// Для надёжности в редких случаях можно принудительно обновить
				// this.$forceUpdate()
			},
			open() {
				this.state = true
			},
			close() {
				this.state = false
			},
		}
	}
</script>

<style lang="scss">
	// .acc_block {
	// 	border: 1px solid red!important;
	// 	background-color: red!important;
	// }
</style>