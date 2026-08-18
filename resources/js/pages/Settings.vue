<template>

	<CabinetLayout :page_name="activeTabLabel">

		<div class="flex flex-row gap-2 border-b mb-4">
			<button v-for="tab in tabs" :key="tab.id"
				type="button"
				class="button ghost-button button-sm"
				:class="{ 'primary-button': tab.id === activeTab }"
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

    // Highlighted account name shown at the top of account-scoped tabs.
    .account-scope-name {
        @apply text-lg font-semibold self-start;
        padding: 0.25rem 0.75rem;
        border-left: 3px solid var(--primary-button-bg);
        color: var(--text-color);
    }

    .user-avatar {
        position: absolute;
        top: 36px;
        left: 30px;

        font-size: var(--text-xl);
        @apply
            absolute 
    }

    .avatar-image {
        border: 4px solid var(--background-color);
    }

    .photo-button {
        position: absolute;
        top: 15px;
        right: 5px;
        color: var(--text-color-secondary);
        cursor: pointer;
    }

    // Desktop: floats in the bottom-right corner of the banner card.
    .profile-since {
        position: absolute;
        bottom: 0.5rem;
        right: 1rem;
    }

    // Mobile: drop into normal flow under the overhanging avatar so the date
    // never overlaps it and the banner fully contains the avatar (no bleed
    // into the profile form below).
    @media (max-width: 639px) {
        .profile-since {
            position: static;
            display: block;
            margin-top: 3.25rem;
            padding-right: 0.25rem;
            text-align: right;
        }
    }
</style>
