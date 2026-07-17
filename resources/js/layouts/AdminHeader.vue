<template>

	<div class="ak-header flex flex-row items-center justify-between border-b lt-sm:py-2 sm:py-2 gap-4">

		<span class="cursor-pointer lg:hidden" @click="$emitter?.emit('ak_burger_click')">
			<Icon icon="mdi:menu" class="ak-icon"/>
		</span>

		<span class="ak-page-title text-nowrap overflow-hidden">
			{{ $t ? $t(page_name || '') : page_name }}
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
		name: 'AdminHeader',
		components: { Icon, AccountSwitcher },
		props: {
			page_name: {
				type: String,
				default: '',
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ak-header {
		height: var(--ak-header-height, 60px);
		background-color: var(--ak-header-bg, #fff);
	}

	.ak-page-title {
		font-size: 1.1rem;
		font-weight: 600;
		color: var(--ak-text-color, #1c1c1c);
	}
</style>
