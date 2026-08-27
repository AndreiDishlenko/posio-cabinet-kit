<template>

	<div ref="root" class="floating-dock-item" :class="{ 'floating-dock-item--local': local }" :style="dock_style">
		<slot />
	</div>

</template>

<script>
	import { joinDock, leaveDock, setDockHeight, dockOffsetOf } from '@/js/floatingDock';

	// Обгортка будь-якого плаваючого елемента в нижньому правому куті екрана.
	// Стандартний спосіб виводити FAB і CTA: замість власного position:fixed
	// елемент стає в спільну чергу й отримує місце над тими, що вже видимі.
	export default {
		name: 'FloatingDock',
		props: {
			// Елемент живе в куті свого контейнера, а не екрана (напр. таблиця з
			// власною прокруткою тіла). Черга при цьому не потрібна: кут у кожного
			// контейнера свій.
			local: {
				type: Boolean,
				default: false,
			},
		},
		data() {
			return {
				dock_id: 0,
			};
		},
		computed: {
			// Місце в стовпчику: сумарна висота сусідів, що стоять нижче. Читання
			// спільної черги робить властивість реактивною — елемент опускається
			// сам, щойно нижній сусід зникає.
			offset() {
				return this.dock_id ? dockOffsetOf(this.dock_id) : 0;
			},
			dock_style() {
				return {
					'--dock-offset': this.offset + 'px',
				};
			},
		},
		mounted() {
			if ( this.local )
				return;

			this.dock_id = joinDock();
			this.syncHeight();

			// Висота елемента змінюється разом із його вмістом (згорнутий список
			// перших кроків проти розгорнутого), тож стежимо, а не міряємо раз.
			if ( typeof ResizeObserver !== 'undefined' ) {
				this.resize_observer = new ResizeObserver(() => this.syncHeight());
				this.resize_observer.observe(this.$refs.root);
			}
		},
		beforeUnmount() {
			this.resize_observer?.disconnect();

			if ( this.dock_id )
				leaveDock(this.dock_id);
		},
		methods: {
			syncHeight() {
				if ( !this.dock_id )
					return;

				setDockHeight(this.dock_id, this.$refs.root?.offsetHeight ?? 0);
			},
		},
	}
</script>

<style lang="scss" scoped>
	.floating-dock-item {
		position: fixed;
		right: var(--floating-panel-offset);
		bottom: calc( var(--floating-panel-offset) + var(--dock-offset, 0px) );
		z-index: 900;

		// Сусід знизу зникає — елемент опускається на його місце плавно, інакше
		// стовпчик смикається при кожній появі кнопки на прокрутці.
		transition: bottom 0.2s ease, opacity 0.2s ease;

		// Спокійний стан — злегка прозорий, щоб не перебивати вміст під собою;
		// коли до елемента тягнуться, він стає повністю непрозорим. Однаково для
		// всіх плаваючих елементів кута, щоб стовпчик виглядав цілісно.
		opacity: 0.85;

		&:hover,
		&:focus-within {
			opacity: 1;
		}

		// Над нижньою панеллю вкладок — вона перекриває нижній край екрана телефона
		@media (max-width: 767.98px) and (orientation: portrait) {
			bottom: calc( var(--bottom-tab-bar-total, 0px) + 0.5rem + var(--dock-offset, 0px) );
		}
	}

	// Локальний режим: кут власного контейнера, без спільної черги.
	.floating-dock-item--local {
		position: absolute;
		bottom: var(--floating-panel-offset);
	}
</style>
