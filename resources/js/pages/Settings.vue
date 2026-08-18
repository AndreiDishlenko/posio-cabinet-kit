<template>

	<CabinetLayout :page_name="activeTabLabel">

		<div class="ck-tabs flex flex-row gap-2 border-b mb-4">
			<button v-for="tab in tabs" :key="tab.id"
				type="button"
				class="ck-tab"
				:class="{ 'is-active': tab.id === activeTab }"
				@click="setActiveTab(tab.id)"
				>
				{{ $t ? $t(tab.label) : tab.label }}
			</button>
		</div>

		<component :is="activeComponent"
			:account="account"
			:members="members"
			:roles="assignable_roles"
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
			assignable_roles: {
				type: Array,
				default: () => [],
			},
		},
		data() {
			return {
				activeTab: this.initialTab(),
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
		watch: {
			tabs() {
				if (!this.tabs.some(tab => tab.id === this.activeTab))
					this.activeTab = this.initialTab();
			},
		},
		methods: {
			initialTab() {
				if (typeof window === 'undefined')
					return this.tabs[0]?.id;

				const requested = new URLSearchParams(window.location.search).get('tab');
				if (requested && this.tabs.some(tab => tab.id === requested))
					return requested;

				return this.tabs[0]?.id;
			},
			setActiveTab(tabId) {
				this.activeTab = tabId;

				if (typeof window === 'undefined')
					return;

				const url = new URL(window.location.href);
				url.searchParams.set('tab', tabId);
				window.history.replaceState({}, '', url);
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
