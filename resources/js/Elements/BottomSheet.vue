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
				ref="sheet"
				class="bs-sheet fixed left-0 right-0 bottom-0 z-[1001] flex flex-col overflow-hidden main-background rounded-t-2xl shadow-2xl"
				:class="{ 'is-dragging': dragging }"
				:style="sheetStyle"
				role="dialog"
				aria-modal="true"
				@click.stop
				>

				<!-- Drag-handle -->
				<div class="bs-handle-zone flex justify-center pt-2 pb-1 shrink-0" @click="onHandleClick" v-on="dragListeners">
					<div class="w-10 h-1.5 rounded-full bg-gray-400/60"></div>
				</div>

				<!-- Header -->
				<div
					v-if="header || $slots.header"
					class="bs-header relative flex items-center justify-center px-12 py-2 border-b shrink-0"
					>
					<slot name="header">
						<!-- Своя типографика обязательна: базовая высота строки заголовка
							 задана абсолютным значением меньше кегля, и обрезка длинного
							 текста по ширине срезала бы заодно верх и хвосты букв -->
						<h2 class="truncate text-xl font-semibold leading-tight">{{ $t(header) }}</h2>
					</slot>
					<!-- Крестик выведен из потока, иначе он смещал бы заголовок влево от центра -->
					<button
						class="absolute right-2 top-1/2 -translate-y-1/2 p-2 rounded-lg hover:bg-gray-700/40 active:bg-gray-600/40 transition-colors"
						@click="close"
						>
						<Icon icon="material-symbols:close" class="icon" />
					</button>
				</div>

				<!-- Content -->
				<!-- Обнулённый минимум обязателен: без него высокое содержимое считает
					 свой размер минимально допустимым, растёт вверх и выдавливает
					 шапку листа за верхнюю кромку экрана -->
				<div class="bs-content grow min-h-0 p-3"
					:class="fill ? 'flex flex-col' : 'overflow-y-auto scrollbar-thin'"
					@touchstart="onContentTouchStart"
					@touchmove="onContentTouchMove"
					@touchend="onContentTouchEnd"
					@touchcancel="onContentTouchEnd"
					>
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

	// Пройденное расстояние, после которого понятно, куда ведёт палец. Пока оно
	// не пройдено, жест остаётся за содержимым.
	const DRAG_ACTIVATION_PX = 8;

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
			// Содержимое само распоряжается высотой листа: лист не прокручивает его
			// целиком, а отдаёт всю высоту под раскладку. Нужно там, где нижний ряд
			// действий должен стоять у края экрана, а прокручивается только часть
			// содержимого.
			fill: {
				type: Boolean,
				default: false,
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
				dragPending: false,
				dragStartX: 0,
				dragStartY: 0,
				dragOffset: 0,
				dragMoved: false,
				dragScroller: null,
				// Имена событий начатого жеста: набор выбирается в момент нажатия и
				// нужен, чтобы снять ровно те слушатели, что были поставлены.
				dragEvents: null,
			}
		},
		computed: {
			// События указателя Apple понимает только с 13-й версии, а ниже остаются
			// касания и мышь. Набор выбирается один раз: если вешать все три сразу,
			// на современных устройствах одно нажатие начинало бы жест трижды.
			dragListeners() {
				if ( typeof window !== 'undefined' && window.PointerEvent )
					return { pointerdown: this.onDragStart };

				return { touchstart: this.onDragStart, mousedown: this.onDragStart };
			},

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
				this.resetDrag();
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
			// Продолжение и завершение жеста приходят тем же набором событий, каким он
			// начат: на устройствах без событий указателя это касания либо мышь.
			dragEventsFor(type) {
				if (type === 'pointerdown') return ['pointermove', 'pointerup', 'pointercancel'];
				if (type === 'touchstart')  return ['touchmove', 'touchend', 'touchcancel'];

				return ['mousemove', 'mouseup', null];
			},
			// Координата нажатия: у касания она лежит в списке точек, у остальных — на
			// самом событии.
			dragPoint(e) {
				return e.touches ? e.touches[0] : e;
			},
			onDragStart(e) {
				if (!this.closeOnSwipe || this.dragging) return;

				const point = this.dragPoint(e);
				if (!point) return;

				this.dragEvents = this.dragEventsFor(e.type);
				this.dragging = true;
				this.dragMoved = false;
				this.dragStartY = point.clientY;
				this.dragOffset = 0;

				window.addEventListener(this.dragEvents[0], this.onDragMove, { passive: true });
				window.addEventListener(this.dragEvents[1], this.onDragEnd);
				if (this.dragEvents[2])
					window.addEventListener(this.dragEvents[2], this.onDragEnd);
			},
			onDragMove(e) {
				const point = this.dragPoint(e);
				if (!point) return;

				// Тянуть можно только вниз: вверх лист не растягивается.
				this.dragOffset = Math.max(0, point.clientY - this.dragStartY);
				if (this.dragOffset > 4) this.dragMoved = true;
			},
			onDragEnd() {
				this.detachDragListeners();
				this.finishDrag();
			},
			// Жест по содержимому перехватывается не сразу: сначала нужно убедиться,
			// что палец ведёт строго вниз, а прокручиваемая область под ним уже в
			// самом верху — иначе это обычная прокрутка, а не закрытие.
			onContentTouchStart(e) {
				if (!this.closeOnSwipe || this.dragging) return;
				if (e.touches.length !== 1) return;

				this.dragScroller = this.findScroller(e.target);
				if (this.dragScroller && this.dragScroller.scrollTop > 0) return;

				const touch = e.touches[0];
				this.dragPending = true;
				this.dragMoved = false;
				this.dragStartX = touch.clientX;
				this.dragStartY = touch.clientY;
				this.dragOffset = 0;
			},
			onContentTouchMove(e) {
				if (!this.dragPending && !this.dragging) return;

				if (e.touches.length !== 1) {
					this.resetDrag();
					return;
				}

				const touch = e.touches[0];
				const dy = touch.clientY - this.dragStartY;
				const dx = Math.abs(touch.clientX - this.dragStartX);

				if (this.dragPending) {
					if (Math.abs(dy) < DRAG_ACTIVATION_PX && dx < DRAG_ACTIVATION_PX)
						return;

					// Вверх, вбок или из уже прокрученного содержимого лист не тянут.
					if (dy <= 0 || dx > dy || (this.dragScroller && this.dragScroller.scrollTop > 0)) {
						this.resetDrag();
						return;
					}

					this.dragPending = false;
					this.dragging = true;
				}

				this.dragOffset = Math.max(0, dy);
				this.dragMoved = this.dragOffset > 4;
				// Лист уже идёт за пальцем — параллельная прокрутка содержимого его дёргает.
				if (e.cancelable) e.preventDefault();
			},
			onContentTouchEnd() {
				if (!this.dragging) {
					this.resetDrag();
					return;
				}

				this.finishDrag();
			},
			finishDrag() {
				const should_close = this.dragOffset > this.swipeThreshold;
				this.resetDrag();
				// Ручное смещение снимается отдельным кадром: пока оно висит инлайном
				// вместе с отключённым сглаживанием, выезд вниз не проигрывается и лист
				// просто исчезает.
				if (should_close)
					this.$nextTick(() => this.close());
			},
			// Признак завершённого жеста здесь не сбрасывается: по нему клик после
			// перетаскивания отличается от намеренного нажатия.
			resetDrag() {
				this.dragging = false;
				this.dragPending = false;
				this.dragOffset = 0;
				this.dragScroller = null;
			},
			// Ближайшая прокручиваемая область под пальцем: пока она не в самом верху,
			// движение вниз принадлежит ей, а не листу.
			findScroller(node) {
				const root = this.$refs.sheet;
				let el = node;

				while (el && el !== root && el.nodeType === 1) {
					const overflow = window.getComputedStyle(el).overflowY;
					if ((overflow === 'auto' || overflow === 'scroll') && el.scrollHeight > el.clientHeight)
						return el;

					el = el.parentNode;
				}

				return null;
			},
			detachDragListeners() {
				if (!this.dragEvents) return;

				window.removeEventListener(this.dragEvents[0], this.onDragMove);
				window.removeEventListener(this.dragEvents[1], this.onDragEnd);
				if (this.dragEvents[2])
					window.removeEventListener(this.dragEvents[2], this.onDragEnd);

				this.dragEvents = null;
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
