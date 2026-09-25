<template>

	<div class="pull-reveal v-flex">

		<!-- Скрытая строка: высоту ей задаёт жест, содержимое остаётся в раскладке -->
		<div
			ref="panel"
			class="pull-reveal-panel"
			:class="{ 'pull-reveal-row': enabled, 'is-settling': settling }"
			>
			<div ref="panel_inner" class="pull-reveal-panel-inner">
				<slot name="panel" />
			</div>
		</div>

		<!-- Зона жеста: протягивание её содержимого вниз вытягивает строку -->
		<div class="pull-reveal-zone v-flex grow"
			ref="zone"
			@touchstart   = "onTouchStart"
			@touchmove    = "onTouchMove"
			@touchend     = "onTouchEnd"
			@touchcancel  = "onTouchEnd"
			>

			<!-- Признак вытягиваемой строки: без него скрытую строку не найти,
				 и по нему же удобно тянуть прицельно -->
			<div v-if="enabled" class="pull-reveal-handle" @click="toggle">
				<span class="pull-reveal-grip"></span>
			</div>

			<slot />

		</div>

	</div>

</template>

<script>
	import { createPullReveal } from '@/js/pullReveal';

	export default {
		name: 'PullRevealPanel',
		props: {
			// Вытягивание нужно только там, где строке не хватает места (телефон);
			// иначе содержимое строки просто стоит над зоной, как обычный блок.
			enabled: {
				type: Boolean,
				default: true,
			},
		},
		emits: ['reveal', 'collapse'],
		data() {
			return {
				// Раскрыта ли строка и идёт ли доводка после отпускания
				open: false,
				settling: false,
			}
		},
		watch: {
			// Переход к узкому экрану начинает со свёрнутой строки: высота, оставшаяся
			// от прошлого раза, относилась к другой раскладке.
			enabled(value) {
				if ( value )
					this.collapse(true);
			},
		},
		created() {
			// Ведение жеста держим вне реактивных данных: его состояние меняется
			// каждый кадр и в разметке не участвует.
			this.pull_reveal = createPullReveal({
				enabled:       () => this.enabled && !!this.$refs.panel,
				// Прокручиваемый предок ищется до самого верха страницы: у кабинета
				// прокручивается слой над содержимым, а не оно само.
				boundary:      () => null,
				fullHeight:    () => this.fullHeight(),
				currentHeight: () => this.currentHeight(),
				begin:         () => this.beginReveal(),
				setHeight:     (px) => this.setHeight(px),
				settle:        (open, duration) => this.settle(open, duration),
			});
		},
		beforeUnmount() {
			this.pull_reveal.destroy();
		},
		methods: {
			onTouchStart(e) {
				this.pull_reveal.start(e);
			},
			onTouchMove(e) {
				this.pull_reveal.move(e);
			},
			onTouchEnd(e) {
				this.pull_reveal.end(e);
			},

			// Собственная высота содержимого — полностью раскрытое состояние.
			fullHeight() {
				const inner = this.$refs.panel_inner;

				return inner ? inner.offsetHeight : 0;
			},

			// Отрисованная высота прямо сейчас: с неё жест продолжает, если строку
			// поймали посреди доводки, — иначе она прыгнула бы к краю.
			currentHeight() {
				const panel = this.$refs.panel;

				return panel ? panel.getBoundingClientRect().height : 0;
			},

			beginReveal() {
				if ( !this.$refs.panel )
					return false;

				this.setHeight(this.currentHeight());
				this.settling = false;

				return true;
			},

			// Раскрытием управляет одна величина — высота, и жест пишет её прямо в
			// стиль узла: реактивное свойство перерисовывало бы страницу каждый кадр.
			setHeight(px) {
				const panel = this.$refs.panel;
				if ( !panel )
					return;

				panel.style.setProperty('--pull-reveal-height', Math.round(px * 10) / 10 + 'px');
			},

			// Доводка после отпускания: длительность приходит от жеста — чем быстрее
			// увели палец, тем короче добег, иначе движение читается как залипание.
			settle(open, duration) {
				const panel = this.$refs.panel;
				if ( panel )
					panel.style.transitionDuration = Math.round(duration || 240) + 'ms';

				this.settling = true;
				this.open     = open;
				this.setHeight(open ? this.fullHeight() : 0);

				this.$emit(open ? 'reveal' : 'collapse');
			},

			// Тап по полоске — короткий путь к тому же, что делает жест.
			toggle() {
				this.settle(!this.open, 240);
			},

			collapse(immediate = false) {
				this.settling = !immediate;
				this.open     = false;
				this.setHeight(0);
			},

			reveal() {
				this.settle(true, 240);
			},
		},
	}
</script>

<style lang="scss" scoped>
	// Отступы строки живут внутри неё: в свёрнутом состоянии они уезжают вместе
	// с содержимым, и над зоной не остаётся пустой полосы. По той же причине
	// зазор сверху задаётся полем, а не внешним отступом — тот вышел бы за
	// пределы сворачиваемой строки и остался бы виден.
	.pull-reveal-panel-inner {
		padding-top:    var(--pull-reveal-gap-top, 0px);
		padding-bottom: var(--pull-reveal-gap, 1rem);
	}

	.pull-reveal-handle {
		display: flex;
		justify-content: center;
		align-items: center;
		// Полоска тонкая, поэтому попасть по ней пальцем помогает запас вокруг.
		padding-top: 2px;
		padding-bottom: 8px;
		cursor: pointer;
	}

	.pull-reveal-grip {
		display: block;
		width: 36px;
		height: 4px;
		border-radius: 4px;
		background: var(--text-color-disabled, #9a9a9a);
		opacity: 0.5;
	}
</style>
