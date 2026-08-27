<template lang="">

	<BurgerMenu ref="mobilePanel"
		:show_user_info="true"
		:show_profile_divider="!can_switch_account"
		@open="onPanelOpen"
		>

		<template #trigger="{ toggle }">
			<slot :toggle="toggle"/>
		</template>

		<template #default="{ closeSilently }">

			<!-- Account selector -->
			<div v-if="can_switch_account" class="px-5 v-center">
				<div class="account-selector w-full">
						<!-- class="w-full form-control-lg"
						input_class="!text-lg"
						items_class="!text-lg h-[52px]" -->

					<!-- <div class="text-center v-flex ms-3 mb-2">
						<span class="text-sm text-disabled">{{ $t('Current account') }}</span>
					</div> -->
					<Selectable
						class="w-full"
						size=""
						v-model="selected_account"
						:in_data="switchable_accounts"
						:isChild="true"
						:isFloating="false"
						bg_color="var(--account-selector-bg)"
						:placeholder="current_account_name"
						@onChange="(val, old_val) => selectAccount(val, old_val)"
					/>

				</div>
			</div>

			<!-- Действия текущей страницы — выше общих настроек кабинета -->
			<template v-if="burger_items.length">
				<BurgerMenuItem
					v-for		= "item in burger_items"
					:key		= "item.name"
					:icon		= "item.icon"
					:label		= "$t(item.name)"
					:href		= "item.href || null"
					:disabled	= "!!item.disabled"
					@click		= "runPageAction(item)"
					/>
				<BurgerMenuDivider/>
			</template>

			<BurgerMenuItem
				icon="proicons:settings"
				:label="$t('Settings')"
				:href="route('cabinet-kit.settings')"
				@click="closeSilently"
			/>

		</template>

	</BurgerMenu>

</template>

<script>
	import { router }       from '@inertiajs/vue3';

	import { Link }         from '@inertiajs/vue3';
	import { Icon }         from "@iconify/vue";

	import Selectable        from '@/js/Elements/Forms/Selectable.vue';
	import BurgerMenu   	from '@/js/Components/BurgerMenu/BurgerMenu.vue';
	import BurgerMenuItem    from '@/js/Components/BurgerMenu/BurgerMenuItem.vue';
	import BurgerMenuDivider from '@/js/Components/BurgerMenu/BurgerMenuDivider.vue';

	export default {
		components: { Link, Icon, Selectable, BurgerMenu, BurgerMenuItem, BurgerMenuDivider },
		props: {
			// Действия текущей страницы: { name, icon?, action | href, disabled?, in_burger? }.
			page_menu: {
				type: Array,
				default: () => [],
			},
		},
		inject: {
			pageMenuRegistry: { default: null },
		},
		data() {
			return {
				user: this.$page.props.user,
				// Действия вложенных блоков собираются в момент открытия панели.
				registered_items: [],
				// Выбор в списке — всегда переход на другой аккаунт, поэтому поле
				// ничего не «держит»: текущий показан подписью.
				selected_account: '',
			}
		},
		computed: {
			// Текущий аккаунт — подпись поля, а не пункт списка, поэтому переключаться
			// предлагается только на остальные доступные.
			switchable_accounts() {
				return this.accounts.filter(item => item.id != this.$page.props.account.id);
			},
			current_account_name() {
				const current = this.accounts.find(item => item.id == this.$page.props.account.id);

				return current ? current.name : this.$t('Select account');
			},
			accounts: function() {
				let result = [];

				this.$page.props.accounts.forEach(item => {
					result.push({
						id: item.id,
						name: item.name + (item.id==this.$page.props.user?.account ? ` (${this.$t('own')})` : '')
					})
				})

				return result;
			},
			// Действия страницы и её вложенных блоков; пункт можно скрыть флагом.
			burger_items() {
				return [...this.page_menu, ...this.registered_items].filter(item => item.in_burger !== false);
			},
			// Переключаться есть куда только при нескольких доступных аккаунтах;
			// тогда селектор встаёт под профилем вместо разделителя.
			can_switch_account() {
				return this.accounts.length > 1;
			},
		},
		mounted() {
			this.$emitter.on('close_burger_menu', this.closePanel);
		},
		beforeUnmount() {
			this.$emitter.off('close_burger_menu', this.closePanel);
		},
		methods: {
			// Открытие панели пользователя и левого меню взаимоисключающи.
			onPanelOpen() {
				this.registered_items = this.pageMenuRegistry ? this.pageMenuRegistry.collect() : [];
				this.$emitter.emit('burger_menu_opened');
			},
			closePanel() {
				if (this.$refs.mobilePanel)
					this.$refs.mobilePanel.close();
			},
			// Действие страницы обычно открывает свой оверлей — панель убираем раньше,
			// иначе карточка окажется под ней и под её записью в истории.
			// Переход по ссылке закрывает панель, не трогая историю.
			runPageAction(item) {
				if ( item.href )
					return this.$refs.mobilePanel?.closeSilently();

				this.closePanel();

				if ( typeof item.action === 'function' )
					this.$nextTick(() => item.action());
			},
			selectAccount(val, old_val) {
				// console.log('selectAccount', val);

				// Выбор уже активного аккаунта ничего не меняет — перезагружать кабинет незачем.
				if ( val == this.$page.props.account.id )
					return;

				let account_name = this.$page.props.accounts.filter(t=>{
					return t.id==val }
				)[0].name;

				router.post( route('cabinet-kit.account.set'), { account_id: val }, {
					onError: async (errors) => {
						// console.warn('errors', errors);
						// Переключения не произошло — поле возвращается к активному аккаунту.
						this.selected_account = '';
						this.$toast.error( 'Can\'t change account' );
						// this.outputErrors(errors);
					},
					onSuccess: (response) => {
						// console.log('response', response);
						this.$toast.success( this.$t(`Welcome to ${account_name} account`) )
						if (this.$refs.mobilePanel)
							this.$refs.mobilePanel.close();

						this.$accountService.setAccount(response.props.account)
					},
					preserveScroll: false,
					preserveState: false,
				});
			}
		}
	}
</script>

<style lang="scss" scoped>

	// Подсветка только этого поля: переменные переопределены на обёртке, ниже по
	// дереву их не видит ни одно другое поле кабинета.
	.account-selector {
		--form-control-background: var(--account-selector-bg);
		--form-control-border-color: var(--account-selector-bg);
		--form-control-border-color-focus: var(--account-selector-bg);
		// Подпись поля — имя активного аккаунта, а не подсказка: читается как значение.
		--placeholder-color: var(--text-color);
	}

</style>
