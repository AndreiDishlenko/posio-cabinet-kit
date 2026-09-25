<template>

	<!-- Строка настройки-переключателя: тумблер и приглушённая подпись рядом -->
	<div class="toggle-field flex items-center space-x-3"
		:class="[ sizeClass, { 'disabled': disabled } ]"
		>
		<Toggler v-model="checked" :disabled="disabled" />

		<!-- Подпись работает как сам тумблер: попасть пальцем в текст проще -->
		<span class="text-secondary cursor-pointer select-none" :class="textSizeClass" @click="toggle()">{{ resolvedLabel }}</span>
	</div>

</template>

<script>
	import Toggler from '@/js/Elements/Toggler.vue';

	// Размер переключателя задаётся классом на обёртке — так его знает и сам
	// переключатель, и подпись рядом.
	// Подпись мельче переключателя не делаем: в карточках кабинета она стоит в ряд
	// с обычными полями формы, и уменьшенный текст читался бы как второстепенный.
	const SIZE = {
		sm:   { toggler: 'toggler-sm', text: '' },
		base: { toggler: '',           text: '' },
		lg:   { toggler: 'toggler-lg', text: 'text-lg' },
	}

	export default {
		components: { Toggler },
		props: {
			modelValue: {
				type: [Boolean, Number, String],
				default: false,
			},
			label: {
				type: String,
				default: '',
			},
			text: {
				type: String,
				default: '',
			},
			disabled: {
				type: Boolean,
				default: false,
			},
			size: {
				type: String,
				default: 'base',
				validator: (v) => ['sm', 'base', 'lg'].includes(v),
			},
		},
		emits: ['update:modelValue', 'change'],
		computed: {
			sizeClass() {
				return (SIZE[this.size] || SIZE.base).toggler;
			},
			textSizeClass() {
				return (SIZE[this.size] || SIZE.base).text;
			},
			resolvedLabel() {
				const label = this.label || this.text;

				return label ? this.$t(label) : '';
			},
			checked: {
				get() {
					return !!this.modelValue;
				},
				set(value) {
					// Тип значения сохраняем: карточки кабинета хранят такие настройки
					// числом и проверяют их числовым правилом.
					const result = typeof this.modelValue === 'boolean' ? !!value : Number(value);

					this.$emit('update:modelValue', result);
					this.$emit('change', result);
				},
			},
		},
		methods: {
			toggle() {
				if ( this.disabled )
					return false;

				this.checked = !this.checked;

				return true;
			},
		},
	}
</script>

<style lang="scss" scoped>
</style>
