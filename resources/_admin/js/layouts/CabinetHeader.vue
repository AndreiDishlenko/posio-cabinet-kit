<template>

	<div class="cabinet-header flex flex-row items-center justify-between border-b
				lt-sm:space-x-6 sm:space-x-7 md:space-x-7 lg:space-x-8 xl:space-x-9 2xl:space-x-10 lt-sm:py-2 sm:py-2">

		<!-- На десктопе (≥1024px) переключатель меню живёт внутри самого SideMenu
		     (как в Gemini), поэтому бургер в шапке нужен только на мобильном для
		     выезжающей панели. -->
		<span class="cabinet-header-item burger-button cursor-pointer lg:hidden">
			<Icon icon="stash:burger-classic-duotone" class="icon icon-lg cursor-pointer text-secondary hover:text-zinc-200" @click="$emitter.emit('burger_button_click')"/>
		</span>

		<!-- Page Title (Breadcrumbs) -->
		<span class="page-title !ms-3  text-nowrap text-secondary max-w-[300px] overflow-hidden flex items-baseline gap-1.5">
			<span v-if="breadcrumbSection" class="text-sm opacity-50 lt-sm:hidden">{{ $t(breadcrumbSection) }}</span>
			<span v-if="breadcrumbSection" class="text-sm opacity-30 lt-sm:hidden">/</span>
			<span class="text-xl font-bold text-color">{{ $t(breadcrumbLeaf) }}</span>
		</span>

		<div class="grow !ms-0"></div>

		<!-- <div class="header-menu flex flex-row items-center lt-sm:space-x-5 md:space-x-10"> -->

		<span class="header-item hidden sm:flex">
			<LangSelectorPill/>
		</span>

		<!-- <span class="header-item hidden sm:flex"> -->
			<!-- <ThemeSelector /> -->
		<!-- </span> -->

		<span class="header-item h-2/3">
			<Notifications iconclass="icon-base"/>
		</span>

		<!-- Dev-only: запит з ролями/правами поточного користувача (системними та
		     акаунтними) — відповідь дивимось у DevTools (Network/Console), UI не потрібен. -->
		<span v-if="$page.props.dev_permissions_url" class="header-item hidden sm:flex">
			<Icon icon="mdi:bug-check-outline" class="icon cursor-pointer text-secondary hover:text-zinc-200"
				:title="$t('Debug: roles & permissions')"
				@click="fetchDebugPermissions"
				/>
		</span>

		<!-- </div> -->

		<span class="cabinet-header-item pe-2">
			<CabinetBurgerMenu class="w-full">
				<template #default="{ toggle }">
					<div class="burger-button flex items-center space-x-2 cursor-pointer" @click="toggle">
						<Avatar
							:src="$page.props.user.avatar"
							:user_name="$page.props.user.name || 'Guest'"
							size=""
							class="self-center !me-1"/>
						<Icon icon="icon-park-outline:application-menu" class="lt-sm:hidden" height="25px"/>
					</div>
				</template>
			</CabinetBurgerMenu>
		</span>

	</div>

</template>

<script>
	import { Icon }  from '@iconify/vue';
	import Avatar    from '@/js/Elements/Avatar.vue';

	import ThemeSelector from '@/js/Custom/_ThemeSelector.vue';
	import Notifications from '@/js/Custom/_Notifications.vue';
	import CabinetBurgerMenu    from '@/_admin/js/layouts/CabinetBurgerMenu.vue';
	import LangSelectorPill from '@/_admin/js/components/LangSelectorPill.vue';

	export default {
		components: { Icon, ThemeSelector, Avatar, LangSelectorPill, Notifications, CabinetBurgerMenu },
		props: {
			// Active sub-section (e.g. the active tab name). When provided, the
			// breadcrumb shows "Page / Subsection" on desktop and just the
			// subsection on mobile. Standard behaviour for any tabbed page.
			page_name: {
				type: String,
				default: ''
			}
		},
		computed: {
			currentSection() {
				const menu = this.$page.props.cabinetMenu;
				const currentId = this.$page.props.currentPage?.id;
				if (!menu || !currentId) return null;
				for (const group of menu) {
					if (group.children?.some(item => item.id === currentId))
						return group.label;
				}
				return null;
			},
			// When a sub-section (page_name) is set, the page name becomes the
			// muted context and the sub-section is the bold leaf.
			breadcrumbSection() {
				return this.page_name ? (this.$page.props.currentPage?.name || null) : this.currentSection;
			},
			breadcrumbLeaf() {
				return this.page_name || this.$page.props.currentPage?.name || '';
			},
		},
		methods: {
			// CabinetHeader.fetchDebugPermissions — dev-only, дивимось у DevTools
			async fetchDebugPermissions() {
				const { data } = await this.$apiClient.get(this.$page.props.dev_permissions_url);
				console.log('Debug: roles & permissions', data);
			},
		},
	}
</script>

<style lang="scss" scoped>

	.cabinet-header {
		height: var(--header-height);
		background-color: var(--header-bg);
		// border: 1px solid var(--border-color);
		// border: 1px solid red;
	}

	.cabinet-header-item {
		align-items: center;
	}

	// Кнопка бокового меню остаётся кликабельной поверх подложки открытой
	// панели пользователя (z-index 900) — иначе первый клик гасит подложку,
	// а не переключает меню. Ниже самой панели (z-index 1100), чтобы её не перекрыть.
	.cabinet-header-item.burger-button {
		position: relative;
		z-index: 910;
	}

	.header-item {
		align-items: center;
	}

	// .page-title {
	// 	max-width: 100px;
	// }

</style>
