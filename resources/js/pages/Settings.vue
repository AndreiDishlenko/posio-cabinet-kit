<template>

	<CabinetLayout :space_y="2" :page_name="current_tab_title">

		<Tabs
			v-model="active_tab"
			:tabs="tabs"
			storage-key="tab"
			>
			<template v-for="tab in tabs" :key="tab.id" #[tab.id]>
				<div class="v-flex items-stretch space-y-6 pb-6">
					<component :is="tabComponent(tab)" v-bind="tabProps(tab)"/>
				</div>
			</template>
		</Tabs>

	</CabinetLayout>

</template>

<script>
	import CabinetLayout from '../layouts/CabinetLayout.vue';
	import Tabs from '@/js/Elements/Tabs.vue';

	import AccountTab from './Settings/AccountTab.vue';
	import UsersTab from './Settings/UsersTab.vue';
	import ProfileTab from './Settings/ProfileTab.vue';

	const TAB_COMPONENTS = { AccountTab, UsersTab, ProfileTab };

	export default {
		name: 'Settings',
		components: { CabinetLayout, Tabs },
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
			profile: {
				type: Object,
				default: () => ({}),
			},
			own_account: {
				type: Object,
				default: () => ({}),
			},
			account_users: {
				type: Array,
				default: () => [],
			},
			can_manage_members: {
				type: Boolean,
				default: false,
			},
			can_manage_account_users: {
				type: Boolean,
				default: false,
			},
			is_owner: {
				type: Boolean,
				default: false,
			},
			is_system_user: {
				type: Boolean,
				default: false,
			},
		},
		data() {
			return {
				active_tab: this.initialTab(),
			}
		},
		computed: {
			current_tab_title() {
				const tab = this.tabs.find(tab => tab.id === this.active_tab);
				return tab ? tab.label : 'Settings';
			},
		},
		watch: {
			tabs() {
				if (!this.tabs.some(tab => tab.id === this.active_tab))
					this.active_tab = this.initialTab();
			},
		},
		methods: {
			initialTab() {
				return this.tabs[0]?.id || '';
			},
			tabComponent(tab) {
				return TAB_COMPONENTS[tab.component] || null;
			},
			// Каждому табу — только его собственные данные; таб, добавленный хостом
			// через конфиг, получает прежний общий набор, чтобы не ломать его пропсы.
			tabProps(tab) {
				if (tab.component === 'ProfileTab')
					return {
						in_data: this.profile,
						disabled: this.is_system_user,
					};

				if (tab.component === 'AccountTab')
					return {
						in_data: this.own_account,
						can_edit: this.can_manage_members,
						is_owner: this.is_owner,
					};

				if (tab.component === 'UsersTab')
					return {
						in_data: this.own_account,
						users: this.account_users,
						roles: this.assignable_roles,
						can_edit: this.can_manage_account_users,
					};

				return {
					account: this.account,
					members: this.members,
					roles: this.assignable_roles,
					can_manage_account: this.can_manage_account,
				};
			},
		},
	}
</script>
