<template>

	<ModalForm ref="modal" :escToClose="true" :outsideClickClose="true" cardclasses="first-receipt-modal">

		<div class="card v-flex items-center text-center space-y-4">

			<div class="congrats-emoji" aria-hidden="true">🎉</div>

			<h2 class="text-xl font-semibold">{{ $t('Your first receipt has been issued') }}</h2>

			<p class="text-secondary">{{ $t('first-receipt-congrats-text') }}</p>

			<div class="flex flex-wrap justify-center wrap-gap-2">
				<a class="button primary-button" :href="report_url" @click="markSeen">{{ $t('Open sales report') }}</a>
				<button type="button" class="button outline-button" @click="close">{{ $t('Later') }}</button>
			</div>

		</div>

	</ModalForm>

</template>

<script>
	import ModalForm from '@/js/Elements/ModalForm.vue';

	// Первый чек — веха, ради которой человек и подключал кассу: письмо о ней уже
	// уходит, а в кабинете она проходила незамеченной. Показывается один раз:
	// отметка живёт в настройках пользователя, поэтому не повторяется и на другом
	// устройстве.
	export default {
		name: 'FirstReceiptCongrats',
		components: { ModalForm },

		computed: {
			report_url() {
				return route('cabinet.report.sales');
			},
		},

		methods: {
			close() {
				this.$refs.modal?.close();
				this.markSeen();
			},

			async markSeen() {
				if ( this.seen )
					return;

				this.seen = true;

				await this.$apiClient.post(route('cabinet.api.user.setsetting'), {
					key:   'first_receipt_seen',
					value: '1',
				});
			},
		},

		mounted() {
			// Отметка о показе не реактивна: она защищает от повторной отправки, а не
			// управляет разметкой.
			this.seen = false;

			this.$refs.modal?.open();
		},
	}
</script>

<style lang="scss" scoped>

	.congrats-emoji {
		font-size: 3rem;
		line-height: 1;
	}

	.card {
		max-width: 28rem;
		padding: 2rem;
	}

</style>
