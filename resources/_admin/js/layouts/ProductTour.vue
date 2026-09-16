<template>
	<Spotlight
		:steps="steps"
		finish_label="Let's get started"
		@step-leave="onStepLeave"
		@finished="onFinished"
	/>
</template>

<script>
	import Spotlight from './Spotlight.vue';

	export default {
		name: 'ProductTour',
		components: { Spotlight },

		props: {
			// Переопределяет встроенный сценарий тура; если пусто — используется tourSteps
			customSteps: {
				type: Array,
				default: () => [],
				// step: { target, title, textKey, position, linkText?, closeMenuAfter? }
			},
		},

		emits: ['finished'],

		data() {
			return {
				// Встроенный data-driven сценарий обучения.
				// Порядок ведёт от того, что уже готово (товар создан за пользователя),
				// к тому, что ему предстоит сделать самому (подключить кассу).
				tourSteps: [
					{
						target:   '#menu-cabinet-products',
						title:    'Products',
						textKey:  'tour-products-text',
						position: 'right',
					},
					{
						target:         '#menu-cabinet-report-sales',
						title:          'Sales Report',
						textKey:        'tour-sales-text',
						position:       'right',
						closeMenuAfter: true,
					},
					// Шаг скрыт вместе с иконкой чата в шапке: без цели в разметке
					// подсветка ждёт её и тур встаёт на затемнённом экране.
					// {
					// 	target:   '[data-tour="ai-chat"]',
					// 	title:    'AI Assistant',
					// 	textKey:  'tour-assistant-text',
					// 	position: 'bottom',
					// },
					{
						target:   '.cashbox-card:first-child',
						title:    'License',
						textKey:  'tour-cashbox-text',
						linkText: 'app.posio.com.ua',
						position: 'bottom',
					},
				],
			}
		},

		computed: {
			steps() {
				return this.customSteps.length ? this.customSteps : this.tourSteps;
			},
		},

		methods: {
			onStepLeave(step) {
				if ( step.closeMenuAfter )
					this.$emitter.emit('close_side_menu');
			},

			onFinished(result) {
				this.$emitter.emit('tour_restore_groups');
				this.$emit('finished', result);
			},
		},

		mounted() {
			this.$nextTick(() => {
				this.$emitter.emit('open_side_menu');
				// Раскрываем все группы меню, иначе пункты в свёрнутых группах
				// (напр. #menu-cabinet-docs-retail) отсутствуют в DOM и шаг тура не находит цель
				this.$emitter.emit('tour_show_all_groups');
			});
		},
	}
</script>
