<template lang="">

    <div class="card items-stretch">

		<!-- Card header -->
		<div class="card-header">
			<div v-if="title" class="card-title text-center">{{ title ? $t(title) : '' }}</div>
			<div v-if="message" class="card-subheader text-center text-secondary w-full">
				{{ message ? $t(message) : message }}
			</div>		
		</div>

		<!-- Card body -->
		<div class="card-body" :class="body_classes">
        	<slot />
		</div>

		<!-- Card footer -->
		<div v-if="buttons.length" class="card-footer">
			<button v-for="(btn, index) in buttons" :key="index"
				class="button"
				:class="[btn.class, { spinner: btn.loading }]"
				:disabled="btn.disabled"
				@click="btn.action && btn.action()"
				>
				{{ $t(btn.label) }}
			</button>
		</div>

    </div>
	
</template>

<script>
    import { lockPageScroll, unlockPageScroll } from '@/js/pageScrollLock';

    export default {
        props: {
            title: {
                type: String,
                default: ''
            },
            message: {
                type: String,
                default: ''
            },
			buttons: {
                type: Array,
                default: () => [],
            },
			body_classes: {
				type: String,
				default: ''
			}
        },
        // Ограничение цепочки прокрутки Apple понимает только с 16: доскроллив тело
        // карточки до края, палец начинает тянуть подложку. Пока карточка открыта,
        // страница под ней фиксируется.
        mounted() {
            lockPageScroll(this);
        },
        beforeUnmount() {
            unlockPageScroll(this);
        }
    }
</script>

<style lang="scss" scoped>

	/* Modal context: never exceed the viewport — the body scrolls internally
	   while header and footer stay pinned. Width / radius are left to the
	   classes passed by the parent (w-lg, w-full h-full, …). */
	.card {
		// Статическая единица — фолбэк для Apple ниже 15.4 (динамическую там отбросят
		// вместе с ограничением высоты, и карточка вылезет за пределы экрана).
		max-height: 95vh;
		max-height: 95dvh;
		margin-left: auto;
		margin-right: auto;

		@apply
			font-normal
			!space-y-8;
	}

	/* Header stays fixed above the scrolling body */
	.card-header {
		// border:1px solid green;
		flex: 0 0 auto;

		@apply
			space-y-2;

		h2 {
			@apply font-semibold;
			letter-spacing: -0.015em;
		}
	}

	.card-title {
		@apply			
			font-bold
			!text-2xl;
	}

	.card-subheader {}

	/* Body takes the remaining height and scrolls on overflow */
	.card-body {
		// border:1px solid green;
		flex: 1 1 auto;
		min-height: 0;
		overflow-y: auto;
		// Удержание прокрутки внутри — с Safari 16; ниже прокрутка протекает на подложку.
		overscroll-behavior: contain;
		-webkit-overflow-scrolling: touch;

		scrollbar-width: thin;
		scrollbar-color: var(--divider-color) transparent;

		&::-webkit-scrollbar {
			width: 6px;
		}

		&::-webkit-scrollbar-thumb {
			background-color: var(--divider-color);
			border-radius: 9999px;
		}

		&::-webkit-scrollbar-track {
			background: transparent;
		}
	}

	/* Footer pinned at the bottom, separated by a hairline divider */
	.card-footer {
		// border:1px solid green;
		flex: 0 0 auto;
		// border-top: 1px solid var(--divider-color);

		// padding-top: 0px!important;
		// @apply pt-0;

		.button {
			@apply min-w-32;
		}
	}

	/* Mobile: tighter paddings, full-bleed split footer buttons */
	@media (max-width: 639px) {

		.card {
			max-height: 100vh;
			max-height: 100dvh;

			@apply p-5 space-y-5;
		}

		.card-footer {
			@apply pt-4 space-x-3;

			.button {
				@apply flex-1 min-w-0;
			}
		}
	}

</style>