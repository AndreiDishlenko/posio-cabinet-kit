<template>
	<!-- Mobile: swipe-to-confirm -->
	<div v-if="isMobile" class="swipe-btn-wrap w-full select-none" :class="{ 'is-swiped': swiped }" @touchstart.passive="onTouchStart" @touchmove.passive="onTouchMove" @touchend.passive="onTouchEnd">
		<div class="swipe-btn-track relative flex items-center h-14 rounded-[--radius-pill] overflow-hidden p-1" ref="track">
			<div class="swipe-btn-handle flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center relative z-10 touch-pan-x" :style="{ transform: `translateX(${dragX}px)` }" ref="handle">
				<slot name="handle-icon">
					<Icon icon="ic:round-chevron-right" width="22" />
				</slot>
			</div>
			<span class="swipe-btn-label absolute inset-0 flex items-center justify-center pointer-events-none" :style="{ opacity: labelOpacity }">
				{{ label }}
			</span>
		</div>
	</div>

	<!-- Desktop: plain primary button -->
	<button v-else type="button" class="swipe-btn-desktop w-full h-14 rounded-[--radius-pill] border-none cursor-pointer flex items-center relative p-1 disabled:opacity-45 disabled:cursor-default" :disabled="disabled" @click="$emit('confirm')">
		<span class="swipe-btn-desktop-handle flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center">
			<slot name="handle-icon">
				<Icon icon="ic:round-chevron-right" width="22" />
			</slot>
		</span>
		<span class="swipe-btn-desktop-label flex-1 text-center text-[13px] font-bold uppercase pr-[52px]">{{ label }}</span>
	</button>
</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'SwipeButton',

		components: { Icon },

		emits: ['confirm'],

		props: {
			label: {
				type: String,
				default: 'СВАЙП ДЛЯ ОПЛАТИ',
			},
			disabled: {
				type: Boolean,
				default: false,
			},
			// threshold: fraction of track width that triggers confirm
			threshold: {
				type: Number,
				default: 0.72,
			},
		},

		data() {
			return {
				isMobile: false,
				swiped: false,
				dragX: 0,
				startX: 0,
				trackWidth: 0,
				handleWidth: 0,
				dragging: false,
			};
		},

		computed: {
			maxDrag() {
				return Math.max(0, this.trackWidth - this.handleWidth - 4);
			},
			labelOpacity() {
				if (!this.trackWidth) return 1;
				const progress = this.dragX / this.maxDrag;
				return Math.max(0, 1 - progress * 2);
			},
		},

		mounted() {
			this.checkMobile();
			window.addEventListener('resize', this.checkMobile);
		},

		beforeUnmount() {
			window.removeEventListener('resize', this.checkMobile);
		},

		methods: {
			checkMobile() {
				this.isMobile = window.matchMedia('(pointer: coarse)').matches || window.innerWidth < 768;
			},

			onTouchStart(e) {
				if (this.disabled || this.swiped) return;
				const track = this.$refs.track;
				const handle = this.$refs.handle;
				if (!track || !handle) return;
				this.trackWidth = track.offsetWidth;
				this.handleWidth = handle.offsetWidth;
				this.startX = e.touches[0].clientX;
				this.dragging = true;
			},

			onTouchMove(e) {
				if (!this.dragging) return;
				const dx = e.touches[0].clientX - this.startX;
				this.dragX = Math.max(0, Math.min(dx, this.maxDrag));
			},

			onTouchEnd() {
				if (!this.dragging) return;
				this.dragging = false;
				const progress = this.dragX / this.maxDrag;
				if (progress >= this.threshold) {
					this.dragX = this.maxDrag;
					this.swiped = true;
					this.$emit('confirm');
					setTimeout(() => {
						this.swiped = false;
						this.dragX = 0;
					}, 800);
				} else {
					this.dragX = 0;
				}
			},
		},
	};
</script>

<style lang="scss" scoped>
	.swipe-btn-track {
		background: var(--swipe-button-track-bg);
	}

	.swipe-btn-handle {
		background: var(--swipe-button-handle-bg);
		box-shadow: var(--swipe-button-handle-shadow);
		color: var(--swipe-button-arrow-fg);
		will-change: transform;
		transition:
			transform var(--dur-fast) var(--ease-out-soft),
			background var(--dur-fast) var(--ease-standard);
	}

	.swipe-btn-label {
		color: var(--swipe-button-fg);
		transition: opacity var(--dur-fast);
	}

	.is-swiped .swipe-btn-handle {
		background: var(--success);
		box-shadow: 0 0 12px rgba(86, 240, 0, .4);
	}

	.swipe-btn-desktop {
		background: var(--swipe-button-track-bg);
		transition: background var(--dur-fast) var(--ease-standard), opacity var(--dur-fast);

		&:hover:not(:disabled) {
			background: var(--swipe-button-track-bg-hover);

			.swipe-btn-desktop-handle {
				background: var(--swipe-button-handle-bg-hover);
			}
		}

		&:active:not(:disabled) {
			background: var(--swipe-button-track-bg-press);
		}
	}

	.swipe-btn-desktop-handle {
		background: var(--swipe-button-handle-bg);
		box-shadow: var(--swipe-button-handle-shadow);
		color: var(--swipe-button-arrow-fg);
		transition: background var(--dur-fast) var(--ease-standard);
	}

	.swipe-btn-desktop-label {
		color: var(--swipe-button-fg);
		letter-spacing: var(--swipe-button-label-spacing);
	}
</style>
