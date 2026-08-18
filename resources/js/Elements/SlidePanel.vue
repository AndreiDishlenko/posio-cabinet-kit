<template>

	<!-- Overlay -->
	<transition name="fade">
		<div
			v-if="isVisible"
			class="!m-0 fixed inset-0 bg-black bg-opacity-50 z-40"
			@click="close"
			>
		</div>
	</transition>

	<!-- Sliding Panel -->
	<transition name="slide">
		<div v-if="isVisible"
			class="fixed z-50 top-0 right-0 h-full v-flex w-full sm:w-2/5 overflow-y-auto main-background"
			@click.stop
			>
			<!-- Header -->
			<div class="sticky top-0 header-background border-b border-gray-700 p-4 flex items-center justify-between">
				<h2 class="text-xl font-semibold text-white">{{ $t(header) }}</h2>
				<button
					@click="close"
					class="text-gray-400 hover:text-white p-2 rounded-lg hover:bg-gray-700 active:bg-gray-600 transition-colors touch-manipulation"
					>
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
					</svg>
				</button>
			</div>

			<!-- Content -->
			<div class="grow v-flex p-3">
				<slot />
			</div>

		</div>
	</transition>
	
</template>

<script>
	import { pushOverlay, popOverlay } from '@/js/overlayHistory';

	export default {
		name: 'SlidePanel',

		props: {
			header: {
				type: String,
				default: ''
			}
		},
		data() {
			return {
				isVisible: false,
			}
		},

		beforeUnmount() {
			popOverlay(this);
		},

		methods: {
			open(dish_data = []) {
				// if ( !dish_data || !dish_data.length )
				// 	console.wrn('Error opening dish editor', dish_data);

				// this.dish_data = dish_data;
				// this.parent_item = dish_data.find(t => t.id_id == 0)

				if (this.isVisible) return;

				this.isVisible = true
				pushOverlay(this, this.dismiss)
			},

			close() {
				if (!this.isVisible) return;

				popOverlay(this)
				this.dismiss()
			},

			// Скрытие без работы с историей — её запись к этому моменту уже снята
			// возвратом назад.
			dismiss() {
				this.isVisible = false
			},

			save() {
				// Валидация
				if (!this.dish.name.trim()) {
					alert('Введите название блюда')
					return
				}
				
				// Отправка события с данными
				this.$emit('save', { ...this.dish })
				this.hide()
			}
		}
	}
</script>

<style scoped>
	/* Fade transition for overlay */
	.fade-enter-active,
	.fade-leave-active {
		transition: opacity 0.3s ease;
	}

	.fade-enter-from,
	.fade-leave-to {
		opacity: 0;
	}

	/* Slide transition for panel */
	.slide-enter-active,
	.slide-leave-active {
		transition: transform 0.3s ease;
	}

	.slide-enter-from,
	.slide-leave-to {
		transform: translateX(100%);
	}

	/* Touch optimization */
	.touch-manipulation {
		-webkit-tap-highlight-color: transparent;
		touch-action: manipulation;
	}

	/* Scrollbar styling for dark theme */
	::-webkit-scrollbar {
		width: 8px;
	}

	::-webkit-scrollbar-track {
		background: #1f2937;
	}

	::-webkit-scrollbar-thumb {
		background: #4b5563;
		border-radius: 4px;
	}

	::-webkit-scrollbar-thumb:hover {
		background: #6b7280;
	}
</style>