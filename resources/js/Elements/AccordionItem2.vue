<template lang="">

	<div :class="accordion_class" class="accordion-wrapper">

		<div v-bind="button_attrs" @click="toggleItem()" :class="[button_class, cells_mode ? 'acc-header-cells' : 'flex items-center pe-2']" :style="button_styles" class="acc-header cursor-pointer" >
			<template v-if="cells_mode">
				<slot name="acc_button" />

				<span v-if="button_arrow" class="acc-arrow-pos">
					<span class="acc-arrow inline-flex items-center justify-center w-3 h-3 shrink-0 origin-center transition-transform duration-200 ease-in-out"
						:class="state ? 'rotate-0' : 'rotate-180'">
						<Icon icon="ep:arrow-down-bold" width="12px" height="12px"/>
					</span>
				</span>
			</template>
			<template v-else>
				<span v-if="button_arrow && arrow_position == 'start'" class="acc-arrow me-2 inline-flex items-center justify-center w-3 h-3 shrink-0 origin-center transition-transform duration-200 ease-in-out"
					:class="state ? 'rotate-0' : 'rotate-180'">
					<Icon icon="ep:arrow-down-bold" width="12px" height="12px"/>
				</span>

				<div class="grow min-w-0">
					<slot name="acc_button" />
				</div>

				<span v-if="button_arrow && arrow_position != 'start'" class="acc-arrow inline-flex items-center justify-center w-3 h-3 shrink-0 origin-center transition-transform duration-200 ease-in-out"
					:class="state ? 'rotate-0' : 'rotate-180'">
					<Icon icon="ep:arrow-down-bold" width="12px" height="12px"/>
				</span>
			</template>
		</div>

		<!-- <Transition
			enter-active-class="transition duration-500 ease-out"
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
			// Атрибути й слухачі, які вішаються прямо на шапку (напр. ключ і захоплення
			// натискання для перетягування) — потрібні саме на ній, бо клік по шапці
			// вже зайнятий розкриттям.
			button_attrs: {
				type: Object,
				default: () => ({})
			},
			// Бік, з якого стоїть стрілка розкриття: 'end' (типово) або 'start'.
			arrow_position: {
				type: String,
				default: 'end'
			},
			body_class: {
				type: String,
				default: ''
			},
			can_opens: {
				type: Boolean,
				default: true
			},
			cells_mode: {
				type: Boolean,
				default: false
			}
		},
		data() {
            return {
                state: false,
            }
        },
		mounted() {
			this.state = this.can_opens && this.is_opened ? true : false
		},
		methods: {
			toggleItem() {
				// console.log('toggleGroup', index);

				if (!this.state)
					this.$emit('before_open')
				else
					this.$emit('before_close')

				this.state = this.can_opens && !this.state ? true : false

				this.$nextTick(() => {
					if (this.state)
						this.$emit('opened')
					else
						this.$emit('closed')
				})
				// Для надёжности в редких случаях можно принудительно обновить
				// this.$forceUpdate()
			},
			close() {
				if (!this.state) return
				this.state = false
				this.$nextTick(() => this.$emit('closed'))
			},
			open() {
				if (this.state || !this.can_opens) return
				this.$emit('before_open')
				this.state = true
				this.$nextTick(() => this.$emit('opened'))
			},
		}
	}
</script>

<style lang="scss">
	// .acc_block {
	// 	border: 1px solid red!important;
	// 	background-color: red!important;
	// }

	.acc-header-cells {
		display: grid;
		grid-template-columns: subgrid;
		align-items: center;
		position: relative;
	}

	.acc-arrow-pos {
		position: absolute;
		left: 0.75rem;
		top: 50%;
		transform: translateY(-50%);
		z-index: 1;
		display: inline-flex;
	}
</style>