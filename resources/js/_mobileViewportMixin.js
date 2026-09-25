// Признак телефонной раскладки для компонентов, у которых на узком экране
// меняется не оформление, а само представление (лист снизу вместо модалки).
// Порог совпадает с брейкпоинтом lt-md и с порогом выезжающего снизу листа.
const MOBILE_VIEWPORT_QUERY = '(max-width: 767px)';

export default {
	data() {
		return {
			is_mobile: false,
		}
	},
	mounted() {
		this._mobile_viewport_media = window.matchMedia(MOBILE_VIEWPORT_QUERY);
		this.is_mobile = this._mobile_viewport_media.matches;
		this._mobile_viewport_media.addEventListener('change', this.onMobileViewportChange);
	},
	beforeUnmount() {
		if ( this._mobile_viewport_media )
			this._mobile_viewport_media.removeEventListener('change', this.onMobileViewportChange);
	},
	methods: {
		onMobileViewportChange(e) {
			this.is_mobile = e.matches;
		},
	},
};
