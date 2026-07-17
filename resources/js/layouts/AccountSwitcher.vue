<template>

	<div class="ak-account-switcher relative" v-click-outside="close">

		<button type="button" class="button ak-account-trigger" @click="open = !open">
			{{ $page.props.account?.name || 'Account' }}
			<Icon icon="mdi:chevron-down" class="ak-icon-sm"/>
		</button>

		<div v-if="open" class="ak-account-menu absolute end-0 mt-1 z-50">

			<div class="ak-account-menu-label">Switch account</div>

			<button v-for="account in accounts" :key="account.id"
				type="button"
				class="ak-account-menu-item"
				:class="{ 'is-active': account.id === $page.props.account?.id }"
				@click="selectAccount(account.id)"
				>
				{{ account.name }}
			</button>

			<div class="ak-account-menu-divider"/>

			<Link class="ak-account-menu-item" :href="route('admin-kit.settings')" @click="close">
				Settings
			</Link>

		</div>

	</div>

</template>

<script>
	import { Link, router } from '@inertiajs/vue3';
	import { Icon } from '@iconify/vue';

	export default {
		name: 'AccountSwitcher',
		components: { Link, Icon },
		directives: {
			// Minimal local click-outside — avoids depending on a host-provided directive.
			clickOutside: {
				mounted(el, binding) {
					el.__akClickOutside = (event) => {
						if (!el.contains(event.target)) binding.value();
					};
					document.addEventListener('click', el.__akClickOutside);
				},
				unmounted(el) {
					document.removeEventListener('click', el.__akClickOutside);
				},
			},
		},
		data() {
			return {
				open: false,
			}
		},
		computed: {
			accounts() {
				return this.$page.props.accounts || [];
			},
		},
		methods: {
			close() {
				this.open = false;
			},
			selectAccount(accountId) {
				if (accountId === this.$page.props.account?.id) {
					this.close();
					return;
				}

				router.post(route('admin-kit.account.set'), { account_id: accountId }, {
					onSuccess: () => this.close(),
					preserveScroll: true,
				});
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ak-account-trigger {
		display: inline-flex;
		align-items: center;
		gap: .35rem;
	}

	.ak-account-menu {
		min-width: 200px;
		background: var(--ak-card-bg, #fff);
		border: 1px solid var(--ak-border-color, #e5e7eb);
		border-radius: .5rem;
		box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
		padding: .35rem;
	}

	.ak-account-menu-label {
		font-size: .7rem;
		text-transform: uppercase;
		opacity: .5;
		padding: .35rem .5rem;
	}

	.ak-account-menu-item {
		display: block;
		width: 100%;
		text-align: start;
		padding: .4rem .5rem;
		border-radius: .35rem;
		font-size: .9rem;

		&:hover {
			background: var(--ak-item-hover-bg, #f0f1f3);
		}

		&.is-active {
			font-weight: 600;
		}
	}

	.ak-account-menu-divider {
		height: 1px;
		background: var(--ak-border-color, #e5e7eb);
		margin: .35rem 0;
	}
</style>
