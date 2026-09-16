<template>

	<div class="password-input relative w-full">

		<input
			ref="input"
			v-bind="$attrs"
			:type="is_visible ? 'text' : 'password'"
			:value="modelValue == null ? '' : modelValue"
			class="password-input-field"
			:class="{ 'has-reveal': reveal }"
			@input="$emit('update:modelValue', $event.target.value)"
			/>

		<!-- Нажатие не забирает фокус у поля: на телефоне клавиатура не прячется. -->
		<span v-if="reveal"
			class="password-reveal absolute right-3 top-1/2 -translate-y-1/2 flex items-center cursor-pointer"
			role="button"
			:aria-label="$t(is_visible ? 'Hide password' : 'Show password')"
			:aria-pressed="is_visible"
			@mousedown.prevent
			@click="toggleVisibility"
			>
			<Icon :icon="is_visible ? 'lucide:eye-off' : 'lucide:eye'" class="icon" />
		</span>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'PasswordInput',

		// Атрибуты поля (классы размера, id, autocomplete, обработчики клавиш) должны
		// попасть на само поле, а не на обёртку — иначе сломается вид существующих форм.
		inheritAttrs: false,

		components: { Icon },

		props: {
			modelValue: {
				type: [String, Number],
				default: '',
			},
			// Подтверждение пароля скрывает свой глазик и следует за основным полем.
			reveal: {
				type: Boolean,
				default: true,
			},
			// Видимость, общая для связанных полей; без привязки поле управляет ею само.
			visible: {
				type: Boolean,
				default: null,
			},
		},

		emits: ['update:modelValue', 'update:visible'],

		data() {
			return {
				own_visible: false,
			};
		},

		computed: {
			is_visible() {
				return this.visible === null ? this.own_visible : this.visible;
			},
		},

		watch: {
			// Срабатывает до перерисовки, пока у поля ещё прежний тип.
			is_visible() {
				this.keepCaretOnTypeChange();
			},
		},

		methods: {
			// Смена типа поля сбрасывает каретку в браузере — возвращаем её на место.
			keepCaretOnTypeChange() {
				const input = this.$refs.input;
				if ( !input || document.activeElement !== input )
					return;

				const selection_start = input.selectionStart;
				const selection_end = input.selectionEnd;
				this.$nextTick(() => input.setSelectionRange(selection_start, selection_end));
			},
			toggleVisibility() {
				const next_visible = !this.is_visible;
				this.own_visible = next_visible;
				this.$emit('update:visible', next_visible);
			},
			inputElement() {
				return this.$refs.input;
			},
			focus() {
				this.$refs.input.focus();
			},
			select() {
				this.$refs.input.select();
			},
			// Браузерное автозаполнение пишет прямо в поле мимо модели — чистим и поле, и модель.
			clear() {
				this.$refs.input.value = '';
				this.$emit('update:modelValue', '');
			},
		},
	};
</script>

<style lang="scss" scoped>
	// Текст не должен заходить под глазик.
	.password-input-field.has-reveal {
		padding-right: 2.5rem !important;
	}

	// Свой глазик Edge дублировал бы наш.
	.password-input-field::-ms-reveal {
		display: none;
	}

	.password-reveal {
		color: var(--text-color-secondary);
	}
</style>
