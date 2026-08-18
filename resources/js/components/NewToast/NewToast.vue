<template>

	<Teleport to="body">
		<div
			v-for="pos in usedPositions"
			:key="pos"
			class="nt-stack"
			:class="`nt-stack--${pos}`"
			:data-theme="isDark ? 'dark' : 'light'"
		>
			<TransitionGroup name="nt-fade" tag="div" class="nt-stack__list" appear>
				<NewToastItem
					v-for="item in groupedItems[pos]"
					:key="item.id"
					:item="item"
					@close="onClose"
				/>
			</TransitionGroup>
		</div>
	</Teleport>

</template>

<script>
	import NewToastItem from './NewToastItem.vue';
	import { NewToastState, NewToast } from './NewToast.js';

	const POSITIONS = [
		'top-left', 'top-center', 'top-right',
		'bottom-left', 'bottom-center', 'bottom-right',
	];

	export default {
		name: 'NewToast',

		components: { NewToastItem },

		data() {
			return {
				state: NewToastState,
				isDark: document.documentElement.classList.contains('dark')
					|| document.documentElement.classList.contains('frontdarknew'),
			};
		},

		mounted() {
			this._observer = new MutationObserver(() => {
				const cl = document.documentElement.classList;
				this.isDark = cl.contains('dark') || cl.contains('frontdarknew');
			});
			this._observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
		},

		beforeUnmount() {
			this._observer?.disconnect();
		},

		computed: {
			groupedItems() {
				const groups = {};
				for (const p of POSITIONS) groups[p] = [];
				for (const item of this.state.items) {
					const pos = groups[item.position] ? item.position : 'bottom-center';
					groups[pos].push(item);
				}
				return groups;
			},
			usedPositions() {
				return POSITIONS.filter(p => this.groupedItems[p].length > 0);
			},
		},

		methods: {
			onClose(id) {
				NewToast.close(id);
			},
		},
	};
</script>

<style lang="scss" scoped>
	.nt-stack {
		position: fixed;
		z-index: 9999;
		display: flex;
		pointer-events: none;
		padding: 16px;
		width: 460px;
		max-width: 100vw;
		// Стек не блокирует клики на пустых участках страницы
	}

	.nt-stack__list {
		display: flex;
		flex-direction: column;
		@include flex-gap(10px, column);
		width: 100%;
		pointer-events: none;
	}

	// --- Positions ---
	// Top: стек вверху, список растёт вниз (новые элементы внизу).
	.nt-stack--top-left,
	.nt-stack--top-center,
	.nt-stack--top-right {
		top: 0;
		justify-content: flex-start;
	}
	.nt-stack--top-left    { left: 0;   .nt-stack__list { align-items: flex-start; } }
	.nt-stack--top-center  { left: 50%; transform: translateX(-50%); .nt-stack__list { align-items: center; } }
	.nt-stack--top-right   { right: 0;  left: auto; .nt-stack__list { align-items: flex-end; } }

	// Bottom: стек прижат к низу. Список растёт сверху вниз, новые элементы внизу.
	// При появлении нового toast — старые сдвигаются ВВЕРХ (через FLIP align-end).
	.nt-stack--bottom-left,
	.nt-stack--bottom-center,
	.nt-stack--bottom-right {
		top: 0;
		bottom: 0;
		justify-content: flex-end;
		padding-top: 16px;
		padding-bottom: 16px;
	}
	.nt-stack--bottom-left   { left: 0;   .nt-stack__list { align-items: flex-start; } }
	.nt-stack--bottom-center { left: 50%; transform: translateX(-50%); .nt-stack__list { align-items: center; } }
	.nt-stack--bottom-right  { right: 0;  left: auto; .nt-stack__list { align-items: flex-end; } }

	// --- Move: плавное смещение соседних тостов ---
	.nt-fade-move {
		transition: transform 480ms cubic-bezier(.2, 0, 0, 1);
	}

	// При удалении элемент должен покинуть поток, иначе FLIP-move не сработает корректно
	.nt-fade-leave-active {
		position: absolute;
	}
</style>

<style lang="scss">
	// Не-scoped: анимации работают на элементах внутри Teleport

	.nt-fade-enter-active {
		animation: nt-spring-in 560ms cubic-bezier(.2, 0, 0, 1) both;
	}

	.nt-fade-leave-active {
		animation: nt-slide-out 200ms cubic-bezier(.4, 0, 1, 1) both;
		pointer-events: none;
	}

	@keyframes nt-spring-in {
		0%   { opacity: 0; transform: translateY(48px) scale(.86); }
		45%  { opacity: 1; transform: translateY(-8px) scale(1.02); }
		68%  { opacity: 1; transform: translateY(4px)  scale(.998); }
		84%  { opacity: 1; transform: translateY(-2px) scale(1.002); }
		100% { opacity: 1; transform: translateY(0)    scale(1); }
	}

	@keyframes nt-slide-out {
		0%   { opacity: 1; transform: translateY(0)    scale(1); }
		100% { opacity: 0; transform: translateY(16px) scale(.94); }
	}
</style>
