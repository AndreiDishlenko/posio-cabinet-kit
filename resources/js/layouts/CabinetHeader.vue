<template>

	<div class="ck-header flex flex-row items-center justify-between border-b lt-sm:py-2 sm:py-2 gap-4">

		<span class="ck-mobile-menu-button cursor-pointer lg:hidden" @click="$emitter?.emit('ck_burger_click')">
			<Icon icon="mdi:menu" class="ck-icon"/>
		</span>

		<span class="ck-page-title text-nowrap overflow-hidden flex items-baseline gap-1.5">
			<span v-if="breadcrumbSection" class="ck-page-title-section lt-sm:hidden">{{ translate(breadcrumbSection) }}</span>
			<span v-if="breadcrumbSection" class="ck-page-title-separator lt-sm:hidden">/</span>
			<span class="ck-page-title-leaf">{{ translate(breadcrumbLeaf) }}</span>
		</span>

		<div class="grow"></div>

		<slot name="header-actions"/>

		<AccountSwitcher/>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	import AccountSwitcher from './AccountSwitcher.vue';

	export default {
		name: 'CabinetHeader',
		components: { Icon, AccountSwitcher },
		props: {
			page_name: {
				type: String,
				default: '',
			},
		},
		computed: {
			breadcrumbSection() {
				return this.page_name
					? (this.$page.props.currentPage?.name || null)
					: (this.$page.props.currentPage?.section || null);
			},
			breadcrumbLeaf() {
				return this.page_name || this.$page.props.currentPage?.name || 'Cabinet';
			},
		},
		methods: {
			translate(value) {
				return this.$t ? this.$t(value) : value;
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-header {
		height: var(--ck-header-height, 60px);
		background-color: var(--ck-header-bg, #fff);
	}

	.ck-page-title {
		color: var(--ck-text-color, #1c1c1c);
	}

	.ck-page-title-section,
	.ck-page-title-separator {
		font-size: .85rem;
		opacity: .5;
	}

	.ck-page-title-separator {
		opacity: .3;
	}

	.ck-page-title-leaf {
		font-size: 1.1rem;
		font-weight: 600;
	}

	.ck-mobile-menu-button {
		position: relative;
		z-index: 910;
	}
</style>
