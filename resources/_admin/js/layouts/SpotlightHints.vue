<template>
	<Spotlight
		v-if="hint"
		:key="hint.key"
		:steps="[ hint ]"
		:skippable="false"
		@finished="dismiss"
	/>
</template>

<script>
	import Spotlight from './Spotlight.vue';

	import { SPOTLIGHT_HINTS } from '@/_admin/js/services/spotlightHints';

	// Показывает разовые подсветки из реестра: первую, которую пользователь ещё не
	// видел и цель которой сейчас на экране. Закрытая подсветка отмечается на
	// пользователе, поэтому не повторяется ни в другом браузере, ни на другом устройстве.
	export default {
		name: 'SpotlightHints',
		components: { Spotlight },

		data() {
			return {
				// Закрытые в этой сессии: отметка уходит на сервер, но убирать подсветку
				// с экрана и открывать дорогу следующей можно не дожидаясь ответа
				dismissed: [],
			}
		},

		computed: {
			hint() {
				const seen = [ ...(this.$page.props.user?.hints_seen ?? []), ...this.dismissed ];

				return SPOTLIGHT_HINTS.find(item => !seen.includes(item.key) && item.visible(this.$page)) ?? null;
			},
		},

		methods: {
			async dismiss() {
				const hint = this.hint;

				if ( !hint?.key )
					return;

				this.dismissed.push(hint.key);

				if ( hint.finish_event )
					this.$emitter.emit(hint.finish_event);

				await this.$apiClient.post(route('cabinet.api.user.hintseen'), { hint: hint.key });
			},
		},
	}
</script>
