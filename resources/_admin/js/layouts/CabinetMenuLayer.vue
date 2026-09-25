<template>

	<!--
		Слой бокового меню — сосед страницы, а не её часть: страница на каждом
		переходе создаётся заново, и меню вместе с ней теряло прокрутку панели
		(выбранный пункт ниже экрана уезжал из виду). Здесь панель монтируется
		один раз и переход её не трогает.

		Слой выведен из потока, поэтому место под меню в раскладке страницы
		держит распорка в каркасе страницы — по той же переменной ширины.
	-->
	<div v-if="is_present" class="cabinet-menu-layer">
		<CabinetMenu :class="is_disabled ? 'disabled' : null" :disabled="is_disabled"/>
	</div>

</template>

<script>
	import CabinetMenu               from '@/_admin/js/layouts/CabinetMenu.vue';
	import { cabinetShellPresence }  from '@/_admin/js/layouts/cabinetShellPresence.js';

	export default {
		name: 'CabinetMenuLayer',
		components: { CabinetMenu },
		computed: {
			is_present() {
				return cabinetShellPresence.pages > 0;
			},
			is_disabled() {
				return cabinetShellPresence.menu_disabled;
			},
		},
	}
</script>

<style lang="scss" scoped>

	.cabinet-menu-layer {
		position: fixed;
		top: 0;
		left: 0;
		height: var(--viewport-height);

		// Выше контента страницы: собственный слой выезжающей панели действует
		// уже внутри этого контекста и наружу не пробивается.
		z-index: 2000;
	}

</style>
