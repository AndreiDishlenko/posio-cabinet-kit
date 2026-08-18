<template>

	<div class="carousel relative">

		<div v-if="hasHeader" class="carousel-header flex items-center justify-between gap-4 mb-6">
			<h2 v-if="hasTitle" class="carousel-title text-left m-0">
				<slot name="title">{{ title }}</slot>
			</h2>
			<div v-if="arrows" class="carousel-header-arrows flex gap-2 ml-auto">
				<button
					type="button"
					class="carousel-arrow carousel-arrow--inline"
					:disabled="!canPrev"
					aria-label="Previous"
					@click="prev"
				>
					<Icon icon="tabler:chevron-left" />
				</button>
				<button
					type="button"
					class="carousel-arrow carousel-arrow--inline"
					:disabled="!canNext"
					aria-label="Next"
					@click="next"
				>
					<Icon icon="tabler:chevron-right" />
				</button>
			</div>
		</div>

		<button
			v-if="arrows && !hasHeader"
			type="button"
			class="carousel-arrow carousel-arrow--prev"
			:disabled="!canPrev"
			aria-label="Previous"
			@click="prev"
		>
			<Icon icon="tabler:chevron-left" />
		</button>

		<div ref="viewport" class="carousel-viewport overflow-hidden pt-1 pb-4 -mt-1 -mb-4">
			<div
				ref="track"
				class="carousel-track flex"
				:style="trackStyle"
				@transitionend.self="onTransitionEnd"
			>
				<div
					v-for="slide in displayItems"
					:key="slide.key"
					class="carousel-slide flex-shrink-0"
					:style="slideStyle"
				>
					<slot :item="slide.item" :index="slide.realIndex" />
				</div>
			</div>
		</div>

		<button
			v-if="arrows && !hasHeader"
			type="button"
			class="carousel-arrow carousel-arrow--next"
			:disabled="!canNext"
			aria-label="Next"
			@click="next"
		>
			<Icon icon="tabler:chevron-right" />
		</button>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	const BP = { sm: 640, md: 768, lg: 1024, xl: 1280, '2xl': 1536 };
	const BP_ORDER = ['2xl', 'xl', 'lg', 'md', 'sm'];

	export default {
		components: { Icon },

		props: {
			items: {
				type:     Array,
				required: true,
			},
			title: {
				type:    String,
				default: '',
			},
			itemsPerView: {
				// Number — фиксированное число карточек.
				// Object — респонсив: { default, sm, md, lg, xl, '2xl' }.
				type:    [Number, Object],
				default: 4,
			},
			gap: {
				type:    Number,
				default: 16,
			},
			arrows: {
				type:    Boolean,
				default: true,
			},
			infinite: {
				type:    Boolean,
				default: false,
			},
			transitionDuration: {
				type:    Number,
				default: 400,
			},
		},

		data() {
			return {
				viewportWidth:     0,
				windowWidth:       typeof window !== 'undefined' ? window.innerWidth : 1280,
				cursor:            0,
				suspendTransition: false,
				_resizeObserver:   null,
				_windowResize:     null,
			};
		},

		computed: {
			hasTitle() {
				return !!this.title || !!this.$slots.title;
			},

			hasHeader() {
				return this.hasTitle;
			},

			perView() {
				const raw = this.itemsPerView;
				if (typeof raw === 'number') return Math.max(1, raw);
				const w = this.windowWidth;
				for (const key of BP_ORDER) {
					if (raw[key] != null && w >= BP[key]) return Math.max(1, raw[key]);
				}
				return Math.max(1, raw.default ?? 1);
			},

			n() {
				return this.items.length;
			},

			effectiveInfinite() {
				return this.infinite && this.n > this.perView;
			},

			cloneCount() {
				return this.effectiveInfinite ? this.perView : 0;
			},

			displayItems() {
				const out = [];
				if (!this.n) return out;
				if (this.effectiveInfinite) {
					const c = this.cloneCount;
					for (let i = this.n - c; i < this.n; i++) {
						out.push({ key: `pre-${i}`, item: this.items[i], realIndex: i });
					}
					this.items.forEach((item, i) => out.push({ key: `m-${i}`, item, realIndex: i }));
					for (let i = 0; i < c; i++) {
						out.push({ key: `post-${i}`, item: this.items[i], realIndex: i });
					}
				} else {
					this.items.forEach((item, i) => out.push({ key: `m-${i}`, item, realIndex: i }));
				}
				return out;
			},

			slideWidth() {
				const v = this.viewportWidth;
				if (!v) return 0;
				return (v - (this.perView - 1) * this.gap) / this.perView;
			},

			step() {
				return this.slideWidth + this.gap;
			},

			translateX() {
				return -(this.cursor * this.step);
			},

			trackStyle() {
				const dur = this.suspendTransition ? '0ms' : `${this.transitionDuration}ms`;
				return {
					gap:        `${this.gap}px`,
					transform:  `translate3d(${this.translateX}px, 0, 0)`,
					transition: `transform ${dur} ease`,
				};
			},

			slideStyle() {
				if (!this.slideWidth) return { width: `${100 / this.perView}%` };
				return { width: `${this.slideWidth}px` };
			},

			maxCursorBounded() {
				return Math.max(0, this.n - this.perView);
			},

			canPrev() {
				if (this.effectiveInfinite) return true;
				return this.cursor > 0;
			},

			canNext() {
				if (this.effectiveInfinite) return true;
				return this.cursor < this.maxCursorBounded;
			},
		},

		watch: {
			perView() {
				this.resetCursor();
			},
			'items.length'() {
				this.resetCursor();
			},
		},

		mounted() {
			this.measure();
			this.resetCursor();

			if (typeof ResizeObserver !== 'undefined' && this.$refs.viewport) {
				this._resizeObserver = new ResizeObserver(() => this.measure());
				this._resizeObserver.observe(this.$refs.viewport);
			}

			this._windowResize = () => { this.windowWidth = window.innerWidth; };
			window.addEventListener('resize', this._windowResize);
		},

		beforeUnmount() {
			if (this._resizeObserver) this._resizeObserver.disconnect();
			if (this._windowResize) window.removeEventListener('resize', this._windowResize);
		},

		methods: {
			measure() {
				if (this.$refs.viewport) this.viewportWidth = this.$refs.viewport.clientWidth;
			},

			resetCursor() {
				this.cursor = this.effectiveInfinite ? this.cloneCount : 0;
				this.suspendTransition = true;
				requestAnimationFrame(() => {
					requestAnimationFrame(() => { this.suspendTransition = false; });
				});
			},

			next() {
				if (!this.canNext) return;
				this.cursor += 1;
			},

			prev() {
				if (!this.canPrev) return;
				this.cursor -= 1;
			},

			onTransitionEnd() {
				if (!this.effectiveInfinite) return;
				const c = this.cloneCount;
				if (this.cursor >= c + this.n) {
					this.silentJump(this.cursor - this.n);
				} else if (this.cursor < c) {
					this.silentJump(this.cursor + this.n);
				}
			},

			silentJump(toCursor) {
				this.suspendTransition = true;
				this.cursor = toCursor;
				requestAnimationFrame(() => {
					requestAnimationFrame(() => { this.suspendTransition = false; });
				});
			},
		},
	};
</script>

<style lang="scss" scoped>
	.carousel {
		width: 100%;
	}

	.carousel-track {
		will-change: transform;
	}

	.carousel-title {
		font-size: clamp(1.5rem, 2.5vw, 2rem);
		font-weight: 700;
		letter-spacing: -0.01em;
	}

	// Base button styling shared by side-overlay and inline-header arrows.
	.carousel-arrow {
		width: 2.75rem;
		height: 2.75rem;
		border-radius: 9999px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		background: rgba(255, 255, 255, 0.12);
		color: #f1f5f9;
		border: 1px solid rgba(255, 255, 255, 0.25);
		cursor: pointer;
		backdrop-filter: blur(10px);
		font-size: 1.25rem;
		transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;

		&:hover:not(:disabled) {
			background: rgba(255, 255, 255, 0.22);
			border-color: rgba(255, 255, 255, 0.45);
		}

		&:disabled {
			opacity: 0.25;
			cursor: not-allowed;
		}
	}

	// Side-overlay mode: vertically centered, overlapping viewport edges.
	.carousel-arrow--prev,
	.carousel-arrow--next {
		position: absolute;
		top: 50%;
		transform: translateY(-50%);
		z-index: 5;
	}

	.carousel-arrow--prev { left: -0.5rem; }
	.carousel-arrow--next { right: -0.5rem; }

	@media (min-width: 640px) {
		.carousel-arrow--prev { left: -1.25rem; }
		.carousel-arrow--next { right: -1.25rem; }
	}

	// Header mode: inline buttons next to title, slightly smaller.
	.carousel-arrow--inline {
		width: 2.5rem;
		height: 2.5rem;
		font-size: 1.125rem;
	}
</style>
