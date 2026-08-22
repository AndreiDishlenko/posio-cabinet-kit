<template lang="">

	<CabinetLayout :page_name="'Change password'" :disable_menu="true">

		<ModalForm ref="systemPassword" :escToClose="false" :outsideClickClose="false">
			<ChangeSystemPassword :email="email"/>
		</ModalForm>

	</CabinetLayout>

</template>

<script>
	import CabinetLayout			from '@/_admin/js/layouts/CabinetLayout.vue';

	import ModalForm				from '@/js/Elements/ModalForm.vue';

	import ChangeSystemPassword		from './SystemPassword/ChangeSystemPassword.vue';

	export default {
		components: { CabinetLayout, ModalForm, ChangeSystemPassword },
		props: {
			email: {
				type: String,
				default: '',
			},
		},
		mounted() {
			this.$nextTick(() => {
				this.$refs.systemPassword.open()
			});
		}
	}
</script>

<style lang="scss" scoped>
</style>
