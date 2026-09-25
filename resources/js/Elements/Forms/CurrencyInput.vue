<template>

	<div class="currency-input input-container flex items-center">

		<input
			ref="input"
			type="text"
			inputmode="decimal"
			:value="display"
			class="form-control w-full currency-input-field"
			:class="[ input_class, sizeClass, signBefore ? 'currency-input-field-sign-before' : 'currency-input-field-sign-after' ]"
			:placeholder="$t(placeholder)"
			:readonly="disabled"
			:disabled="disabled"
			:tabindex="disabled ? -1 : 0"
			@input = "(e) => handleInput(e)"
			@focus = "(e) => handleFocus(e)"
			@blur  = "(e) => handleBlur(e)"
			@keydown.enter = "(e) => handleEnter(e)"
			/>

		<span v-if="currencySymbol"
			class="currency-symbol"
			:class="[ sizeClass ? `currency-symbol-${size}` : '', signBefore ? 'currency-symbol-before' : 'currency-symbol-after' ]"
			>
			{{ currencySymbol }}
		</span>

	</div>

</template>

<script>
	import { currencySignPlacement, formatAmount, numberSeparators } from '@/js/currencyFormat.js';

	export default {
		name: 'CurrencyInput',
		props: {
			modelValue: {
				type: [String, Number],
				default: '',
			},
			value: {
				type: [String, Number],
				default: undefined,
			},
			currency: {
				type: String,
				default: '',
			},
			// Международный код валюты (UAH, USD): определяет сторону знака у поля.
			code: {
				type: String,
				default: '',
			},
			// Разделение разрядов в неактивном поле; при вводе число всегда голое.
			grouping: {
				type: Boolean,
				default: true,
			},
			placeholder: {
				type: String,
				default: '',
			},
			input_class: {
				type: String,
				default: '',
			},
			size: {
				type: String,
				default: '',
			},
			decimals: {
				type: Number,
				default: 2,
			},
			disabled: {
				type: Boolean,
				default: false,
			},
			// Разрешает ввод отрицательной суммы (ведущий «-»). По умолчанию выключено —
			// поведение поля без флага не меняется (минус вырезается как прежде).
			allow_negative: {
				type: Boolean,
				default: false,
			},
			// Включает калькулятор: ввод, начинающийся с «=», трактуется как
			// арифметическое выражение (+ - * / и скобки) и считается по Enter/blur.
			calculator: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['update:modelValue', 'change', 'blur', 'inputFocus'],
		data() {
			return {
				display: '',
				isFocused: false,
			}
		},
		computed: {
			sizeClass() {
				return this.size ? `form-control-${this.size}` : '';
			},
			currentValue() {
				if (this.modelValue !== '' && this.modelValue !== null && this.modelValue !== undefined)
					return this.modelValue;

				return this.value !== undefined ? this.value : '';
			},
			accountCurrency() {
				const currency_id = this.$page?.props?.account?.currency_id;
				const dict = this.$dictionaries?.currencies_a;

				return (currency_id && dict) ? dict[currency_id] : null;
			},
			currencySymbol() {
				if (this.currency) return this.currency;

				const entry = this.accountCurrency;

				return entry ? (entry.sign || entry.name || '') : '';
			},
			currencyCode() {
				if (this.code) return this.code;

				const entry = this.accountCurrency;

				return entry ? (entry.name || '') : '';
			},
			// Гривна и евро ставят знак после суммы, доллар и фунт — перед ней.
			signBefore() {
				return currencySignPlacement(this.currencyCode, this.currencySymbol).position === 'before';
			},
			// Разделители текущей локали нужны, чтобы разобрать вставленную
			// сгруппированную сумму обратно в число.
			separators() {
				return numberSeparators(this.$i18n?.locale);
			},
		},
		watch: {
			currentValue(newValue) {
				if (!this.isFocused)
					this.display = this.displayValue(newValue);
			},
		},
		mounted() {
			this.display = this.displayValue(this.currentValue);
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
			// CurrencyInput.sanitize — приводит произвольный ввод пользователя к допустимому числу
			sanitize(raw) {
				if (raw === null || raw === undefined) return '';

				let s = this.stripGrouping(String(raw));

				// При allow_negative запоминаем ведущий минус (по нему же определяем
				// «одинокий минус» в процессе набора), затем чистим строку от знака.
				const neg = this.allow_negative && s.trimStart().startsWith('-');

				// выкидываем всё кроме цифр и точки (пробелы-разделители, буквы, знак и т.д.)
				s = s.replace(/[^\d.]/g, '');

				// оставляем только первую точку
				const firstDot = s.indexOf('.');
				if (firstDot !== -1)
					s = s.slice(0, firstDot + 1) + s.slice(firstDot + 1).replace(/\./g, '');

				// ограничиваем число знаков после точки
				if (firstDot !== -1 && this.decimals >= 0) {
					const parts = s.split('.');
					s = parts[0] + '.' + parts[1].slice(0, this.decimals);
				}

				// Возвращаем минус (в т.ч. одинокий «-» при наборе, чтобы его не съедало).
				return neg ? '-' + s : s;
			},
			// CurrencyInput.stripGrouping — убирает разделители разрядов и приводит
			// десятичный знак к точке. Разделитель дробной части — тот из «,» и «.»,
			// что стоит правее: так «1,250.00» и «1.250,00» читаются одинаково верно.
			stripGrouping(raw) {
				let s = String(raw).replace(/[\s']/g, '');

				const group = this.separators.group;
				if (group && group !== '.' && group !== ',')
					s = s.split(group).join('');

				const last_comma = s.lastIndexOf(',');
				const last_dot   = s.lastIndexOf('.');

				if (last_comma !== -1 && last_dot !== -1)
					return last_comma > last_dot
						? s.split('.').join('').replace(/,/g, '.')
						: s.split(',').join('');

				return s.replace(/,/g, '.');
			},
			// CurrencyInput.formatValue — нормализует внешнее значение к строке с фиксированным числом знаков
			formatValue(raw) {
				if (raw === '' || raw === null || raw === undefined) return '';

				const n = Number(this.sanitize(raw));
				if (isNaN(n)) return '';

				return this.decimals >= 0 ? n.toFixed(this.decimals) : String(n);
			},
			// CurrencyInput.displayValue — вид суммы в неактивном поле: с разрядами
			// и разделителями текущей локали.
			displayValue(raw) {
				const plain = this.formatValue(raw);

				if (plain === '' || !this.grouping)
					return plain;

				return formatAmount(plain, { decimals: this.decimals, locale: this.$i18n?.locale });
			},
			emitValue(stringValue) {
				// Одинокий «-» (набор отрицательного числа ещё не завершён) трактуем как пусто.
				const emitted = (stringValue === '' || stringValue === '-') ? '' : Number(stringValue);
				this.$emit('update:modelValue', emitted);
			},
			handleInput(event) {
				const raw = event.target.value;

				// Режим калькулятора: пока строка начинается с «=», не очищаем её до
				// числа и не эмитим — копим выражение, считаем по Enter/blur.
				if (this.calculator && this.isFormula(raw)) {
					const clean = this.sanitizeFormula(raw);
					this.display = clean;
					event.target.value = clean;
					return;
				}

				const clean = this.sanitize(raw);

				this.display = clean;
				// принудительно возвращаем очищенное значение в DOM, если что-то было вырезано
				event.target.value = clean;

				this.emitValue(clean);
			},
			handleEnter(event) {
				if (this.calculator && this.isFormula(this.display)) {
					event.preventDefault();
					if (this.evaluateFormula())
						this.select();
				}
			},
			// CurrencyInput.isFormula — строка является арифметическим выражением калькулятора
			isFormula(raw) {
				return String(raw == null ? '' : raw).trimStart().startsWith('=');
			},
			// CurrencyInput.sanitizeFormula — оставляет только допустимые символы выражения
			sanitizeFormula(raw) {
				if (raw === null || raw === undefined) return '';

				// запятая → точка; разрешаем цифры, точку, операторы, скобки, пробелы и «=»
				let s = String(raw).replace(/,/g, '.').replace(/[^=\d.+\-*/() ]/g, '');

				// «=» допустим только первым символом
				s = '=' + s.replace(/=/g, '');

				return s;
			},
			// CurrencyInput.evaluateFormula — считает выражение и подставляет результат
			evaluateFormula() {
				if (!this.calculator || !this.isFormula(this.display))
					return false;

				const expr = String(this.display).trim().slice(1);
				const result = this.computeExpression(expr);

				if (result === null || isNaN(result) || !isFinite(result)) {
					this.display = '';
					this.emitValue('');
					return true;
				}

				const formatted = this.decimals >= 0 ? Number(result).toFixed(this.decimals) : String(result);
				this.display = formatted;
				this.emitValue(formatted);
				return true;
			},
			// CurrencyInput.computeExpression — безопасный парсер + - * / и скобок (без eval)
			computeExpression(input) {
				const s = String(input).replace(/\s+/g, '');
				if (!s) return null;

				let pos = 0;
				const peek = () => s[pos];

				const parseExpr = () => {
					let value = parseTerm();
					if (value === null) return null;
					while (peek() === '+' || peek() === '-') {
						const op = s[pos++];
						const rhs = parseTerm();
						if (rhs === null) return null;
						value = op === '+' ? value + rhs : value - rhs;
					}
					return value;
				};

				const parseTerm = () => {
					let value = parseFactor();
					if (value === null) return null;
					while (peek() === '*' || peek() === '/') {
						const op = s[pos++];
						const rhs = parseFactor();
						if (rhs === null) return null;
						if (op === '/') {
							if (rhs === 0) return null;
							value = value / rhs;
						} else {
							value = value * rhs;
						}
					}
					return value;
				};

				const parseFactor = () => {
					if (peek() === '+') { pos++; return parseFactor(); }
					if (peek() === '-') { pos++; const v = parseFactor(); return v === null ? null : -v; }
					if (peek() === '(') {
						pos++;
						const v = parseExpr();
						if (v === null || peek() !== ')') return null;
						pos++;
						return v;
					}
					return parseNumber();
				};

				const parseNumber = () => {
					const start = pos;
					while (pos < s.length && /[\d.]/.test(s[pos])) pos++;
					if (pos === start) return null;
					const num = Number(s.slice(start, pos));
					return isNaN(num) ? null : num;
				};

				const result = parseExpr();
				// остались неразобранные символы — выражение некорректно
				if (pos !== s.length) return null;
				return result;
			},
			handleFocus(event) {
				this.isFocused = true;
				this.display = this.formatValue(this.currentValue);
				this.$emit('inputFocus', event);
			},
			handleBlur(event) {
				this.isFocused = false;

				// Незавершённое выражение калькулятора считаем при потере фокуса.
				if (this.calculator && this.isFormula(this.display))
					this.evaluateFormula();

				if (this.display !== '') {
					const n = Number(this.display);
					if (!isNaN(n)) {
						const plain = this.decimals >= 0 ? n.toFixed(this.decimals) : String(n);
						this.emitValue(plain);
						this.display = this.displayValue(plain);
					} else {
						// Ввод не сводится к числу (например одинокий «-») — очищаем.
						this.display = '';
						this.emitValue('');
					}
				}

				this.$emit('change', event);
				this.$emit('blur', event);
			},
		},
	}
</script>

<style lang="scss" scoped>

	.currency-input {
		position: relative;
		width: 100%;
		background: inherit !important;
	}

	.currency-input-field {
		text-align: right;
	}

	// место под символ валюты — !important, т.к. form-control-* классы (в т.ч.
	// подключаемые снаружи для мобильных брейкпоинтов) задают свои поля с более
	// высокой специфичностью и иначе перебивают его
	.currency-input-field-sign-after {
		padding-right: 2rem !important;
	}

	.currency-input-field-sign-before {
		padding-left: 2rem !important;
	}

	.currency-symbol {
		position: absolute;
		top: 1px;
		bottom: 0;
		display: flex;
		align-items: center;
		// размер символа по умолчанию совпадает с базовым шрифтом поля
		font-size: var(--text-base);
		font-weight: 600;
		color: var(--yellow-color);
		pointer-events: none;
		white-space: nowrap;
		line-height: 1;
	}

	.currency-symbol-after  { right: 1rem; }
	.currency-symbol-before { left: 1rem; }

	// размер символа валюты соразмерен размеру шрифта ввода суммы
	.currency-symbol-sm { font-size: var(--text-sm); }
	.currency-symbol-md { font-size: var(--text-md); }
	.currency-symbol-lg { font-size: var(--text-lg); }
	.currency-symbol-xl { font-size: var(--text-xl); }

	// На вузьких екранах поле суми звужується (form-control-md) — символ валюти
	// зменшуємо разом з ним, інакше він лишається розмiру lg й виглядає непропорційно.
	@media (max-width: 639px) {
		.currency-symbol-lg { font-size: var(--text-md); }
	}
</style>
