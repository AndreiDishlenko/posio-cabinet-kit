<template>
	<CardTemplate
		:class="{ 'user-card-extended': extra_tabs.length }"
		title="User card"
		:form_data="form_data"
		:is_changed="is_changed"
		@save="saveRecordAndClose(form_data)"
		@cancel="$emit('close')"
	>
		<div ref="form" class="card-body h-full flex flex-col" :class="extra_tabs.length ? 'min-h-[350px]' : 'min-h-[260px]'">
			<!-- Без разделов хоста карточка — одна форма, панель вкладок не нужна. -->
			<component :is="extra_tabs.length ? 'Tabs' : 'SingleTab'" v-model="active_tab" :tabs="tabs">
				<template #user>
					<div class="flex flex-col space-y-3">
						<InlineInput ref="email" label="E-mail" v-model="form_data.email" :error="form_data_errors.email" input_class="disabled" label_class="w-[140px]"/>
						<InlineInput ref="name" label="First Name" v-model="form_data.name" :error="form_data_errors.name" label_class="w-[140px]"/>

						<div :class="{ disabled: !perms.roles }">
							<InlineInput type="select" ref="role_id" label="System role" v-model="form_data.role_id" :source="roles" :error="form_data_errors.role_id" label_class="w-[140px]"/>
						</div>

						<InlineInput type="password" ref="password" label="Password" v-model="form_data.password" v-model:visible="password_visible" :error="form_data_errors.password" label_class="w-[140px]" placeholder="********" :noautocomplete="true"/>
						<InlineInput type="password" ref="password_confirmation" label="Confirmation" v-model="form_data.password_confirmation" :reveal="false" :visible="password_visible" :error="form_data_errors.password_confirmation" label_class="w-[140px]" placeholder="********"/>
					</div>
				</template>

				<template v-for="tab in extra_tabs" :key="tab.id" #[tab.id]>
					<component :is="tab.component" :user="form_data" :perms="perms" :active="active_tab === tab.id" :shared="tabs_shared" />
				</template>
			</component>
		</div>
	</CardTemplate>
</template>

<script>
	import formMixins      from '@/js/_formMixins.js'
	import modalcardMixins from '@/js/_modalcardMixins.js'

	import CardTemplate    from '@/js/Elements/CardComponent.vue'
	import Tabs            from '@/js/Elements/Tabs.vue'

	import { userCardTabs } from '@/_admin/js/userCardTabs.js'

	// Единственная вкладка без панели: выводит только форму пользователя.
	const SingleTab = {
		name: 'SingleTab',
		inheritAttrs: false,
		render() {
			return this.$slots.user?.();
		},
	};

	export default {
		mixins: [formMixins, modalcardMixins],
		components: { CardTemplate, Tabs, SingleTab },
		props: {
			roles: {
				type: Array,
				default: []
			},
			perms: {
				type: Object,
				default: () => ({ users: false, roles: false, accounts: false })
			},
		},
		data() {
			return {
				password_visible: false,
				validationRules: {},
				active_tab: 'user',
				// Общее хранилище разделов хоста; у каждого пользователя своё.
				tabs_shared: {},
			}
		},
		computed: {
			extra_tabs() {
				return userCardTabs().filter(tab => !tab.permission || this.perms[tab.permission]);
			},
			tabs() {
				return [
					{ id: 'user', label: 'User' },
					...this.extra_tabs.map(tab => ({ id: tab.id, label: tab.label })),
				];
			},
		},
		watch: {
			'form_data.id'() {
				this.tabs_shared = {};
			},
		},
		mounted() {
			this.$nextTick(() => {
				this.$refs.name?.focus();
			});
		},
		methods: {
			onSaveSuccess() {
				delete this.form_data.password
				delete this.form_data.password_confirmation
			}
		}
	}
</script>

<style lang="scss" scoped>

	// Разделы хоста несут списки (лицензии, подключения): карточка шире и постоянной
	// высоты, чтобы длинный список прокручивался внутри раздела, а шапка, вкладки и
	// форма под списком оставались на месте.
	.card.user-card-extended {
		height: 44rem;
		max-height: 95vh;
		max-height: 95dvh;
	}

	@media (min-width: 1024px) {
		.card.user-card-extended {
			width: 56rem;
			min-width: 56rem;
		}
	}

	@media (max-width: 767px) {
		.card.user-card-extended {
			height: 97.5vh;
			height: 97.5dvh;
			max-height: none;
		}
	}

	.form-label {
		width: 140px;
	}

	.thumb {
		width: 200px;
	}

	.license-row {
		display: grid;
		grid-template-columns: 1.3fr 0.9fr 1.1fr 0.7fr 1fr 0.9fr auto;
		gap: 0.6rem;
		align-items: center;
		padding: 0.4rem 0;
		border-top: 1px solid var(--card-border-color);
		font-size: var(--text-sm);
	}

	// Заголовок колонок списка лицензий (только десктоп)
	.license-head {
		border-top: none;
		color: var(--text-color-secondary);
		font-size: var(--text-xs);
		font-weight: 500;
		padding-bottom: 0.15rem;
	}

	// Подпись ячейки для мобильной раскладки (label : value); на десктопе скрыта
	.license-row > span::before {
		content: attr(data-label);
		display: none;
	}

	.guest-row {
		display: flex;
		align-items: center;
		@include flex-gap(0.6rem);
		padding: 0.4rem 0;
		border-top: 1px solid var(--card-border-color);
		font-size: var(--text-sm);
	}

	// Мобильная адаптация: строка лицензии → вертикальный стек «подпись : значение»
	@media (max-width: 639px) {
		.license-row {
			position: relative;
			grid-template-columns: 1fr;
			gap: 0.15rem;
			padding: 0.6rem 2rem 0.6rem 0;
		}

		.license-row > span {
			display: flex;
			justify-content: space-between;
			@include flex-gap(0.75rem);
			text-align: left;
		}

		.license-row > span::before {
			display: block;
			color: var(--text-color-secondary);
		}

		.license-remove {
			position: absolute;
			top: 0.5rem;
			right: 0;
		}
	}
</style>
