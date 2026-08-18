<template lang="">

	<!--
		Localization will detect in 3 steps:
			1. If it first visit        - it will get default browser locale
			2. For second etc. visits   - it will get localStorage 'locale' setting
			3. If user is authenticated - it will use serverLocale with server.session.locale and update it when detect changes
	-->

	<!-- Compact or Text mode: uses dropdown -->
	<Dropdown v-if="mode !== 'inline'" class=""
		:dropareaclass="'p-1 border border-white/10'"
		ref="dropdown"
		:title="$t('Language selection')"
		buttonclass="py-3 h-full"
		:downOnClick="true"
		@changeState="onDropdownStateChange"
		>

		<template #button>
			<div class="lang-btn-inner text-md">
				<!-- <Icon v-if="mode === 'compact'"
					:icon="locales[locale] ? locales[locale].icon : ''"
					class="icon"
					:class="iconclass"
					/> -->
				<Icon icon="material-symbols:language" class="icon icon-md" :class="iconclass"/>
				<span class="lang-label ">
					{{ shortNames[locale] || locale.toUpperCase() }}
				</span>
				<!-- <Icon icon="mdi:chevron-down" class="icon icon-sm chevron" :class="{ 'is-open': dropdownOpen }"/> -->
			</div>
		</template>

		<template #dropdownitems>
			<div class="m-2 text-md">
				<span v-for="item in locales" :key="item.code" class="menu-item icon-label" @click="changeLocale(item.code)">
					<Icon :icon="item.icon" class="icon icon-lg"/>
					<span class="grow menu-item-text">{{ $t(item.name) }}</span>
				</span>
			</div>
		</template>

	</Dropdown>

	<!-- Inline mode: locale codes in a row, no dropdown -->
	<div v-else class="inline-selector">
		<span
			v-for="item in locales"
			:key="item.code"
			class="inline-lang-item"
			:class="{ 'is-active': locale === item.code }"
			@click="changeLocale(item.code)">
			{{ shortNames[item.code] || item.code.toUpperCase() }}
		</span>
	</div>

</template>


<script>
	import { Icon }     from "@iconify/vue";
	import { router }   from "@inertiajs/vue3"

	import Dropdown from "@/js/Elements/Dropdown.vue";
	import DropdownItem from "@/js/Elements/DropdownItem.vue";

	import { applyLocale, browserLocale, isSupportedLocale, rememberLocale, storedLocale } from "@/js/localeSync.js";

	export default {
		components: {Icon, Dropdown, DropdownItem},
		props: {
			iconclass: {
				type: String,
				default: 'icon-lg'
			},
			mode: {
				type: String,
				default: 'compact',
				validator: (value) => ['compact', 'text', 'inline'].includes(value),
			},
		},
		data: function() {
			return {
				locales: this.$i18n.locales,
				locale: this.$i18n.locale,
				dropdownOpen: false,
				shortNames: {
					uk: 'UA',
					en: 'ENG',
					ru: 'RU',
				},
			}
		},
		async mounted() {
			this.init();
		},
		methods: {
			init: function() {
				// console.log('LangSelector.init', this.$page.props.serverlocale);
				let locale = this.$page.props.serverlocale;

				if ( this.checkLocale(locale) )
					return this.setLocale(locale);

				locale = storedLocale() || this.getBrowserLocale();

				return this.setLocale(locale);
			},
			// Проверяем по полному списку переведённых языков, а не по списку выбора:
			// на маркетинговом сайте есть язык, которого нет в переключателе.
			checkLocale: function(locale) {
				return isSupportedLocale(locale);
			},
			getBrowserLocale: function() {
				return browserLocale();
			},
			// User-triggered locale change: update state + close dropdown + navigate + sync server
			changeLocale: function(selected_locale) {
				if (!this.setLocale(selected_locale)) return;
				if (this.$refs.dropdown) this.$refs.dropdown.close();
				this.updateServer();
				this.navigateToLocalized(selected_locale);
			},
			// Silent locale change used during init — no dropdown interaction, no navigation
			setLocale: function(selected_locale) {
				if (!applyLocale(selected_locale))
					return false;

				this.locale = selected_locale;

				return true;
			},
			navigateToLocalized(newLocale) {
				// Public routes are named `${base}.${locale}` (e.g. usecases.coffeeshop.uk).
				// Strip the locale suffix and resolve the localized URL for newLocale.
				// Non-localized routes (cabinet.*, login, etc.) have no suffix — skip.
				try {
					const currentName = this.route().current();
					if (!currentName) return;

					const m = currentName.match(/^(.+)\.(uk|en|ru)$/);
					if (!m) return;

					const baseName = m[1];
					const newUrl = this.$locRoute(baseName, newLocale);
					if (newUrl) router.visit(newUrl);
				} catch { /* noop */ }
			},
			// Закрепляем выбор и для гостя: без этого на страницах без языкового префикса
			// (вход, регистрация, кабинет) сервер не узнаёт о выборе, и при следующей
			// загрузке страница возвращается на прежний язык.
			updateServer() {
				rememberLocale(this.locale);
			},
			onDropdownStateChange(isOpen) {
				this.dropdownOpen = isOpen;
			},
		},
	}
</script>

<style lang="scss" scoped>
	.lang-btn-inner {
		@apply flex items-center;
		@include flex-gap(0.375rem);
	}

	// .lang-label {
	// 	@apply 
	// 		text-sm 
	// 		font-medium 
	// 		leading-none;
	// }

	.chevron {
		transition: transform 0.2s ease;

		&.is-open {
			transform: rotate(180deg);
		}
	}

	.menu-item {
		@apply
			cursor-pointer
			text-nowrap
			rounded-md
			!px-2
			!py-2.5;
	}

	.menu-item:not(.disabled):hover {
		background-color: var(--dropdown-items-color)!important;
	}

	.menu-item-text {
		color: var(--text-secondary);
		letter-spacing: 0.03em;
	}

	.inline-selector {
		@apply flex items-center;
		@include flex-gap(0.125rem);
	}

	.inline-lang-item {
		@apply
			cursor-pointer
			text-sm
			font-medium
			px-1.5
			py-0.5
			rounded
			opacity-50
			transition-opacity
			duration-150;

		&.is-active {
			@apply opacity-100;
		}

		&:not(.is-active):hover {
			@apply opacity-75;
		}
	}
</style>
