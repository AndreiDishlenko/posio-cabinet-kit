<template>

	<div class="cabinet-header flex flex-row items-center justify-between border-b
				lt-sm:space-x-6 sm:space-x-7 md:space-x-7 lg:space-x-8 xl:space-x-9 2xl:space-x-10 lt-sm:py-2 sm:py-2">

		<!-- На десктопе (≥1024px) переключатель меню живёт внутри самого SideMenu
		     (как в Gemini), поэтому бургер в шапке нужен только на мобильном для
		     выезжающей панели. -->
		<button type="button" class="cabinet-header-item burger-button shell-icon-button cursor-pointer lg:hidden"
			:aria-label="$t('Main menu')"
			@click="$emitter.emit('burger_button_click')"
			>
			<Icon icon="stash:burger-classic-duotone" class="icon icon-lg cursor-pointer text-secondary hover:text-zinc-200"/>
		</button>

		<!-- Page Title -->
		<!-- Ширину не ограничиваем константой: заголовок занимает столько, сколько
		     нужно, и ужимается многоточием только когда не хватает места в строке.
		     Заголовок страницы — единственный h1 документа: без него у страницы нет
		     ни одного заголовка первого уровня. -->
		<h1 class="page-title !ms-3  text-nowrap text-secondary min-w-0 overflow-hidden flex items-baseline">
			<!-- Шапка называет всю страницу: название активного таба видно в самих табах
			     и в заголовке вкладки браузера. -->
			<span class="page-title-leaf text-xl font-bold text-color overflow-hidden text-ellipsis">{{ $t(pageTitle) }}</span>
		</h1>

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
			<button type="button" class="shell-icon-button cursor-pointer"
				:aria-label="$t('Debug: roles & permissions')"
				:title="$t('Debug: roles & permissions')"
				@click="fetchDebugPermissions"
				>
				<Icon icon="mdi:bug-check-outline" class="icon cursor-pointer text-secondary hover:text-zinc-200"/>
			</button>
		</span>

		<!-- </div> -->

		<span class="cabinet-header-item pe-2">
			<CabinetBurgerMenu class="w-full" :page_menu="page_menu">
				<template #default="{ toggle }">
					<button type="button" class="burger-button shell-icon-button flex items-center space-x-2 cursor-pointer"
						:aria-label="$t('User menu')"
						@click="toggle"
						>
						<Avatar
							:src="$page.props.user.avatar"
							:user_name="$page.props.user.name || 'Guest'"
							size=""
							class="self-center !me-1"/>
						<Icon icon="icon-park-outline:application-menu" class="lt-sm:hidden" height="25px"/>
					</button>
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
			// Active sub-section (e.g. the active tab name) — last-resort fallback
			// for pages without a menu entry and an explicit header title.
			page_name: {
				type: String,
				default: ''
			},
			header_title: {
				type: String,
				default: ''
			},
			// Действия текущей страницы для панели пользователя — на мобильном она
			// единственное место, где они доступны.
			page_menu: {
				type: Array,
				default: () => []
			}
		},
		computed: {
			pageTitle() {
				return this.header_title || this.$page.props.currentPage?.name || this.page_name || '';
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

	// Кнопка-значок оболочки: собственного оформления у неё нет, но она обязана
	// быть настоящей кнопкой — иначе действие недоступно с клавиатуры и не имеет
	// имени для чтения с экрана.
	.shell-icon-button {
		display: flex;
		align-items: center;
		justify-content: center;
		padding: 0;
		border: 0;
		background: none;
		color: inherit;
	}

	// Кольцо клавиатурного фокуса — только для клавиатуры: от клика мышью оно
	// не появляется, а на планке старых браузеров правило отбрасывается целиком.
	.shell-icon-button:focus-visible {
		outline: 2px solid var(--focus-ring-color);
		outline-offset: 2px;
		border-radius: 4px;
	}

	.webchat-item .icon {
		color: var(--header-icon-accent-color);
	}

	// На телефоне от заголовка остаётся только название раздела — чуть крупнее
	// базового; следующий шаг шкалы шрифтов для этого слишком велик.
	@media (max-width: 767px) {
		.page-title-leaf {
			font-size: calc(var(--text-xl) + 2px);
		}
	}

	// .page-title {
	// 	max-width: 100px;
	// }

</style>
