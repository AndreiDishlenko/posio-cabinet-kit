<template>

	<div class="scroll-area" :class="{ 'scroll-area--thin': thin, 'scroll-area--horizontal': horizontal }">
		<slot></slot>
	</div>

</template>

<script>

	export default {
		name: 'ScrollArea',

		props: {
			// Вужча смуга — для щільних панелей, де звичайна відкушує помітну частину ширини.
			thin: {
				type: Boolean,
				default: false,
			},
			horizontal: {
				type: Boolean,
				default: false,
			},
		},

		methods: {
			scrollToBottom() {
				this.$el.scrollTop = this.$el.scrollHeight;
			},

			scrollToTop() {
				this.$el.scrollTop = 0;
			},
		},
	}

</script>

<style lang="scss" scoped>

	/* Оформлена смуга прокрутки замість системної: системна на Windows — широка
	   світла колонка, яка в темній темі читається як чужий елемент поверх панелі.
	   Кольори бігунка можна перекрити ззовні змінними, тому власних оголошень тут
	   немає — лише запасні значення в місці використання. */
	.scroll-area {
		overflow-y: auto;
		overflow-x: hidden;

		&::-webkit-scrollbar {
			width:  10px;
			height: 10px;
		}

		&::-webkit-scrollbar-track,
		&::-webkit-scrollbar-corner {
			background: transparent;
		}

		/* Прозора рамка з обрізкою фону по padding-box дає відступ бігунка від краю. */
		&::-webkit-scrollbar-thumb {
			background-color: var( --scroll-area-thumb, var(--scrollbar-thumb-light-color) );
			background-clip: padding-box;
			border: 3px solid transparent;
			border-radius: 999px;
		}

		&::-webkit-scrollbar-thumb:hover {
			background-color: var( --scroll-area-thumb-hover, var(--scrollbar-thumb-light-hover-color) );
		}
	}

	.scroll-area--thin {
		&::-webkit-scrollbar {
			width:  8px;
			height: 8px;
		}

		&::-webkit-scrollbar-thumb {
			border-width: 2px;
		}
	}

	.scroll-area--horizontal {
		overflow-x: auto;
	}

	/* Стандартні властивості смуги вимикають webkit-оформлення вище, тому задаються
	   лише там, де псевдоелементів немає (Firefox). */
	@supports not selector(::-webkit-scrollbar) {

		.scroll-area {
			scrollbar-width: auto;
			scrollbar-color: var( --scroll-area-thumb, var(--scrollbar-thumb-light-color) ) transparent;
		}

		.scroll-area--thin {
			scrollbar-width: thin;
		}

	}

</style>
