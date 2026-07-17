<template>

	<div class="ck-header flex flex-row items-center justify-between border-b lt-sm:py-2 sm:py-2 gap-4">

		<span class="cursor-pointer lg:hidden" @click="$emitter?.emit('ck_burger_click')">
			<Icon icon="mdi:menu" class="ck-icon"/>
		</span>

		<span class="ck-page-title text-nowrap overflow-hidden">
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
		name: 'CabinetHeader',
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
	.ck-header {
		height: var(--ck-header-height, 60px);
		background-color: var(--ck-header-bg, #fff);
	}

	.ck-page-title {
		font-size: 1.1rem;
		font-weight: 600;
		color: var(--ck-text-color, #1c1c1c);
	}
</style>
