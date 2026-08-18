<template>
	<CardTemplate
		title="User card"
		:form_data="form_data"
		:is_changed="is_changed"
		@save="saveRecordAndClose(form_data)"
		@cancel="$emit('close')"
	>
		<div ref="form" class="card-body min-h-[260px] h-full flex flex-col">
			<div class="flex flex-col space-y-3">
				<InlineInput ref="email" label="E-mail" v-model="form_data.email" :error="form_data_errors.email" input_class="disabled" label_class="w-[140px]"/>
				<InlineInput ref="name" label="First Name" v-model="form_data.name" :error="form_data_errors.name" label_class="w-[140px]"/>

				<div :class="{ disabled: !perms.roles }">
					<InlineInput type="select" ref="role_id" label="System role" v-model="form_data.role_id" :source="roles" :error="form_data_errors.role_id" label_class="w-[140px]"/>
				</div>

				<InlineInput type="password" ref="password" label="Password" v-model="form_data.password" :error="form_data_errors.password" label_class="w-[140px]" placeholder="********" :noautocomplete="true"/>
				<InlineInput type="password" ref="password_confirmation" label="Confirmation" v-model="form_data.password_confirmation" :error="form_data_errors.password_confirmation" label_class="w-[140px]" placeholder="********"/>
			</div>
		</div>
	</CardTemplate>
</template>

<script>
	import formMixins      from '@/js/_formMixins.js'
	import modalcardMixins from '@/js/_modalcardMixins.js'

	import CardTemplate    from '@/js/Elements/CardComponent.vue'

	export default {
		mixins: [formMixins, modalcardMixins],
		components: { CardTemplate },
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
				validationRules: {},
			}
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
