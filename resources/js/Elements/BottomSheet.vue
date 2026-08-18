<template>

	<!-- Desktop: обычный контейнер -->
	<div v-if="!isMobile" :class="$attrs.class">
		<slot />
	</div>

	<!-- Mobile: модальное окно с оверлеем, выезжающее снизу -->
	<teleport v-else to="body">

		<transition name="bs-fade">
			<div
				v-if="isVisible"
				class="fixed inset-0 bg-black/60 z-[1000]"
				@click="onOverlayClick"
				/>
		</transition>

		<transition name="bs-slide-up">
			<div
				v-if="isVisible"
				class="bs-sheet fixed left-0 right-0 bottom-0 z-[1001] flex flex-col main-background rounded-t-2xl shadow-2xl"
				:class="{ 'is-dragging': dragging }"
				:style="sheetStyle"
				role="dialog"
				aria-modal="true"
				@click.stop
				>

				<!-- Drag-handle -->
				<div class="bs-handle-zone flex justify-center pt-2 pb-1 shrink-0" @click="onHandleClick" @pointerdown="onDragStart">
					<div class="w-10 h-1.5 rounded-full bg-gray-400/60"></div>
				</div>

				<!-- Header -->
				<div
					v-if="header || $slots.header"
					class="bs-header flex items-center justify-between px-4 py-2 border-b header-background shrink-0"
					>
					<slot name="header">
						<h2 class="truncate">{{ $t(header) }}</h2>
					</slot>
					<button
						class="p-2 -mr-2 rounded-lg hover:bg-gray-700/40 active:bg-gray-600/40 transition-colors"
						@click="close"
						>
						<Icon icon="material-symbols:close" class="icon" />
					</button>
				</div>

				<!-- Content -->
				<div class="grow overflow-y-auto p-3 scrollbar-thin">
					<slot />
				</div>

			</div>
		</transition>

	</teleport>

</template>

<script>
	import { Icon } from '@iconify/vue';

	import { lockPageScroll, unlockPageScroll } from '@/js/pageScrollLock';
	import { pushOverlay, popOverlay, popOverlaySilent } from '@/js/overlayHistory';

	export default {
		name: 'BottomSheet',
		inheritAttrs: false,
		components: { Icon },
		props: {
			header: {
				type: String,
				default: '',
			},
			breakpoint: {
				type: Number,
				default: 768,
			},
			minHeight: {
				type: String,
				default: '',
			},
			height: {
				type: String,
				default: '',
			},
			closeOnOverlay: {
				type: Boolean,
				default: true,
			},
			// Листы, где содержимое открывает карточку поверх, закрывают только
			// крестиком: случайный промах по полоске не должен снимать список.
			closeOnHandle: {
				type: Boolean,
				default: true,
			},
			closeOnSwipe: {
				type: Boolean,
				default: true,
			},
			// Насколько далеко нужно протянуть вниз, чтобы жест засчитался
			// закрытием, а не случайным касанием полоски.
			swipeThreshold: {
				type: Number,
				default: 120,
			},
		},
		emits: ['open', 'close'],
		data() {
			return {
				isVisible: false,
				isMobile: false,
				mediaQuery: null,
				dragging: false,
				dragStartY: 0,
				dragOffset: 0,
				dragMoved: false,
			}
		},
		computed: {
			sheetStyle() {
				// Fixed height keeps the sheet stable while its content changes;
				// otherwise the sheet grows with content between minHeight and 90dvh.
				const max_height = this.withViewportFallback('90dvh');

				const style = this.height
					? { height: this.withViewportFallback(this.height), maxHeight: max_height }
					: { minHeight: this.withViewportFallback(this.minHeight), maxHeight: max_height };

				// Пока палец ведёт лист, смещение задаётся вручную и сглаживание
				// отключается — иначе лист тянется за пальцем с задержкой.
				if (this.dragging) {
					style.transform = `translateY(${this.dragOffset}px)`;
					style.transition = 'none';
				}

				return style;
			},
		},
		watch: {
			// Ограничение цепочки прокрутки Apple понимает только с 16: доскроллив
			// содержимое sheet до края, палец начинает тянуть подложку, и sheet
			// визуально уезжает. Пока он открыт, страница под ним фиксируется.
			isVisible(is_visible) {
				if (is_visible)
					lockPageScroll(this);
				else
					unlockPageScroll(this);
			},
		},
		mounted() {
			this.mediaQuery = window.matchMedia(`(max-width: ${this.breakpoint - 1}px)`);
			this.isMobile = this.mediaQuery.matches;
			this.mediaQuery.addEventListener('change', this.onMediaChange);
		},
		beforeUnmount() {
			unlockPageScroll(this);
			this.detachDragListeners();
			if (this.mediaQuery)
				this.mediaQuery.removeEventListener('change', this.onMediaChange);
			popOverlay(this);
		},
		methods: {
			// Динамическую высоту вьюпорта Apple понимает только с 15.4; ниже значение
			// отбрасывается и sheet остаётся без ограничения. Массив значений Vue
			// раскрывает в парные объявления — статическое первым.
			withViewportFallback(value) {
				if (!value || !value.includes('dvh'))
					return value;

				return [value.replace(/dvh/g, 'vh'), value];
			},
			open() {
				// BottomSheet.open
				if (!this.isMobile) {
					this.$emit('open');
					return;
				}
				if (this.isVisible) return;
				this.isVisible = true;
				pushOverlay(this, this.dismiss);
				this.$emit('open');
			},
			close() {
				// BottomSheet.close
				if (!this.isVisible) return;
				popOverlay(this);
				this.dismiss();
			},
			// Закрытие по клику ссылки внутри листа — не трогаем историю (см. overlayHistory.js).
			closeSilently() {
				if (!this.isVisible) return;
				popOverlaySilent(this);
				this.dismiss();
			},
			// Собственно скрытие. Вызывается и при закрытии изнутри, и когда лист
			// снимает возврат назад — историю в этом случае трогать уже нельзя.
			dismiss() {
				if (!this.isVisible) return;
				this.isVisible = false;
				this.$emit('close');
			},
			toggle() {
				if (this.isVisible) this.close();
				else this.open();
			},
			onOverlayClick() {
				if (this.closeOnOverlay) this.close();
			},
			onHandleClick() {
				// Завершение жеста тоже приходит кликом — по нему закрывать не нужно,
				// решение уже принято по пройденному расстоянию.
				if (this.dragMoved) {
					this.dragMoved = false;
					return;
				}
				if (this.closeOnHandle) this.close();
			},
			onDragStart(e) {
				if (!this.closeOnSwipe) return;
				this.dragging = true;
				this.dragMoved = false;
				this.dragStartY = e.clientY;
				this.dragOffset = 0;
				window.addEventListener('pointermove', this.onDragMove, { passive: true });
				window.addEventListener('pointerup', this.onDragEnd);
				window.addEventListener('pointercancel', this.onDragEnd);
			},
			onDragMove(e) {
				// Тянуть можно только вниз: вверх лист не растягивается.
				this.dragOffset = Math.max(0, e.clientY - this.dragStartY);
				if (this.dragOffset > 4) this.dragMoved = true;
			},
			onDragEnd() {
				const should_close = this.dragOffset > this.swipeThreshold;
				this.dragging = false;
				this.dragOffset = 0;
				this.detachDragListeners();
				// Ручное смещение снимается отдельным кадром: пока оно висит инлайном
				// вместе с отключённым сглаживанием, выезд вниз не проигрывается и лист
				// просто исчезает.
				if (should_close)
					this.$nextTick(() => this.close());
			},
			detachDragListeners() {
				window.removeEventListener('pointermove', this.onDragMove);
				window.removeEventListener('pointerup', this.onDragEnd);
				window.removeEventListener('pointercancel', this.onDragEnd);
			},
			onMediaChange(e) {
				// BottomSheet.onMediaChange
				this.isMobile = e.matches;
				if (!this.isMobile && this.isVisible)
					this.close();
			},
		},
	}
</script>

<style lang="scss" scoped>
	.bs-fade-enter-active,
	.bs-fade-leave-active {
		transition: opacity 0.25s ease;
	}
	.bs-fade-enter-from,
	.bs-fade-leave-to {
		opacity: 0;
	}

	// Возврат листа на место после недотянутого жеста: инлайновое смещение
	// снимается, и лист доезжает обратно этим переходом.
	.bs-sheet {
		transition: transform 0.3s ease;
		background-color: var(--bottom-sheet-bg);
		// Лист лежит поверх страницы и светлее её, поэтому разделители внутри
		// отсчитываются от его собственного фона, а не от общего фона темы.
		--card-divider: var(--bottom-sheet-divider);
		--divider-color: var(--bottom-sheet-divider);

		&.is-dragging {
			transition: none;
		}
	}

	.bs-header {
		border-bottom-color: var(--bottom-sheet-divider);
	}

	.bs-handle-zone {
		cursor: grab;
		user-select: none;
		// Вертикальный жест отдаётся обработчику, а не нативной прокрутке.
		touch-action: none;

		&:active {
			cursor: grabbing;
		}
	}

	.bs-slide-up-enter-active,
	.bs-slide-up-leave-active {
		transition: transform 0.3s ease;
	}
	.bs-slide-up-enter-from,
	.bs-slide-up-leave-to {
		transform: translateY(100%);
	}
</style>
