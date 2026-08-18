<template>

	<div class="burger-wrapper contents">

		<slot name="trigger" :toggle="toggle"/>

		<!-- Мобильный: меню выезжает снизу -->
		<BottomSheet
			v-if="isMounted && isMobile"
			ref="sheet"
			:breakpoint="mobile_breakpoint"
			@close="onSheetClose"
			>
			<BurgerMenuBody :show_user_info="show_user_info" :show_profile_divider="show_profile_divider" @close="close" @close-silently="closeSilently">
				<slot :close="close" :close-silently="closeSilently"/>
			</BurgerMenuBody>
		</BottomSheet>

		<!-- Десктоп: плавающая карточка поверх шапки -->
		<teleport v-else-if="isMounted" to="body">

			<div v-if="isOpen" class="burger-backdrop fixed inset-0 z-[900]" @click="close"/>

			<transition name="burger-drop">
				<div
					v-if="isOpen"
					class="burger-panel pt-2 pb-4 fixed z-[1100] flex flex-col overflow-y-auto"
					@click.stop=""
					>
					<BurgerMenuBody :show_user_info="show_user_info" :show_profile_divider="show_profile_divider" @close="close" @close-silently="closeSilently">
						<slot :close="close" :close-silently="closeSilently"/>
					</BurgerMenuBody>
				</div>
			</transition>

		</teleport>

	</div>

</template>

<script>
	import BottomSheet    from '@/js/Elements/BottomSheet.vue';
	import BurgerMenuBody from '@/js/Components/BurgerMenu/BurgerMenuBody.vue';

	export default {
		components: { BottomSheet, BurgerMenuBody },
		expose: ['toggle', 'open', 'close', 'closeSilently'],
		emits: ['open', 'close'],
		props: {
			show_user_info: {
				type: Boolean,
				default: false
			},
			// Отключается, когда сразу под профилем идёт собственный блок меню
			// (например переключатель аккаунта) — он сам отбивает шапку от пунктов.
			show_profile_divider: {
				type: Boolean,
				default: true
			}
		},
		data() {
			return {
				isOpen: false,
				isMounted: false,
				isMobile: false,
				mediaQuery: null,
				mobile_breakpoint: 640,
			}
		},
		mounted() {
			this.isMounted = true;
			this.mediaQuery = window.matchMedia(`(max-width: ${this.mobile_breakpoint - 1}px)`);
			this.isMobile = this.mediaQuery.matches;
			this.mediaQuery.addEventListener('change', this.onMediaChange);
			document.addEventListener('keydown', this.handleEsc);
			window.addEventListener('scroll', this.handleScroll, { passive: true });
		},
		beforeUnmount() {
			if (this.mediaQuery)
				this.mediaQuery.removeEventListener('change', this.onMediaChange);
			document.removeEventListener('keydown', this.handleEsc);
			window.removeEventListener('scroll', this.handleScroll);
		},
		methods: {
			toggle() {
				this.isOpen ? this.close() : this.open();
			},
			open() {
				if (this.isOpen)
					return;

				this.isOpen = true;

				if (this.isMobile && this.$refs.sheet)
					this.$refs.sheet.open();

				this.$emit('open');
			},
			close() {
				if (!this.isOpen)
					return;

				this.isOpen = false;

				if (this.isMobile && this.$refs.sheet)
					this.$refs.sheet.close();

				this.$emit('close');
			},
			// Закрытие как следствие перехода по ссылке в меню — на мобильном
			// не трогаем историю (см. overlayHistory.js), иначе теряется сам переход.
			closeSilently() {
				if (!this.isOpen)
					return;

				this.isOpen = false;

				if (this.isMobile && this.$refs.sheet)
					this.$refs.sheet.closeSilently();

				this.$emit('close');
			},
			// Лист снялся своими средствами — свайпом, подложкой или возвратом назад.
			onSheetClose() {
				this.close();
			},
			handleEsc(e) {
				if (e.key === 'Escape' && this.isOpen)
					this.close();
			},
			// Прокрутка страницы под открытой карточкой означает, что пользователь
			// вернулся к содержимому; на мобильном прокрутка под листом заблокирована.
			handleScroll() {
				if (this.isOpen && !this.isMobile)
					this.close();
			},
			onMediaChange(e) {
				this.close();
				this.isMobile = e.matches;
			},
		},
	}
</script>

<style lang="scss">

	.burger-panel {
		top: var(--floating-panel-offset);
		right: var(--floating-panel-offset);
		width: 330px;
		max-width: calc( 100vw - var(--floating-panel-offset) * 2 );
		max-height: calc( 100vh - var(--floating-panel-offset) * 2 );
		border-radius: var(--floating-panel-radius);
		box-shadow: var(--floating-panel-shadow);
		background-color: var(--burger-menu-bg);
		// Панель лежит на собственном фоне, отличном от общего фона темы, поэтому
		// разделители внутри отсчитываются от него же — как и в мобильном листе.
		--card-divider: var(--bottom-sheet-divider);
		transform-origin: top right;
		scrollbar-width: none;
		-ms-overflow-style: none;

		&::-webkit-scrollbar {
			display: none;
		}

		.burger-profile {
			padding: 0.75rem 1rem;
		}

		.burger-profile__name {
			font-size: 0.9rem;
		}

		.burger-profile__email {
			font-size: 0.75rem;
		}

		.bdm-nav {
			padding: 0.25rem 0.5rem;
		}

		.bdm-nav-item {
			border-radius: 0.375rem;
			border-bottom: none;

			&:last-child {
				border-bottom: none;
			}
		}
	}

	// Динамическую высоту вьюпорта Apple понимает с 15.4. Парное объявление здесь
	// не спасает: в декларации есть переменная, и непонятая единица не откатывает
	// строку, а обнуляет свойство — поэтому подмена по признаку поддержки.
	@supports (height: 100dvh) {

		.burger-panel {
			max-height: calc( 100dvh - var(--floating-panel-offset) * 2 );
		}

	}

	.bdm-nav {
		display: flex;
		flex-direction: column;
		padding: 0.5rem 0;
	}

	.burger-drop-enter-active {
		transition: opacity 0.18s ease, transform 0.18s cubic-bezier(0.4, 0, 0.2, 1);
	}
	.burger-drop-leave-active {
		transition: opacity 0.15s ease, transform 0.15s cubic-bezier(0.4, 0, 0.2, 1);
	}
	.burger-drop-enter-from,
	.burger-drop-leave-to {
		opacity: 0;
		transform: translateY(-0.5rem) scale(0.98);
	}

</style>
