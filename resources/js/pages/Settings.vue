<template>

	<CabinetLayout :page_name="activeTabLabel">

		<div class="ck-tabs flex flex-row gap-2 border-b mb-4">
			<button v-for="tab in tabs" :key="tab.id"
				type="button"
				class="ck-tab"
				:class="{ 'is-active': tab.id === activeTab }"
				@click="activeTab = tab.id"
				>
				{{ $t ? $t(tab.label) : tab.label }}
			</button>
		</div>

		<component :is="activeComponent"
			:account="account"
			:members="members"
			:can_manage_account="can_manage_account"
			/>

	</CabinetLayout>

</template>

<script>
	import CabinetLayout from '../layouts/CabinetLayout.vue';

	import AccountTab from './Settings/AccountTab.vue';
	import UsersTab from './Settings/UsersTab.vue';
	import ProfileTab from './Settings/ProfileTab.vue';

	const TAB_COMPONENTS = { AccountTab, UsersTab, ProfileTab };

	export default {
		name: 'Settings',
		components: { CabinetLayout },
		props: {
			tabs: {
				type: Array,
				default: () => [],
			},
			account: {
				type: Object,
				default: null,
			},
			members: {
				type: Array,
				default: () => [],
			},
			can_manage_account: {
				type: Boolean,
				default: false,
			},
		},
		data() {
			return {
				activeTab: this.tabs[0]?.id,
			}
		},
		computed: {
			activeTabLabel() {
				return this.tabs.find(tab => tab.id === this.activeTab)?.label || 'Settings';
			},
			activeComponent() {
				const tab = this.tabs.find(tab => tab.id === this.activeTab);
				return tab ? TAB_COMPONENTS[tab.component] : null;
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-tab {
		padding: .5rem .75rem;
		font-size: .9rem;
		opacity: .6;
		border-bottom: 2px solid transparent;

		&.is-active {
			opacity: 1;
			border-color: var(--ck-brand-bg, #3961E9);
			font-weight: 600;
		}
	}
</style>
