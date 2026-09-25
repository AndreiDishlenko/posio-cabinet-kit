<template>

	<div class="number-stepper" :class="[ size_class, { 'is-disabled': disabled } ]">

		<button type="button" class="stepper-button" tabindex="-1"
			:disabled="disabled || at_min"
			@click="stepBy(-1)"
			>
			<Icon icon="mdi:minus" class="icon icon-md" />
		</button>

		<input
			ref="input"
			type="text"
			inputmode="numeric"
			class="stepper-field"
			:value="display"
			:disabled="disabled"
			:placeholder="placeholder ? $t(placeholder) : ''"
			@input = "(e) => handleInput(e)"
			@blur  = "(e) => handleBlur(e)"
			@focus = "(e) => $emit('inputFocus', e)"
			@keydown.up.prevent   = "stepBy(1)"
			@keydown.down.prevent = "stepBy(-1)"
			/>

		<button type="button" class="stepper-button" tabindex="-1"
			:disabled="disabled || at_max"
			@click="stepBy(1)"
			>
			<Icon icon="mdi:plus" class="icon icon-md" />
		</button>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'NumberStepper',
		components: { Icon },
		props: {
			modelValue: {
				type: [String, Number],
				default: '',
			},
			min: {
				type: Number,
				default: 0,
			},
			// Без верхньої межі — null.
			max: {
				type: Number,
				default: null,
			},
			increment: {
				type: Number,
				default: 1,
			},
			size: {
				type: String,
				default: '',
			},
			placeholder: {
				type: String,
				default: '',
			},
			disabled: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['update:modelValue', 'change', 'blur', 'inputFocus'],
		data() {
			return {
				display: '',
			}
		},
		computed: {
			size_class() {
				return this.size ? 'stepper-' + this.size : '';
			},
			// Порожнє поле під час набору — це відсутнє значення, а не нуль.
			current() {
				if ( this.modelValue === '' || this.modelValue === null || this.modelValue === undefined )
					return null;

				const value = Number(this.modelValue);
				return isNaN(value) ? null : value;
			},
			at_min() {
				return this.current !== null && this.current <= this.min;
			},
			at_max() {
				return this.max !== null && this.current !== null && this.current >= this.max;
			},
		},
		watch: {
			modelValue(value) {
				// Поки поле в фокусі, набране не переписуємо: інакше проміжне значення
				// нормалізується прямо під пальцями і «10» не набрати.
				if ( this.$refs.input !== document.activeElement )
					this.display = (value === null || value === undefined) ? '' : String(value);
			},
		},
		mounted() {
			this.display = this.current === null ? '' : String(this.current);
		},
		methods: {
			focus() {
				this.$refs.input.focus();
			},
			select() {
				this.$nextTick(() => {
					this.$refs.input.select();
				})
			},
			clamp(value) {
				let result = Math.round(value);

				if ( result < this.min )
					result = this.min;

				if ( this.max !== null && result > this.max )
					result = this.max;

				return result;
			},
			stepBy(direction) {
				if ( this.disabled )
					return;

				const base  = this.current === null ? this.min : this.current;
				const value = this.clamp(base + direction * this.increment);

				this.display = String(value);
				this.$emit('update:modelValue', value);
				this.$emit('change', value);
			},
			handleInput(event) {
				// Кількість — ціле число: усе інше з набору просто відкидається.
				const clean = String(event.target.value).replace(/[^\d]/g, '');

				this.display = clean;
				event.target.value = clean;

				this.$emit('update:modelValue', clean === '' ? '' : Number(clean));
			},
			handleBlur(event) {
				const value = this.display === '' ? this.min : this.clamp(Number(this.display));

				this.display = String(value);
				this.$emit('update:modelValue', value);
				this.$emit('change', value);
				this.$emit('blur', event);
			},
		},
	}
</script>

<style lang="scss" scoped>

	.number-stepper {
		--stepper-height: var(--ui-h-base);

		display: flex;
		align-items: center;
		width: 100%;
		max-width: 100%;
		height: var(--stepper-height);
		font-size: var(--text-base);
		color: var(--text-color);
		background-color: var(--form-control-background);
		border: 1px solid var(--form-control-border-color);
		overflow: hidden;

		@apply rounded-lg;
	}

	.number-stepper:focus-within {
		border-color: var(--form-control-border-color-focus);
	}

	.number-stepper.is-disabled {
		opacity: 0.5;
	}

	// Кнопка — квадрат по висоті поля: степер лишається одного зросту з рештою
	// полів форми, а зона натискання не залежить від довжини числа.
	.stepper-button {
		display: flex;
		align-items: center;
		justify-content: center;
		flex: 0 0 auto;
		width: var(--stepper-height);
		height: 100%;
		padding: 0;
		color: var(--text-color-secondary);
		background: transparent;
		border: 0;
		cursor: pointer;
		transition: background-color .15s ease-in-out, color .15s ease-in-out;
	}

	.stepper-button:hover {
		color: var(--text-color);
		background-color: var(--button-hover-background);
	}

	.stepper-button:disabled {
		opacity: 0.35;
		cursor: auto;
	}

	.stepper-button:disabled:hover {
		background-color: transparent;
	}

	// Роздільники малює саме поле: рамки в сусідніх кнопок склали б подвійну лінію.
	.stepper-field {
		flex: 1 1 auto;
		min-width: 0;
		height: 100%;
		text-align: center;
		font-size: inherit;
		font-weight: 600;
		color: inherit;
		background: transparent;
		border-top: 0;
		border-bottom: 0;
		border-left: 1px solid var(--divider-color);
		border-right: 1px solid var(--divider-color);
		outline: none;
	}

	// Загальне правило підсвічує рамку будь-якого текстового поля у фокусі —
	// тут підсвічується контейнер, а роздільники лишаються роздільниками.
	.stepper-field:focus {
		border-color: var(--divider-color);
		border-width: 1px;
	}

	.stepper-field::placeholder {
		font-weight: 400;
		color: var(--placeholder-color);
	}

	.number-stepper.stepper-xs { --stepper-height: var(--ui-h-xs); font-size: var(--text-xs); }
	.number-stepper.stepper-sm { --stepper-height: var(--ui-h-sm); font-size: var(--text-sm); }
	.number-stepper.stepper-md { --stepper-height: var(--ui-h-md); font-size: var(--text-md); }
	.number-stepper.stepper-lg { --stepper-height: var(--ui-h-lg); font-size: var(--text-lg); }

	// Поля форм на вузькому екрані знижуються до базового зросту (form-control без
	// md-модифікатора) — степер тримає з ними один ряд.
	@media (max-width: 767px) {
		.number-stepper.stepper-lg {
			--stepper-height: var(--ui-h-base);
			font-size: var(--text-base);
		}
	}
</style>
