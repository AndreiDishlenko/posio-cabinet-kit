<template>

	<div class="ck-account-switcher relative" v-click-outside="close">

		<button type="button" class="button ck-account-trigger" @click="open = !open">
			{{ $page.props.account?.name || 'Account' }}
			<Icon icon="mdi:chevron-down" class="ck-icon-sm"/>
		</button>

		<div v-if="open" class="ck-account-menu absolute end-0 mt-1 z-50">

			<div class="ck-account-menu-label">Switch account</div>

			<button v-for="account in accounts" :key="account.id"
				type="button"
				class="ck-account-menu-item"
				:class="{ 'is-active': account.id === $page.props.account?.id }"
				@click="selectAccount(account.id)"
				>
				{{ account.name }}
			</button>

			<div class="ck-account-menu-divider"/>

			<Link class="ck-account-menu-item" :href="route('cabinet-kit.settings')" @click="close">
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
					el.__ckClickOutside = (event) => {
						if (!el.contains(event.target)) binding.value();
					};
					document.addEventListener('click', el.__ckClickOutside);
				},
				unmounted(el) {
					document.removeEventListener('click', el.__ckClickOutside);
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

				router.post(route('cabinet-kit.account.set'), { account_id: accountId }, {
					onSuccess: () => this.close(),
					preserveScroll: true,
				});
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-account-trigger {
		display: inline-flex;
		align-items: center;
		gap: .35rem;
	}

	.ck-account-menu {
		min-width: 200px;
		background: var(--ck-card-bg, #fff);
		border: 1px solid var(--ck-border-color, #e5e7eb);
		border-radius: .5rem;
		box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
		padding: .35rem;
	}

	.ck-account-menu-label {
		font-size: .7rem;
		text-transform: uppercase;
		opacity: .5;
		padding: .35rem .5rem;
	}

	.ck-account-menu-item {
		display: block;
		width: 100%;
		text-align: start;
		padding: .4rem .5rem;
		border-radius: .35rem;
		font-size: .9rem;

		&:hover {
			background: var(--ck-item-hover-bg, #f0f1f3);
		}

		&.is-active {
			font-weight: 600;
		}
	}

	.ck-account-menu-divider {
		height: 1px;
		background: var(--ck-border-color, #e5e7eb);
		margin: .35rem 0;
	}
</style>
