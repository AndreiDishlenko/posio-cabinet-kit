<template>

<!-- {{ items.length }} -->

    <!-- Бар виден только на телефоне в портретной ориентации: landscape и ширина >= md скрывают его -->
    <nav v-if="items.length" class="bottom-tab-bar-wrapper fixed inset-x-0 bottom-0 z-[1000] sm:hidden landscape:hidden flex justify-center px-2 pointer-events-none">
        
		<div class="bottom-tab-bar w-full flex items-center justify-between pointer-events-auto 
                    backdrop-blur-xl
                    rounded-full border
                    px-2 py-2 gap-1  
                    shadow-[0_8px_32px_rgba(0,0,0,0.5)]">

            <!-- <Link class="flex items-center rounded-full px-4 py-2 text-[12px] font-medium transition-all duration-200"
                v-for="item in items"
                :key="item.routeName"
                :href="route(item.routeName)"
                :prefetch="['mount', 'hover']"
                :class="isActive(item.routeName)
                    ? 'bg-blue-600 text-white'
                    : 'text-zinc-400 hover:text-zinc-200 hover:bg-white/5'"
            	> -->
			<Link class="grow button rounded-3xl"
                v-for="item in items"
                :key="item.routeName"
                :href="route(item.routeName)"
                :prefetch="['mount', 'hover']"
                :class="isActive(item.routeName)
                    ? 'lightblue-button'
                    : 'transparent-button text-secondary hover:text-zinc-200'"
            	>

                <Icon :icon="item.icon" class="icon shrink-0" />
				<!-- h-[24px] w-[18px] -->
                <span v-if="isActive(item.routeName)" class="ps-2 overflow-hidden transition-all duration-200 whitespace-nowrap leading-[3]">
                    {{ $t(item.label) }}
                </span>

            </Link>

        </div>
    </nav>

</template>

<script>
    import { Link } from '@inertiajs/vue3';
    import { Icon } from '@iconify/vue';

    import { resolveTabSet, MAX_TAB_BAR_ITEMS } from '@/_admin/js/components/ui/Elements/BottomTabBarSets.js';

    export default {
        name: 'BottomTabBar',
        components: { Link, Icon },
        computed: {
            // Пункты cabinetMenu (admin_menus, уже отфильтрованы по permissions роли) плоским списком
            flatMenuItems() {
                // console.log('BottomTabBar.flatMenuItems', this.$page.props.cabinetMenu);
                const menu = this.$page.props.cabinetMenu ?? [];
                return menu.flatMap(entry => entry.type === 'group' ? (entry.children ?? []) : [entry]);
            },
            // Набор кнопок по конфигурации аккаунта и роли пользователя (с fallback внутри resolveTabSet)
            items() {
                // console.log('BottomTabBar.items', this.$page.props.account?.configuration, this.$page.props.user?.role);
                const configCode = this.$page.props.account?.configuration;
                const role       = this.$page.props.user?.role;
                const routeNames = resolveTabSet(configCode, role);

				// console.log('resolveTabSet', configCode, routeNames)
				// console.log('2', this.flatMenuItems)
				// console.log('1', routeNames
                //     .map(routeName => {
                //         const menuItem = this.flatMenuItems.find(item => item.route === routeName);
                //         return menuItem
                //             ? { label: menuItem.label, routeName: menuItem.route, icon: menuItem.icon }
                //             : null;
                //     }))

                return routeNames
                    .map(routeName => {
                        const menuItem = this.flatMenuItems.find(item => item.route === routeName);
                        return menuItem
                            ? { label: menuItem.label, routeName: menuItem.route, icon: menuItem.icon }
                            : null;
                    })
                    .filter(Boolean)
                    .slice(0, MAX_TAB_BAR_ITEMS);
            },
        },
        methods: {
            isActive(routeName) {
                if (typeof route !== 'function') {
                    return false;
                }

                try {
                    return route().current(routeName);
                } catch (e) {
                    return false;
                }
            },
        },
    }
</script>

<style lang="scss" scoped>
	.bottom-tab-bar-wrapper {
		// height: calc( 3rem + 18px );
		// max-height: calc( 3rem + 18px );

		// Ряд кнопок поднимается над индикатором жеста — иначе нажатия по нижней
		// кромке перехватывает система.
		padding-bottom: calc( 0.5rem + env(safe-area-inset-bottom, 0px) );

		background-color: transparent;
		// background-color: var(--background-light-color);
		// border-color: var(--border-color);
	}
	.bottom-tab-bar {
		background-color: var(--header-bg);
	}

	.bottom-tab-link {
		min-height: var(--bottom-tab-bar-height);
	}
</style>