<template>
	<div v-if="isVisible">

		<!-- Overlay сегменты (только после того, как цель измерена) -->
		<template v-if="hintReady">
			<div class="spo-seg" :style="segTop"></div>
			<div class="spo-seg" :style="segBottom"></div>
			<div class="spo-seg" :style="segLeft"></div>
			<div class="spo-seg" :style="segRight"></div>

			<!-- Рамка вокруг цели -->
			<div v-if="framed" class="spo-frame" :style="frameStyle"></div>

			<!-- Блокер кликов: перехватывает любые клики по странице (включая
				 подсвеченную цель). Хинт выше по z-index, поэтому остаётся кликабельным -->
			<div
				class="spo-blocker"
				@click.stop.prevent
				@mousedown.stop.prevent
				@contextmenu.stop.prevent
			></div>
		</template>

		<!-- Hint -->
		<div
			v-if="hintReady"
			ref="hint"
			class="spo-hint compact-card"
			:class="{ 'spo-hint--measuring': !hintPlaced }"
			:style="_hintStyle"
		>

			<!-- Прогресс -->
			<div v-if="steps.length > 1" class="spo-progress">
				<div
					v-for="(_, i) in steps"
					:key="i"
					class="spo-progress-dot"
					:class="{ 'spo-progress-dot--active': i === stepIndex }"
				></div>
			</div>

			<div class="spo-hint-title">{{ $t(currentStep.title) }}</div>

			<div class="spo-hint-text">
				<template v-if="currentStep.linkText">
					<span v-for="(part, i) in textParts" :key="i">
						<a v-if="part.isLink"
							:href="'https://' + part.text"
							target="_blank"
							rel="noopener"
							class="spo-hint-link"
						>{{ part.text }}</a>
						<template v-else>{{ part.text }}</template>
					</span>
				</template>
				<template v-else>{{ $t(currentStep.textKey) }}</template>
			</div>

			<div class="spo-hint-footer">
				<button v-if="skippable" class="button button-sm outline-button" @click="skip">{{ $t(skip_label) }}</button>
				<button class="button button-sm primary-button" @click="next">
					{{ isLastStep ? $t(finish_label) : $t(next_label) }}
				</button>
			</div>

		</div>

	</div>
</template>

<script>
	// Общее ядро подсветок: затемняет экран вокруг цели, обводит её рамкой,
	// перехватывает клики и подвешивает рядом карточку с текстом. Что именно
	// подсвечивать, когда и с какими последствиями — дело того, кто его подключает.

	const HINT_WIDTH = 300;
	// Зазор между целью и карточкой — поверх отступа выреза в затемнении
	const HINT_GAP   = 14;
	// Минимальный отступ карточки от края экрана
	const VIEWPORT_MARGIN = 12;

	export default {
		name: 'Spotlight',

		props: {
			// step: { target, title, textKey, position, linkText? }
			steps: {
				type: Array,
				required: true,
			},
			skippable: {
				type: Boolean,
				default: true,
			},
			// Обводка цели поверх выреза в затемнении: нужна там, где вырез
			// приходится на однотонный участок и сам по себе не читается
			framed: {
				type: Boolean,
				default: true,
			},
			skip_label: {
				type: String,
				default: 'Skip',
			},
			next_label: {
				type: String,
				default: 'Next',
			},
			finish_label: {
				type: String,
				default: 'Got it',
			},
			// Пауза перед первым шагом: даёт улечься тому, что подключивший
			// подсветку успевает открыть или перестроить на экране
			start_delay: {
				type: Number,
				default: 300,
			},
		},

		emits: ['finished', 'step-leave'],

		data() {
			return {
				isVisible: true,
				stepIndex: 0,
				hintReady: false,
				// Карточка отрисована и уже поставлена на место: до замера её
				// настоящей высоты показывать нечего — место может смениться.
				hintPlaced: false,
				currentTargetEl: null,
				targetRect: { top: 0, left: 0, width: 0, height: 0 },
				_hintStyle: {},
			}
		},

		computed: {
			currentStep() {
				return this.steps[this.stepIndex] ?? null;
			},
			isLastStep() {
				return this.stepIndex === this.steps.length - 1;
			},
			pad() { return 6; },

			// Разбивает текст на части: обычный текст и ссылка
			textParts() {
				const step = this.currentStep;
				if (!step?.linkText) return [];
				// Подставляем placeholder явно, чтобы потом найти ссылку по нему
				const placeholder = '\x00LINK\x00';
				const full = this.$t(step.textKey, { link: placeholder });
				const idx  = full.indexOf(placeholder);
				if (idx === -1) return [{ text: full.replace(placeholder, step.linkText), isLink: false }];
				return [
					{ text: full.slice(0, idx),                         isLink: false },
					{ text: step.linkText,                              isLink: true  },
					{ text: full.slice(idx + placeholder.length),       isLink: false },
				];
			},

			segTop() {
				const { top } = this.targetRect;
				return { top: 0, left: 0, right: 0, height: Math.max(0, top - this.pad) + 'px' };
			},
			segBottom() {
				const { top, height } = this.targetRect;
				return { top: (top + height + this.pad) + 'px', left: 0, right: 0, bottom: 0 };
			},
			segLeft() {
				const { top, left, height } = this.targetRect;
				const p = this.pad;
				return { top: (top - p) + 'px', left: 0, width: Math.max(0, left - p) + 'px', height: (height + p * 2) + 'px' };
			},
			segRight() {
				const { top, left, width, height } = this.targetRect;
				const p = this.pad;
				return { top: (top - p) + 'px', left: (left + width + p) + 'px', right: 0, height: (height + p * 2) + 'px' };
			},
			frameStyle() {
				const { top, left, width, height } = this.targetRect;
				const p = this.pad;
				return { top: (top - p) + 'px', left: (left - p) + 'px', width: (width + p * 2) + 'px', height: (height + p * 2) + 'px' };
			},
		},

		methods: {

			activateStep(index) {
				this.hintReady = false;
				this.hintPlaced = false;
				this.currentTargetEl = null;

				const step = this.steps[index];
				if (!step) return;

				this.waitForElement(step.target, (el) => {
					this.currentTargetEl = el;
					// Прокручиваем контейнер-скроллер так, чтобы подсвечиваемый пункт был
					// максимально по центру по высоте (для пунктов меню скроллится сам
					// контейнер меню — стандартное поведение меню при этом не меняется)
					el.scrollIntoView({ behavior: 'instant', block: 'center' });

					setTimeout(() => {
						const rect = el.getBoundingClientRect();
						this.targetRect = { top: rect.top, left: rect.left, width: rect.width, height: rect.height };
						// Первый проход — только чтобы карточка получила ширину и
						// отрисовалась: настоящее место считается по её высоте.
						this._hintStyle = { position: 'fixed', top: '0px', left: '0px', width: HINT_WIDTH + 'px', zIndex: 10004 };
						this.hintReady = true;

						this.$nextTick(() => this.placeHint(step));
					}, 350);
				});
			},

			next() {
				if (this.isLastStep) {
					this.finish();
				} else {
					this.$emit('step-leave', this.currentStep, this.stepIndex);
					this.stepIndex++;
					this.activateStep(this.stepIndex);
				}
			},

			skip() {
				this.isVisible = false;
				this.$emit('finished', { skipped: true });
			},

			finish() {
				this.isVisible = false;
				this.$emit('finished', { skipped: false });
			},

			waitForElement(selector, callback, attempts = 0) {
				const el = document.querySelector(selector);
				if (el) { callback(el); return; }
				if (attempts < 30) {
					setTimeout(() => this.waitForElement(selector, callback, attempts + 1), 150);
				}
			},

			// Ставит карточку по её настоящей высоте: постоянного размера у подсказки
			// нет — длина текста шага и число кнопок меняют её в полтора раза, и по
			// заранее угаданной высоте карточка ложится поверх того, что подсвечивает.
			placeHint(step) {
				const el = this.$refs.hint;

				if ( !el )
					return;

				this._hintStyle = this.calcHintStyle(this.targetRect, step.position ?? 'bottom', el.offsetHeight);
				this.hintPlaced = true;
			},

			calcHintStyle(rect, position, hintH) {
				const p      = this.pad;
				const vp     = { w: window.innerWidth, h: window.innerHeight };
				const bottom = rect.top + rect.height;
				const right  = rect.left + rect.width;

				// Свободное место с каждой стороны цели — за вычетом выреза и зазора
				const space = {
					top:    rect.top - p - HINT_GAP,
					bottom: vp.h - bottom - p - HINT_GAP,
					left:   rect.left - p - HINT_GAP,
					right:  vp.w - right - p - HINT_GAP,
				};
				const need = side => ( side === 'top' || side === 'bottom' ) ? hintH : HINT_WIDTH;
				const fits = side => space[side] >= need(side);

				// Предпочтительная сторона не вмещает карточку — берём первую, которая
				// вмещает; если не вмещает ни одна, самую просторную из них.
				if ( !fits(position) ) {
					const sides = ['bottom', 'top', 'right', 'left'];

					position = sides.find(fits)
						|| sides.reduce((a, b) => ( space[a] - need(a) >= space[b] - need(b) ? a : b ));
				}

				let top, left;
				if (position === 'bottom') {
					top  = bottom + p + HINT_GAP;
					left = rect.left + rect.width / 2 - HINT_WIDTH / 2;
				} else if (position === 'top') {
					top  = rect.top - p - hintH - HINT_GAP;
					left = rect.left + rect.width / 2 - HINT_WIDTH / 2;
				} else if (position === 'right') {
					top  = rect.top + rect.height / 2 - hintH / 2;
					left = right + p + HINT_GAP;
				} else {
					top  = rect.top + rect.height / 2 - hintH / 2;
					left = rect.left - p - HINT_WIDTH - HINT_GAP;
				}

				left = Math.max(VIEWPORT_MARGIN, Math.min(left, vp.w - HINT_WIDTH - VIEWPORT_MARGIN));
				top  = Math.max(VIEWPORT_MARGIN, Math.min(top,  vp.h - hintH - VIEWPORT_MARGIN));

				return { position: 'fixed', top: top + 'px', left: left + 'px', width: HINT_WIDTH + 'px', zIndex: 10004 };
			},
		},

		mounted() {
			this.$nextTick(() => {
				setTimeout(() => this.activateStep(0), this.start_delay);
			});
		},

		beforeUnmount() {
			this.currentTargetEl = null;
		},
	}
</script>

<style lang="scss">

	.spo-seg {
		position: fixed;
		background: rgba(0, 0, 0, 0.65);
		z-index: 10001;
		pointer-events: none;
	}

	.spo-frame {
		position: fixed;
		z-index: 10002;
		border-radius: 8px;
		outline: 2px solid var(--border-color);
		pointer-events: none;
	}

	.spo-blocker {
		position: fixed;
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		z-index: 10003;
		background: transparent;
		pointer-events: auto;
	}

	.spo-hint {
		position: fixed;
		border: 0px;
		box-sizing: border-box;
		box-shadow: 0 8px 32px rgba(0, 0, 0, 0.22);
		display: flex;
		flex-direction: column;
		@include flex-gap(10px, column);
		pointer-events: all;
		z-index: 10003;
	}

	// Карточка на замере: место ей ещё не найдено, показывать её нельзя,
	// но размеры должна дать настоящие.
	.spo-hint--measuring {
		visibility: hidden;
	}

	.spo-progress {
		display: flex;
		@include flex-gap(5px);
		align-items: center;
	}

	.spo-progress-dot {
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: #d1d5db;
		transition: background 0.2s, transform 0.2s;

		&--active {
			background: #3b82f6;
			transform: scale(1.3);
		}
	}

	.spo-hint-title {
		font-size: 14px;
		font-weight: 600;
		color: white;
	}

	.spo-hint-text {
		font-size: 13px;
		line-height: 1.5;
		white-space: pre-line;
	}

	.spo-hint-link {
		color: #3b82f6;
		text-decoration: underline;

		&:hover {
			color: #2563eb;
		}
	}

	.spo-hint-footer {
		display: flex;
		align-items: center;
		justify-content: flex-end;
		flex-wrap: wrap;
		gap: 8px;
		margin-top: 2px;

		// Ряд с переносом: отступ раздаётся всем кнопкам, лишняя рамка снимается
		// отрицательным полем ряда — собственный верхний отступ учтён в нём же.
		html.no-flex-gap & {
			margin: -4px;
			margin-top: -2px;

			> * {
				margin: 4px;
			}
		}

		.button {
			white-space: nowrap;
			flex-shrink: 0;
		}
	}

</style>
