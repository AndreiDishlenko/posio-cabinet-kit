<template>

	<div class="burger-body flex flex-col space-y-2">

		<!-- Панель перекрывает кнопку вызова, поэтому закрывать её можно только изнутри -->
		<div class="burger-body-head flex items-start justify-between">

			<div v-if="show_user_info" class="burger-profile flex items-center gap-4 py-[1.125rem] !px-7 min-w-0">
				<Avatar
					:src="$page.props.user.avatar"
					:user_name="$page.props.user.name || 'Guest'"
					:size="'lg'"/>
				<div class="burger-profile__info flex flex-col min-w-0">
					<span class="burger-profile__name text-base font-bold truncate">{{ $page.props.user.name || $t('Guest') }}</span>
					<span class="burger-profile__email text-[0.8rem] opacity-[0.55] truncate">{{ $page.props.user.email || 'Please sign in' }}</span>
				</div>
			</div>
			<span v-else></span>

			<button type="button" class="burger-close" :title="$t('Close')" @click="$emit('close')">
				<Icon icon="material-symbols:close" class="icon icon-lg"/>
			</button>

		</div>

		<BurgerMenuDivider v-if="show_user_info && show_profile_divider"/>

		<slot/>

		<!-- Login / Logout -->
		<template v-if="show_user_info">
			<BurgerMenuItem
				v-if="$page.props.user.name"
				icon="solar:logout-outline"
				:label="$t('Logout')"
				:href="route('logout')"
				method="post"
				@click="$emit('close-silently')"
			/>
			<BurgerMenuItem
				v-else
				icon="solar:login-outline"
				:label="$t('Login')"
				:href="route('login')"
				@click="$emit('close-silently')"
			/>
		</template>

		<BurgerMenuDivider/>

		<!-- App version -->
		<div v-if="$page.props.version" class="px-5 pt-1 text-center text-xs opacity-50">
			Posio {{ $page.props.version }}
		</div>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	import Avatar            from '@/js/Elements/Avatar.vue';
	import BurgerMenuItem    from '@/js/Components/BurgerMenu/BurgerMenuItem.vue';
	import BurgerMenuDivider from '@/js/Components/BurgerMenu/BurgerMenuDivider.vue';

	export default {
		name: 'BurgerMenuBody',
		components: { Icon, Avatar, BurgerMenuItem, BurgerMenuDivider },
		props: {
			show_user_info: {
				type: Boolean,
				default: false,
			},
			show_profile_divider: {
				type: Boolean,
				default: true,
			},
		},
		emits: ['close', 'close-silently'],
	}
</script>

<style lang="scss" scoped>

	.burger-close {
		display: grid;
		place-items: center;
		flex: none;
		margin: 0.5rem 0.75rem 0 0;
		padding: 0.25rem;
		border: none;
		background: none;
		cursor: pointer;
		border-radius: var(--ui-radius-xs);
		color: var(--text-color-secondary);

		&:hover {
			color: var(--text-color);
		}
	}

</style>
