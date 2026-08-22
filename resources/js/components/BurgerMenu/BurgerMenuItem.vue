<template>

	<component
		:is="href ? ( external ? 'a' : 'Link' ) : 'button'"
		class="bdm-nav-item
			flex items-center gap-3.5  
			lt-md:text-lg
			transition-[background] 
			duration-150 hover:bg-[rgba(255,255,255,0.06)] 
			active:bg-[rgba(255,255,255,0.06)] 
			p-4 lt-sm:px-6 sm:mx-4"
		:class="{ 'opacity-40 pointer-events-none cursor-default hover:!bg-transparent active:!bg-transparent': disabled }"
		v-bind="element_attrs"
		@click="$emit('click', $event)"
	>
		<Icon v-if="icon" :icon="icon" class="icon icon-lg text-color"/>
		<span>{{ label }}</span>
	</component>

</template>

<script>
	import { Link } from '@inertiajs/vue3';
	import { Icon } from '@iconify/vue';

	export default {
		components: { Link, Icon },
		props: {
			icon: {
				type: String,
				default: null,
			},
			label: {
				type: String,
				required: true,
			},
			href: {
				type: String,
				default: null,
			},
			disabled: {
				type: Boolean,
				default: false,
			},
			// Переход в другое приложение (не в текущий Inertia-стек) — только полной загрузкой страницы.
			external: {
				type: Boolean,
				default: false,
			},
			// HTTP-метод перехода; всё, кроме get, требует отправки формы, а не обычной ссылки.
			method: {
				type: String,
				default: 'get',
			},
		},
		emits: ['click'],
		computed: {
			// Не-GET переход обязан рендериться кнопкой: браузер всё равно откроет <a> обычным GET-запросом.
			element_attrs() {
				if ( !this.href )
					return { type: 'button' };

				if ( this.external || this.method === 'get' )
					return { href: this.href };

				return { href: this.href, method: this.method, as: 'button' };
			},
		},
	}
</script>

<style lang="scss" scoped>
	// .bdm-nav-item {
	// 	font-size: var(--text-xl);
	// }

	// Иконка не красит свой фон отдельно — иначе при hover строки её фон
	// накладывается поверх уже закрашенного родителя и визуально отличается.
	.icon {
		background-color: transparent !important;
	}
</style>
