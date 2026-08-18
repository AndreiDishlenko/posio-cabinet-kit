import { pushOverlay, popOverlay } from '@/js/overlayHistory';

// Esc и аппаратная/жестовая кнопка «назад» (Android/iOS swipe-back) делают то
// же самое, что кнопка Cancel/Close карточки: эмитят 'cancel', дальнейшую
// логику (простое закрытие или запрос подтверждения при изменённых данных)
// ведёт родитель. Карточка живёт внутри VueFinalModal с display-directive
// "if" — создаётся при открытии и уничтожается при закрытии модалки, поэтому
// mounted/beforeUnmount корректно соответствуют открытию/закрытию.
export default {
	props: {
		close_on_esc: {
			type: Boolean,
			default: true
		},
	},
	methods: {
		attachCancelShortcuts() {
			if ( !this.close_on_esc )
				return;

			window.addEventListener('keydown', this.onCancelKeydown);
			pushOverlay(this, this.onCancelBackButton);
		},
		detachCancelShortcuts() {
			window.removeEventListener('keydown', this.onCancelKeydown);
			popOverlay(this);
		},
		onCancelKeydown(event) {
			if ( event.key !== 'Escape' )
				return;

			this.$emit('cancel');
		},
		// Запись истории, отвечающая за карточку, уже потрачена браузером —
		// сразу восстанавливаем охранную запись: если карточка всё же
		// закроется, её заберёт popOverlay() в detachCancelShortcuts; если
		// пользователь передумает (отменит диалог «Сохранить изменения?»),
		// карточка останется открытой уже с восстановленной записью, и
		// следующее «назад» снова сработает.
		onCancelBackButton() {
			pushOverlay(this, this.onCancelBackButton);

			// Диалог подтверждения (SweetAlert) не видит popstate — «назад»
			// во время него не должен открывать второй диалог поверх первого.
			if ( this.$popup.isOpen() )
				return;

			this.$emit('cancel');
		},
	},
	mounted() {
		this.attachCancelShortcuts();
	},
	beforeUnmount() {
		this.detachCancelShortcuts();
	},
};
