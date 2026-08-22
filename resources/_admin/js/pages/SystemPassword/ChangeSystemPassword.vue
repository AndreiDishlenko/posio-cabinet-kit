<template lang="">

	<ModalCard class="w-lg max-w-[90vw]"
		title	= "Change the installation password"
		message	= "system-password-message"
		:buttons = "footerButtons"
		>

		<div ref="form" class="card-body v-flex !items-stretch">

			<!-- Account being secured -->
			<div class="label-group disabled">
				<label class="form-label">{{ $t('Your account') }}</label>
				<input type="text" class="form-control md:form-control-lg" :value="email" readonly/>
			</div>

			<!-- New password -->
			<div class="label-group">
				<label class="form-label">{{ $t('New Password') }}</label>
				<input ref="password" type="password" v-model="form_data.password" class="form-control md:form-control-lg" placeholder="********" maxlength="20"/>
				<p v-if="form_data_errors.password" class="form-error" >{{ form_data_errors.password }}</p>
			</div>

			<!-- Password confirmation -->
			<div class="label-group">
				<label class="form-label">{{ $t('Password Confirmation') }}</label>
				<input ref="password_confirmation" type="password" v-model="form_data.password_confirmation" class="form-control md:form-control-lg" placeholder="********" maxlength="20"/>
				<p v-if="form_data_errors.password_confirmation" class="form-error" >{{ form_data_errors.password_confirmation }}</p>
			</div>

		</div>

	</ModalCard>

</template>

<script>
	import { router }		from '@inertiajs/vue3';

	import _formMixins		from '@/js/_formMixins';

	import ModalCard		from '@/js/Elements/ModalCard.vue';

	export default {
		mixins: [_formMixins],
		components: { ModalCard },
		props: {
			email: {
				type: String,
				default: '',
			},
		},
		data() {
			return {
				validationRules: {
					'password'				: 'required|password',
					'password_confirmation'	: 'required|confirmed:password',
				},
			}
		},
		computed: {
			// Единственное действие: закрыть карточку или уйти со страницы нечем,
			// пока пароль не сменён.
			footerButtons() {
				return [
					{ label: 'Save password', class: 'primary-button', loading: this.$modal_inprogress.value, action: () => this.save() },
				];
			},
		},
		mounted() {
			this.form_data.password = "";
			this.form_data.password_confirmation = "";

			this.$refs.password.focus();
		},
		methods: {
			async save() {
				if ( !await this.validateForm() )
					return;

				router.post( route('cabinet-kit.system-password.update'), this.form_data, {
					onError: (errors) => {
						if (errors.error)
							this.$toast.error(errors.error);
						this.outputErrors(errors);
					},
					onSuccess: () => {
						// Дальше уводит бэкенд — на страницу входа в кабинет.
						this.$toast.success( this.$t('Password changed') );
					},
					preserveScroll: true,
					preserveState: true,
				});
			}
		}
	}
</script>

<style lang="scss" scoped>
</style>
