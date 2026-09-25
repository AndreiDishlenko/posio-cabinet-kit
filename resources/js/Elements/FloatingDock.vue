<template>

	<div ref="root" class="floating-dock-item" :class="{ 'floating-dock-item--local': local }" :style="dock_style">
		<slot />
	</div>

</template>

<script>
	import { joinDock, leaveDock, setDockHeight, dockOffsetOf, openDockWidget, closeDockWidget, openedDockWidget } from '@/js/floatingDock';

	// Ознака розкритого віджета не залежить від місця в черзі: у куті власного
	// контейнера елемент до черги не входить, але витісняти сусідів має так само.
	let last_widget_id = 0;

	// Скільки стежити за переїздом елемента на нове місце. Має перекривати
	// тривалість переходу нижнього відступу зі стилів нижче: доки елемент їде,
	// його положення ще не остаточне.
	const MOVE_TRACK_MS = 300;

	// Обгортка будь-якого плаваючого елемента в нижньому правому куті екрана.
	// Стандартний спосіб виводити FAB і CTA: замість власного position:fixed
	// елемент стає в спільну чергу й отримує місце над тими, що вже видимі.
	export default {
		name: 'FloatingDock',
		emits: ['collapse', 'move'],
		props: {
			// Елемент живе в куті свого контейнера, а не екрана (напр. таблиця з
			// власною прокруткою тіла). Черга при цьому не потрібна: кут у кожного
			// контейнера свій.
			local: {
				type: Boolean,
				default: false,
			},
			// Вага в стовпчику: менша — ближче до краю екрана. Задає стале місце
			// елемента незалежно від того, хто зʼявився раніше (кнопка списку
			// виринає на прокрутці, коли решта кута вже стоїть).
			weight: {
				type: Number,
				default: 0,
			},
			// Всередині розкрите вікно (панель помічника, список кроків, меню дій).
			// Розкритий у куті лишається один: решта отримує запит на згортання.
			expanded: {
				type: Boolean,
				default: false,
			},
		},
		data() {
			return {
				dock_id: 0,
				widget_id: ++last_widget_id,
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
			opened_widget() {
				return openedDockWidget();
			},
		},
		watch: {
			// Сусід згорнувся або зник — місце змінилось. Вміст, вирівняний по кутовому
			// елементу (вікно помічника стоїть рівно над своєю кнопкою), мусить знати
			// про переїзд, інакше лишиться там, де було звільнене місце.
			offset() {
				this.trackMove();
			},
			expanded(open) {
				if ( open )
					openDockWidget(this.widget_id);
				else
					closeDockWidget(this.widget_id);
			},
			// Розкрився хтось інший — просимо власника згорнутися. Гасити вміст
			// звідси не можна: у кожного віджета своє прощання (запамʼятати вибір,
			// прибрати слухачів).
			opened_widget(id) {
				if ( this.expanded && id !== this.widget_id )
					this.$emit('collapse');
			},
		},
		mounted() {
			if ( this.expanded )
				openDockWidget(this.widget_id);

			if ( this.local )
				return;

			this.dock_id = joinDock(this.weight);
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

			if ( this.move_frame )
				cancelAnimationFrame(this.move_frame);

			closeDockWidget(this.widget_id);

			if ( this.dock_id )
				leaveDock(this.dock_id);
		},
		methods: {
			// Переїзд плавний, тож кінцеве положення відоме тільки в кінці — а разовий
			// замір у момент відкриття дав би старе місце. Тому сповіщаємо щокадру,
			// поки елемент їде: вміст переїжджає разом з ним, без стрибка в кінці.
			trackMove() {
				this.move_until = Date.now() + MOVE_TRACK_MS;

				if ( this.move_frame )
					return;

				const step = () => {
					this.$emit('move');

					this.move_frame = Date.now() < this.move_until ? requestAnimationFrame(step) : 0;
				};

				this.move_frame = requestAnimationFrame(step);
			},

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
