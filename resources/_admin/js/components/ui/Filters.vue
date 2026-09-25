<template>

	<div class="filter-container page-filters compact-card !p-3 flex flex-col">

		<!-- Desktop header: title + reset -->
		<!-- <div class="lt-md:hidden flex items-center justify-between mb-2">
			<span class="flex items-center gap-1.5 text-xs font-medium uppercase text-secondary tracking-wider">
				<Icon icon="mdi:tune-variant" class="icon icon-sm" />
				{{ $t('Filter data') }}
			</span>
			<button class="text-xs text-secondary hover:text-primary transition-colors" @click="resetAllFilters">
				{{ $t('Reset all') }}
			</button>
		</div> -->

		<!-- Mobile: chips + toggle button in one row -->
		<!-- <div class="md:hidden flex items-center gap-2 mb-2">
			<div class="flex items-center gap-1.5 flex-wrap flex-1">
				<span v-for="chip in [...activeChips].reverse()" :key="chip.key"
					class="inline-flex items-center gap-1.5 text-xs py-1 rounded-full border transition-all duration-200 select-none"
					:class="chip.removable
						? 'pl-2.5 pr-1.5 bg-primary/15 border-primary/40 text-primary shadow-sm shadow-primary/10'
						: 'px-2.5 bg-white/10 border-white/20 text-slate-200'">
					<Icon v-if="chip.icon" :icon="chip.icon" class="icon icon-sm" :class="chip.removable ? '' : 'opacity-70'" />
					<span class="font-medium">{{ chip.label }}</span>
					<button v-if="chip.removable"
						class="flex items-center justify-center w-4 h-4 rounded-full bg-white/10 hover:bg-white/25 transition-all duration-150 ml-0.5"
						@click.stop="removeChip(chip.key)">
						<Icon icon="mdi:close" class="icon" style="font-size:10px" />
					</button>
				</span>
			</div>
			<button class="flex-shrink-0 flex items-center justify-center w-8 h-8 rounded-lg border transition-all duration-200"
				:class="isMobileFiltersOpen
					? 'border-primary/40 text-primary bg-primary/10'
					: 'border-white/15 text-slate-400 hover:text-primary hover:border-primary/30 hover:bg-primary/5'"
				@click="isMobileFiltersOpen = !isMobileFiltersOpen">
				<Icon icon="mdi:filter-variant" class="icon" />
			</button>
		</div> -->

		<!-- Filter inputs grid -->
		<div class="flex items-center lt-md:items-end gap-2">

			<div class="filter-inputs !grid w-fit lt-md:!w-full lt-md:!grid-cols-1"
				:class="[customClass]"
				:style="!customClass ? { gridTemplateColumns: columnsWidthes } : ''">

				<div v-for="def in visible_filters" :key="def.name"
					class="label-group"
					:class="[getShowClass(def.name), def.type === 'year_quarter' ? 'flex !flex-row gap-2' : '']">

					<!-- Вибір зі списку: довідник або готовий перелік -->
					<Selectable v-if="def.type === 'select'"
						:class="def.class"
						:input_class="'form-control-md'"
						v-model="filters[def.model]"
						:in_data="listFor(def)"
						:filter="listFilterFor(def)"
						:placeholder="placeholderFor(def)"
						:is_mandatory="isMandatory(def)"
						:addall="hasAllOption(def)"
						@onChange="changeFilter(def.name)"
						/>

					<!-- Пошук по списку (набір тексту + підказки) -->
					<SelectableInput v-else-if="def.type === 'search'"
						class="filter-item form-control-md !p-0"
						v-model="filters[def.model]"
						:in_data="listFor(def)"
						:clear_button="true"
						:placeholder="$t(def.placeholder)"
						@onChange="changeFilter(def.name)"
						@onEnterKey="changeFilter(def.name)"
						/>

					<!-- Дата -->
					<VueDatePicker v-else-if="def.type === 'date'"
						v-model="filters.date"
						model-type="yyyy-MM-dd"
						format="dd.MM.yyyy"
						@update:model-value="(new_val) => handleDateChange(new_val, 'date')"
						:locale="$i18n.locale"
						:enable-time-picker="false"
						auto-apply
						:ui="{ input: 'form-control form-control-md  !ps-8 !pe-7' }"
						position="right"
						:placeholder="$t('Date')"
						:dark="true"
						/>

					<!-- Період -->
					<VueDatePicker v-else-if="def.type === 'date_range'"
						v-model="filters.date_range"
						range
						model-type="yyyy-MM-dd"
						format="dd.MM.yyyy"
						@update:model-value="(new_val) => handleDateRangeChange(new_val, 'date_range')"
						:locale="$i18n.locale"
						:enable-time-picker="false"
						auto-apply
						:ui="{ input: 'form-control form-control-md text-secondary !ps-8 !pe-7' }"
						position="right"
						:placeholder="$t('Date')"
						:dark="true"
						/>

					<!-- Місяць з роком (період = весь місяць) -->
					<VueDatePicker v-else-if="def.type === 'month'"
						v-model="month"
						month-picker
						auto-apply
						format="LLLL yyyy"
						:format-locale="uk"
						:ui="{ input: 'form-control form-control-md text-secondary !ps-8 !pe-7' }"
						:dark="true"
						@update:model-value="(new_val) => handleDateRangeChange(new_val, 'month')"
						/>

					<!-- Період довільної довжини: пресети збоку календаря (на телефоні — згорнуті
					     під ним), стрілки — сусідній період -->
					<div v-else-if="def.type === 'period'" class="period-filter flex items-center gap-1">

						<button type="button"
							class="button button-md button-square outline-button text-secondary shrink-0"
							:title="$t('Previous period')"
							@click="shiftPeriod(-1)">
							<Icon icon="mdi:chevron-left" class="icon" />
						</button>

						<div class="grow min-w-0 md:w-[15.5rem]">
							<VueDatePicker
								ref="period_picker"
								v-model="filters.date_range"
								range
								:multi-calendars="period_single_calendar ? false : { count: 2 }"
								model-type="yyyy-MM-dd"
								:format="formatPeriod"
								:preset-dates="is_mobile ? [] : periodPresets"
								:start-date="periodCalendarStart"
								focus-start-date
								:alt-position="placePeriodMenu"
								:locale="$i18n.locale"
								:week-start="1"
								:enable-time-picker="false"
								auto-apply
								:ui="{ input: 'form-control form-control-md text-secondary !ps-8 !pe-7' }"
								position="left"
								:placeholder="$t('Period')"
								:dark="true"
								@update:model-value="handlePeriodChange"
								>
								<template #period-preset="{ label, value, presetDate }">
									<button type="button"
										class="button button-md preset-button w-full"
										:class="{ selected: isActivePeriod(value) }"
										@click="presetDate(value)">
										{{ label }}
									</button>
								</template>

								<!-- На телефоні пресети згорнуті під календарем і розгортаються за тапом:
								     календар лишається головним, а розгорнуті пресети — просторими. -->
								<template v-if="is_mobile" #left-sidebar>
									<div class="period-presets-mobile">
										<button type="button"
											class="button button-sm preset-button w-full justify-between"
											:aria-expanded="period_presets_open"
											@click="period_presets_open = !period_presets_open">
											<span>{{ $t('Quick select') }}</span>
											<Icon icon="mdi:chevron-down" class="icon transition-transform"
												:class="{ 'rotate-180': period_presets_open }" />
										</button>

										<div v-if="period_presets_open" class="grid grid-cols-2 gap-2 pt-2">
											<button v-for="preset in periodPresets" :key="preset.label" type="button"
												class="button button-md preset-button w-full"
												:class="{ selected: isActivePeriod(preset.value) }"
												@click="applyPeriodPreset(preset.value)">
												{{ preset.label }}
											</button>
										</div>
									</div>
								</template>
							</VueDatePicker>
						</div>

						<button type="button"
							class="button button-md button-square outline-button text-secondary shrink-0"
							:title="$t('Next period')"
							@click="shiftPeriod(1)">
							<Icon icon="mdi:chevron-right" class="icon" />
						</button>

					</div>

					<!-- Рік -->
					<VueDatePicker v-else-if="def.type === 'year'"
						v-model="filters.year"
						year-picker
						auto-apply
						:ui="{ input: 'form-control form-control-md text-secondary !ps-8 !pe-7' }"
						:dark="true"
						@update:model-value="(new_val) => handleYearChange(new_val)"
						/>

					<!-- Рік + квартал (комбінований → date_range) -->
					<template v-else-if="def.type === 'year_quarter'">
						<Selectable class="!min-w-0 grow"
							:input_class="'form-control-md'"
							v-model="filters.quarter"
							:in_data="quarterOptions"
							:is_mandatory="true"
							:placeholder="$t('Quarter')"
							@onChange="changeYearQuarter()"
							/>
						<Selectable class="!min-w-0 w-24 shrink-0"
							:input_class="'form-control-md'"
							v-model="filters.year"
							:in_data="yearOptions"
							:is_mandatory="true"
							:placeholder="$t('Year')"
							@onChange="changeYearQuarter()"
							/>
					</template>

				</div>

				<!-- <button class="text-xs text-secondary hover:text-primary transition-colors" @click="resetAllFilters">
					{{ $t('Reset all') }}
				</button> -->

			</div>

			<!-- Кінцеві кнопки (іконка фільтрів + меню звіту) завжди притиснуті до кінця
			     рядка: спільний контейнер з одним ml-auto (не по ml-auto на кожній —
			     інакше вільний простір ділиться між ними й іконка фільтрів їде до центру). -->
			<div class="ml-auto flex items-center gap-2">

				<!-- Перезавантаження даних сторінки поточними фільтрами (значення не скидаються). -->
				<button v-if="show_refresh" type="button"
					class="flex items-center justify-center text-secondary px-1 button button-md !w-9"
					:title="$t('Refresh')"
					@click="refresh">
					<Icon icon="mdi:refresh" class="icon icon-md" />
				</button>

				<span class="lt-md:hidden flex items-center text-secondary px-1 button button-md !w-9">
					<Icon icon="mdi:tune-variant" class="icon icon-md" />
				</span>

			</div>

		</div>

	</div>

</template>

<script setup>
	import { uk } from 'date-fns/locale';
	import { format } from 'date-fns';
</script>

<script>
	import { Icon }			from '@iconify/vue';
	import VueDatePicker	from '@vuepic/vue-datepicker';

	import Selectable		from '@/js/Elements/Forms/Selectable.vue';
	import SelectableInput	from '@/js/Elements/Forms/SelectableInput.vue';

	import mobileViewportMixin from '@/js/_mobileViewportMixin.js';

	// Запас між меню періоду й краями видимої області.
	const PERIOD_MENU_MARGIN = 8;
	// Приблизна ширина меню з двома календарями й колонкою пресетів: у вужчій видимій
	// області лишається один календар, інакше меню не вміщається.
	const PERIOD_TWO_CALENDARS_WIDTH = 736;

	// Реєстр фільтрів: порядок записів задає порядок колонок у рядку (порядок ключів,
	// з якими сторінка перелічила фільтри, на вигляд не впливає).
	// Поля: type — вид контрола; model — ключ у стані фільтрів; dict/source — звідки
	// перелік; active_only — ховати мʼяко видалені; scope_pos — перелік звужується
	// вибраною точкою; always_all — пункт «усі» є завжди, незалежно від mandatory.
	const FILTER_DEFS = [
		{
			name: 'poses',
			type: 'select',
			model: 'pos_id',
			dict: 'poses',
			class: 'filter-pos filter-item',
			active_only: true,
			placeholder: 'Point of sale',
			all_placeholder: 'All points of sale',
		},
		{
			name: 'cashboxes',
			type: 'select',
			model: 'cashbox_id',
			dict: 'cashboxes',
			class: 'filter-item',
			active_only: true,
			scope_pos: true,
			placeholder: 'Cashbox',
			all_placeholder: 'All cashboxes',
		},
		{
			name: 'cash_account',
			type: 'select',
			model: 'cash_account_id',
			dict: 'cash_accounts',
			class: 'filter-item',
			placeholder: 'Cash account',
			all_placeholder: 'All cash accounts',
		},
		{
			name: 'correspondent_account',
			type: 'search',
			model: 'correspondent_account_name',
			source: 'correspondentAccountOptions',
			placeholder: 'Correspondent account',
		},
		{
			name: 'date',
			type: 'date',
			model: 'date',
		},
		{
			name: 'cashflow_item',
			type: 'search',
			model: 'cashflow_item_id',
			source: 'cashflowItemOptions',
			placeholder: 'All cash flow items',
		},
		{
			name: 'counterparties',
			type: 'search',
			model: 'counterparty_id',
			source: 'counterpartyOptions',
			placeholder: 'Search Counterparty',
		},
		{
			name: 'cashiers',
			type: 'select',
			model: 'cashier_id',
			dict: 'cashiers',
			class: 'filter-item',
			active_only: true,
			placeholder: 'Cashier',
			all_placeholder: 'All cashiers',
		},
		{
			name: 'doctypes',
			type: 'select',
			model: 'doctype_id',
			dict: 'doctypes',
			class: 'filter-item',
			always_all: true,
			all_placeholder: 'All doc types',
		},
		{
			name: 'paytypes',
			type: 'select',
			model: 'paytype_id',
			dict: 'paytypes',
			class: 'filter-item',
			always_all: true,
			all_placeholder: 'All payment methods',
		},
		{
			name: 'date_range',
			type: 'date_range',
			model: 'date_range',
		},
		{
			name: 'month',
			type: 'month',
			model: 'date_range',
		},
		{
			name: 'period',
			type: 'period',
			model: 'date_range',
		},
		{
			name: 'report_month',
			type: 'select',
			model: 'month',
			source: 'monthOptions',
			class: 'filter-item',
			always_all: true,
			all_placeholder: 'All months',
		},
		{
			name: 'year',
			type: 'year',
			model: 'year',
		},
		{
			name: 'year_quarter',
			type: 'year_quarter',
			model: 'quarter',
		},
		{
			name: 'product',
			type: 'search',
			model: 'product_id',
			source: 'productOptions',
			placeholder: 'Search Product',
		},
	];

	// Скільки чекати довідники, перш ніж дозволити сторінці вантажити дані з тим,
	// що є: без страховки збій завантаження довідників залишив би порожній екран.
	const DICTIONARIES_WAIT = 5000;

	// Цілі періоди, які впізнає фільтр періоду, — у місяцях.
	const PERIOD_MONTHS = { month: 1, quarter: 3, year: 12 };

	export default {
		components: { Icon, Selectable, SelectableInput, VueDatePicker },
		// На телефоні один календар замість двох — два поруч не вміщаються.
		mixins: [mobileViewportMixin],
		inject: {
			// Затвор первинного завантаження сторінки (дає міксин таблиці). Панель фільтрів
			// сама повідомляє, коли значення підставлені й запит матиме сенс.
			filters_gate: { default: null },
		},
		props: {
			filters: {
				type: Object,
				required: true,
				default: {},
			},
			options: {
				type: Object,
				requires: false,
				default: {}
			},
			customClass: {
				type: String,
				default: ''
			},
			storage_key: {
				type: String,
				default: ''
			},
			no_persist: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['onChange', 'ready'],
		watch: {
			filters: {
				handler(new_val, val) {
					// console.log('changeDictionary');
					if ( new_val.pos_id == '' )
						delete new_val.pos_id;
				},
				immediate: true,
				deep: true
			},
			// Довідники приїжджають асинхронно і змінюються разом з акаунтом — значення
			// фільтрів перевіряються заново на кожен такий склад.
			dictionaries_signature() {
				this.onDictionariesChange();
			},
		},
		data: function() {
			return {
				isMobileFiltersOpen: false,
				previousDocDate: null,
				filters_snapshot: '',
				// Значення, підставлені автоматично: їх не можна запамʼятовувати як
				// вибір користувача, інакше вони протікають на інші сторінки.
				auto_filled: {},
				is_silent: false,
				is_ready: false,
				ready_timer: null,
				period_presets_open: false,
				period_menu_narrow: false,
			}
		},
		computed: {
			accountId() {
				return this.$page?.props?.account?.id ?? null;
			},
			// Кнопка перезавантаження є на кожній сторінці з фільтрами; вимикається явно.
			show_refresh() {
				return this.options.refresh !== false;
			},
			// Фільтри, замовлені сторінкою, у канонічному порядку реєстру.
			visible_filters() {
				return FILTER_DEFS.filter(def => this.options[def.name]);
			},
			// Фільтри, значення яких перевіряються по довіднику.
			dict_filters() {
				return this.visible_filters.filter(def => def.dict && def.type === 'select');
			},
			// Склад довідників, задіяних фільтрами: зміна складу — привід перевірити значення.
			dictionaries_signature() {
				return this.dict_filters
					.map(def => (this.$dictionaries[def.dict] || [])
						.map(item => `${item.id}:${item.is_deleted ? 1 : 0}:${item.pos_id ?? ''}`)
						.join(','))
					.join('|');
			},
			// Довідники обовʼязкових фільтрів наповнені — запит сторінки матиме сенс.
			dictionaries_ready() {
				return this.dict_filters
					.filter(def => this.options[def.name]?.mandatory)
					.every(def => (this.$dictionaries[def.dict] || []).length > 0);
			},
			columnsWidthes() {
				let arr = this.visible_filters.map(() => 'auto');
				// arr.unshift('min-content'); // Filters icon

				return arr.join(' ');
			},
			counterpartyOptions() {
				return (this.$dictionaries.counterparties || [])
					.filter(item => !item.is_deleted)
					.map(item => ({ id: item.id, name: item.name }))
					.sort((a, b) => String(a.name).localeCompare(String(b.name)));
			},
			cashflowItemOptions() {
				return (this.$dictionaries.cashflow_items || [])
					.map(item => ({ id: item.id, name: `${item.code} ${item.name}` }));
			},
			productOptions() {
				return (this.$dictionaries.products || []).map(item => ({ id: item.id, name: item.name }));
			},
			// Місяці року з локалізованими назвами (Січень, Лютий … / January …) — id = номер місяця.
			monthOptions() {
				const locale = this.$i18n?.locale || 'uk';
				const fmt    = new Intl.DateTimeFormat(locale, { month: 'long' });

				return Array.from({ length: 12 }, (_, i) => {
					const name = fmt.format(new Date(2020, i, 1));
					return { id: i + 1, name: name.charAt(0).toUpperCase() + name.slice(1) };
				});
			},
			// Роки для комбінованого фільтра рік-квартал: поточний і 5 попередніх.
			yearOptions() {
				const current = this.$dayjs().year();
				return Array.from({ length: 6 }, (_, i) => {
					const year = current - i;
					return { id: year, name: String(year) };
				});
			},
			// Швидкий вибір цілого місяця / кварталу / року — поточного й попереднього.
			periodPresets() {
				const now           = this.$dayjs();
				const month_start   = now.startOf('month');
				const quarter_start = this.quarterStart(now);
				const year_start    = now.startOf('year');

				// Власну розмітку кнопки пакет бере лише зі слота, названого в самому пресеті.
				return [
					{ label: this.$t('This month'),   value: this.periodRange(month_start, 'month') },
					{ label: this.$t('Last month'),   value: this.periodRange(month_start.subtract(1, 'month'), 'month') },
					{ label: this.$t('This quarter'), value: this.periodRange(quarter_start, 'quarter') },
					{ label: this.$t('Last quarter'), value: this.periodRange(quarter_start.subtract(3, 'month'), 'quarter') },
					{ label: this.$t('This year'),    value: this.periodRange(year_start, 'year') },
					{ label: this.$t('Last year'),    value: this.periodRange(year_start.subtract(1, 'year'), 'year') },
				].map(preset => ({ ...preset, slot: 'period-preset' }));
			},
			// Один календар — на телефоні й там, де два поруч не вміщаються у видиму область.
			period_single_calendar() {
				return this.is_mobile || this.period_menu_narrow;
			},
			// Календар відкривається на місяці, де закінчується період, але не далі
			// поточного — минуле потрібніше за майбутнє. На двох календарях цей місяць
			// праворуч, а ліворуч — попередній, а не наступний.
			periodCalendarStart() {
				const [from, to] = this.filters.date_range || [];
				if ( !from )
					return null;

				const today = this.$dayjs();
				const start = this.$dayjs(from);
				let focus   = this.$dayjs(to || from);

				if ( focus.isAfter(today, 'month') )
					focus = start.isAfter(today, 'month') ? start : today;

				focus = focus.startOf('month');

				return (this.period_single_calendar ? focus : focus.subtract(1, 'month')).toDate();
			},
			// Квартали для комбінованого фільтра рік-квартал.
			quarterOptions() {
				return [1, 2, 3, 4].map(q => ({ id: q, name: `${this.$t('Quarter')} ${q}` }));
			},
			// Кореспондентський рахунок — назва місця грошей звʼязаного документа переказу
			// (рахунок або каса). Фільтр по назві (рядки несуть лише correspondent_account_name).
			correspondentAccountOptions() {
				const accounts  = (this.$dictionaries.cash_accounts || []).map(item => item.name);
				const cashboxes = (this.$dictionaries.cashboxes || []).map(item => item.name);

				return [...new Set([...accounts, ...cashboxes])]
					.filter(Boolean)
					.sort((a, b) => String(a).localeCompare(String(b)))
					.map(name => ({ id: name, name }));
			},
			activeChips() {
				const chips = [];

				// Period chip (month or date_range)
				if ((this.options.month || this.options.date_range) && this.filters.date_range?.[0]) {
					let label;
					if (this.options.month) {
						const raw = format(new Date(this.filters.date_range[0]), 'LLLL yyyy', { locale: uk });
						label = raw.charAt(0).toUpperCase() + raw.slice(1);
					} else {
						const start = this.$dayjs(this.filters.date_range[0]).format('DD.MM');
						const end   = this.$dayjs(this.filters.date_range[1]).format('DD.MM.YY');
						label = `${start} – ${end}`;
					}
					chips.push({ key: 'date_range', label, icon: 'mdi:calendar', removable: false });
				}

				// Product chip
				if (this.options.product && this.filters.product_id) {
					const product = this.$dictionaries.products?.find(p => p.id == this.filters.product_id);
					if (product)
						chips.push({ key: 'product_id', label: product.name, removable: true });
				}

				// Cashbox chip
				if (this.options.cashboxes) {
					if (this.filters.cashbox_id) {
						const cashbox = this.$dictionaries.cashboxes?.find(c => c.id == this.filters.cashbox_id);
						chips.push({ key: 'cashbox_id', label: cashbox?.name || this.$t('Cashbox'), removable: true });
					} else {
						chips.push({ key: 'cashbox_id', label: this.$t('All cashboxes'), removable: false });
					}
				}

				// POS chip
				if (this.options.poses && this.filters.pos_id) {
					const pos = this.$dictionaries.poses?.find(p => p.id == this.filters.pos_id);
					chips.push({ key: 'pos_id', label: pos?.name || this.$t('Point of sale'), removable: false });
				}

				return chips;
			},
			month: {
				get() {
					if ( !this.filters?.date_range )
						return null;

					const firstDate = this.$dayjs(this.filters?.date_range?.length ? this.filters.date_range[0] : null)

					const result = {
						year: firstDate.year(),
						month: firstDate.month(),
					}

					return result;
				},
				set(new_val) {
					if (!this.options.month)
						return false;

					if (!new_val)
						return this.filters.date_range=null

					const start = this.$dayjs().year(new_val?.year).month(new_val?.month).date(1)
					const end = start.endOf('month')

					this.filters.date_range = [
						start.format('YYYY-MM-DD'),
						end.format('YYYY-MM-DD'),
					]
				}
			}
		},
		created() {
			// Сторінка не має вантажити дані до того, як фільтри отримають значення.
			if ( this.filters_gate )
				this.filters_gate.registered = true;
		},
		mounted() {
			// console.log('[Filters.mount]');
			this.applySilently(() => {
				this.setStartFilters();
				if (!this.no_persist)
					this.restoreFilters();
				this.resolveDictFilters();
			});

			this.takeSnapshot();
			this.armReadyGate();
		},
		unmounted() {
			clearTimeout(this.ready_timer);
		},
		methods: {
			// Програмна установка значень: контроли на неї відповідають тими самими
			// подіями, що й на дію користувача, — під час установки їх не слухаємо.
			applySilently(apply) {
				this.is_silent = true;
				try {
					apply();
				} finally {
					this.is_silent = false;
				}
			},

			// Повторний запит даних сторінки з тими самими фільтрами: значення не чіпаємо,
			// тому й знімок стану лишається як був.
			refresh() {
				this.$emit('onChange', 'refresh');
			},

			getShowClass(filter_name) {
				const opts = this.options[filter_name];

				if (!opts || typeof opts !== 'object' || !opts.showon)
					return '';

				const map = {
					'xs':     'lt-xs:hidden',
					'sm':     'lt-sm:hidden',
					'md':     'lt-md:hidden',
					'lg':     'lt-lg:hidden',
					'xl':     'lt-xl:hidden',
					'2xl':    'lt-2xl:hidden',
					'lt-xs':  'xs:hidden',
					'lt-sm':  'sm:hidden',
					'lt-md':  'md:hidden',
					'lt-lg':  'lg:hidden',
					'lt-xl':  'xl:hidden',
					'lt-2xl': '2xl:hidden',
				};

				return map[opts.showon] || '';
			},

			// --- Реєстр: параметри контрола за дескриптором ---------------------------

			isMandatory(def) {
				return !def.always_all && !!this.options[def.name]?.mandatory;
			},
			hasAllOption(def) {
				return def.always_all || !this.options[def.name]?.mandatory;
			},
			placeholderFor(def) {
				const key = this.isMandatory(def) ? def.placeholder : (def.all_placeholder || def.placeholder);

				return key ? this.$t(key) : '';
			},
			listFor(def) {
				if ( def.source )
					return this[def.source];

				return this.$dictionaries[def.dict] || [];
			},
			// Звуження переліку в самому контролі (типи документів фільтрує сторінка).
			listFilterFor(def) {
				if ( def.name === 'doctypes' )
					return this.options.doctypes?.filter || {};

				const filter = {};

				if ( def.active_only )
					filter.is_deleted = false;

				// Без вибраної точки каси не звужуємо: інакше на сторінках з «усі точки»
				// перелік кас порожній і вибрати нічого.
				if ( def.scope_pos && this.filters.pos_id )
					filter.pos_id = this.filters.pos_id;

				return filter;
			},

			// --- Резолв значень по довідниках ----------------------------------------

			// Рядки довідника, доступні цьому фільтру зараз (з урахуванням точки).
			availableItems(def) {
				const dict = this.$dictionaries[def.dict] || [];

				return dict.filter(item => {
					if ( def.active_only && item.is_deleted )
						return false;
					if ( def.scope_pos && this.filters.pos_id && item.pos_id != this.filters.pos_id )
						return false;

					return true;
				});
			},

			// Єдине місце, де вирішується значення фільтра-довідника:
			// вибране й дійсне лишається → мертве (видалене/чуже) прибирається →
			// обовʼязковий фільтр з єдиним доступним варіантом підставляє його.
			// Повертає перелік ключів, значення яких змінились.
			resolveDictFilters() {
				const changed = [];

				this.dict_filters.forEach(def => {
					const dict = this.$dictionaries[def.dict] || [];

					// Довідник ще не приїхав — збережене значення не чіпаємо.
					if ( !dict.length )
						return;

					const available = this.availableItems(def);
					const current   = this.filters[def.model];
					const is_valid  = current !== '' && current != null
						&& available.some(item => item.id == current);

					if ( is_valid )
						return;

					if ( current !== '' && current != null ) {
						this.filters[def.model] = '';
						delete this.auto_filled[def.model];
						changed.push(def.model);
					}

					if ( this.isMandatory(def) && available.length === 1 ) {
						this.filters[def.model]    = available[0].id;
						this.auto_filled[def.model] = true;
						changed.push(def.model);
					}
				});

				return changed;
			},

			// Довідники змінились (приїхали, оновились, змінився акаунт).
			onDictionariesChange() {
				const changed = this.resolveDictFilters();

				// До готовності первинне завантаження замовляє затвор — окремої події не треба.
				// Страхувальний таймер при цьому не подовжуємо, інакше потік оновлень
				// довідників відкладав би завантаження нескінченно.
				if ( !this.is_ready ) {
					this.takeSnapshot();
					if ( this.dictionaries_ready )
						this.markReady();
					return;
				}

				if ( !changed.length )
					return;

				this.cacheFilters();
				this.clearEmptyFilters();
				this.takeSnapshot();
				this.$emit('onChange', 'dictionaries');
			},

			// --- Затвор готовності ----------------------------------------------------

			armReadyGate() {
				if ( this.is_ready )
					return;

				if ( this.dictionaries_ready )
					return this.markReady();

				clearTimeout(this.ready_timer);
				this.ready_timer = setTimeout(() => this.markReady(), DICTIONARIES_WAIT);
			},
			markReady() {
				if ( this.is_ready )
					return;

				clearTimeout(this.ready_timer);
				this.is_ready = true;

				if ( this.filters_gate )
					this.filters_gate.ready = true;

				this.$emit('ready');
			},

			// --- Стан фільтрів ---------------------------------------------------------

			// Відбиток значущих значень: за ним відрізняємо справжню зміну від
			// повторної події контрола з тим самим значенням.
			filtersFingerprint() {
				const significant = {};

				Object.keys(this.filters)
					.sort()
					.forEach(key => {
						const value = this.filters[key];
						if ( value === '' || value === null || value === undefined )
							return;
						significant[key] = value;
					});

				return JSON.stringify(significant);
			},
			// Запамʼятати поточний стан; true — стан відрізняється від попереднього.
			takeSnapshot() {
				const fingerprint = this.filtersFingerprint();
				const changed     = fingerprint !== this.filters_snapshot;

				this.filters_snapshot = fingerprint;

				return changed;
			},

			setStartFilters() {
				// console.log('Filters.setStartFilters');

				// Default values
				if ( !this.filters.pos_id && this.options.cashboxes?.mandatory )
					this.filters.pos_id='';

				if ( !this.filters.cashbox_id && this.options.cashboxes?.mandatory )
					this.filters.cashbox_id='';

				if ( this.options.year && !this.filters.year )
					this.filters.year = this.$dayjs().year();

				this.setCurrentDate();
				this.previousDocDate = this.filters.date
			},

			restoreFilters() {
				// console.log('[Filters.restoreFilters]', this.storage_key);

				// 1) Global: pos_id (shared across all pages for the account)
				const global = this.$settings.getGlobalState(this.accountId);
				if ( global.pos_id && this.options.poses )
					this.filters.pos_id = global.pos_id;

				// 2) Backward-compat: legacy `filters` cache (pre page-state era)
				if ( !this.filters.pos_id && this.options.poses ) {
					const legacy = this.$settings.getSetting('filters') || {};
					const legacy_account = legacy[this.accountId] || {};
					if ( legacy_account.pos_id )
						this.filters.pos_id = legacy_account.pos_id;
				}

				// 3) Per-page: cashbox_id / cash_account_id (needs storage_key)
				if ( !this.storage_key ) return false;

				const page = this.$settings.getPageState(this.storage_key, this.accountId);

				const saved_cashbox = page.filters?.cashbox_id;
				if ( saved_cashbox && this.options.cashboxes )
					this.filters.cashbox_id = saved_cashbox;

				const saved_cash_account = page.filters?.cash_account_id;
				if ( saved_cash_account && this.options.cash_account?.persistent )
					this.filters.cash_account_id = saved_cash_account;

				return true;
			},

			// force_update — оновити дані навіть якщо значення не змінилось: скидання
			// дати хрестиком повертає її на сьогодні, і саме сьогодні там і могло стояти,
			// а користувач очікує від хрестика свіжі дані.
			changeFilter(filter_name, force_update = false) {
				// console.log('[Filters.changeFilter]', filter_name);

				if (this.is_silent) return;

				// Вибране вручну більше не автопідстановка — його можна запамʼятовувати.
				const def = FILTER_DEFS.find(item => item.name === filter_name);
				if ( def?.model )
					delete this.auto_filled[def.model];

				// Каскад: зміна точки може знецінити вибрану касу.
				this.resolveDictFilters();

				if ( !this.takeSnapshot() && !force_update )
					return;

				this.cacheFilters()
				this.clearEmptyFilters();

				if ( this.options[filter_name]?.updateonchange )
					this.$emit('onChange', filter_name);
			},

			cacheFilters() {
				// console.log('[Filters.cacheFilters]', this.storage_key, 'no_persist:', this.no_persist);

				if (this.no_persist)
					return;

				// Global slice — pos_id only (shared across pages)
				if ( this.options.poses )
					this.$settings.mergeGlobalState(this.accountId, {
						pos_id: this.rememberableValue('pos_id'),
					});

				// Per-page slice — cashbox_id / cash_account_id (persistent)
				if ( !this.storage_key ) return;

				const page_filters = {};
				if ( this.options.cashboxes )
					page_filters.cashbox_id = this.rememberableValue('cashbox_id');
				if ( this.options.cash_account?.persistent )
					page_filters.cash_account_id = this.rememberableValue('cash_account_id');

				if ( Object.keys(page_filters).length )
					this.$settings.mergePageState(this.storage_key, this.accountId, {
						filters: page_filters,
					});
			},

			// Автопідставлене значення не запамʼятовується: інакше воно виглядало б як
			// вибір користувача й підмінило б «усі» на сторінках з необовʼязковим фільтром.
			rememberableValue(model) {
				if ( this.auto_filled[model] )
					return null;

				return this.filters[model] || null;
			},

			clearEmptyFilters() {
				Object.keys(this.filters).forEach(key => {
					const v = this.filters[key]
					// console.log('filter', key, v);
					if (v === '' || v === null || v === undefined)
						delete this.filters[key]
				})
			},

			currentDate() {
				return this.$dayjs().format('YYYY-MM-DD');
			},
			setCurrentDate() {
				// console.log('[Filters.setCurrentDate]', this.$dayjs().format('YYYY-MM-DD'));
				const now = this.$dayjs();

				// Початкова дата діапазону за замовчуванням: сьогодні, або перше число
				// поточного місяця, якщо date_range має default_period: 'month'.
				let range_start = now;
				if ( this.options.date_range?.default_period === 'month' )
					range_start = now.startOf('month');

				this.filters.date    = now.format('YYYY-MM-DD');
				this.filters.date_range = [range_start.format('YYYY-MM-DD'), now.format('YYYY-MM-DD')];

				if (this.options.month)
					this.month = {
						year: now.year(),
						month: now.month(), // от 0 до 11
					}

				// Період за замовчуванням — поточний місяць цілком (його можна розширити).
				if (this.options.period)
					this.filters.date_range = this.periodRange(now.startOf('month'), 'month');

				// Комбінований фільтр рік-квартал: за замовчуванням поточний квартал.
				if (this.options.year_quarter) {
					if (!this.filters.year)
						this.filters.year = now.year();
					if (!this.filters.quarter)
						this.filters.quarter = Math.floor(now.month() / 3) + 1;

					this.computeQuarterRange();
				}
			},
			handleDateChange(new_val, filter_name) {
				// console.log('handleDateChange', new_val, this.previousDocDate);
				// console.log('old_val', this.filters.date);
				if (new_val === null) {
					this.$nextTick(() => {
						this.setCurrentDate()
						this.previousDocDate = this.filters.date
						this.changeFilter(filter_name, true);
					})
				} else {
					this.changeFilter(filter_name)
					this.previousDocDate = this.filters.date = new_val
				}
			},
			handleYearChange(new_val) {
				// Рік не може бути порожнім — при очищенні (хрестик) повертаємо поточний.
				if ( !new_val ) {
					this.$nextTick(() => {
						this.filters.year = this.$dayjs().year();
						this.changeFilter('year', true);
					});
					return;
				}

				this.changeFilter('year');
			},
			// Перерахунок date_range з обраних року та кварталу й проброс зміни нагору.
			changeYearQuarter() {
				this.computeQuarterRange();
				this.changeFilter('year_quarter');
			},
			computeQuarterRange() {
				const now        = this.$dayjs();
				const year       = Number(this.filters.year) || now.year();
				const quarter    = Number(this.filters.quarter) || (Math.floor(now.month() / 3) + 1);
				const startMonth = (quarter - 1) * 3; // 0, 3, 6, 9

				const start = now.year(year).month(startMonth).date(1).startOf('month');
				const end   = start.add(2, 'month').endOf('month');

				this.filters.date_range = [start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD')];
			},
			handleDateRangeChange(new_val, filter_name) {
				// console.log('handleDateChange', new_val);
				if (new_val === null) {
					this.$nextTick(() => {
						this.setCurrentDate()
						this.$nextTick(() => {
							this.setCurrentDate()
							// this.$emit('changeDateRange')
						});
						this.changeFilter(filter_name, true);
					})
				} else
					this.changeFilter(filter_name)
			},

			// --- Період -------------------------------------------------------------------

			// Межі цілого місяця / кварталу / року, що починається з вказаної дати.
			periodRange(start, kind) {
				const end = start.add(PERIOD_MONTHS[kind] - 1, 'month').endOf('month');

				return [start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD')];
			},
			// Спершу на перше число: інакше 31 травня при переході на квітень переповзе в травень.
			quarterStart(date) {
				const month_start = date.startOf('month');

				return month_start.month(Math.floor(month_start.month() / 3) * 3);
			},
			// Який цілий період вибрано: від нього залежать підпис у полі й крок стрілок.
			periodKind(range) {
				const [from, to] = range || [];
				if ( !from || !to )
					return null;

				const start = this.$dayjs(from);
				if ( start.date() !== 1 )
					return 'custom';

				if ( this.periodRange(start, 'month')[1] === to )
					return 'month';
				if ( start.month() % 3 === 0 && this.periodRange(start, 'quarter')[1] === to )
					return 'quarter';
				if ( start.month() === 0 && this.periodRange(start, 'year')[1] === to )
					return 'year';

				return 'custom';
			},
			isActivePeriod(value) {
				const range = this.filters.date_range || [];

				return range[0] === value[0] && range[1] === value[1];
			},
			// Цілий період зсувається на сусідній такий самий, довільний — на свою довжину.
			shiftPeriod(direction) {
				const range = this.filters.date_range;
				const kind  = this.periodKind(range);
				if ( !kind )
					return;

				const start = this.$dayjs(range[0]);

				if ( kind === 'custom' ) {
					const end  = this.$dayjs(range[1]);
					const days = (end.diff(start, 'day') + 1) * direction;

					this.filters.date_range = [
						start.add(days, 'day').format('YYYY-MM-DD'),
						end.add(days, 'day').format('YYYY-MM-DD'),
					];
				} else
					this.filters.date_range = this.periodRange(start.add(PERIOD_MONTHS[kind] * direction, 'month'), kind);

				this.changeFilter('period');
			},
			// Цілий період підписується словами («Вересень 2026», «Квартал 3, 2026», «2026»),
			// довільний — датами, рік на початку опускається, якщо він той самий.
			formatPeriod(dates) {
				const [from, to] = (dates || []).filter(Boolean).map(date => this.$dayjs(date));
				if ( !from )
					return '';

				const range = [from.format('YYYY-MM-DD'), (to || from).format('YYYY-MM-DD')];

				switch ( this.periodKind(range) ) {
					case 'month':
						return `${this.monthOptions[from.month()].name} ${from.year()}`;
					case 'quarter':
						return `${this.$t('Quarter')} ${Math.floor(from.month() / 3) + 1}, ${from.year()}`;
					case 'year':
						return String(from.year());
				}

				if ( !to || from.isSame(to, 'day') )
					return from.format('DD.MM.YYYY');

				return `${from.format(from.isSame(to, 'year') ? 'DD.MM' : 'DD.MM.YYYY')} – ${to.format('DD.MM.YYYY')}`;
			},
			handlePeriodChange(new_val) {
				// Період не буває порожнім: хрестик повертає поточний місяць.
				if ( !new_val?.[0] ) {
					this.$nextTick(() => {
						this.filters.date_range = this.periodRange(this.$dayjs().startOf('month'), 'month');
						this.changeFilter('period', true);
					});
					return;
				}

				// Календар закрили після першої дати — це період в один день.
				if ( !new_val[1] )
					this.filters.date_range = [new_val[0], new_val[0]];

				this.changeFilter('period');
			},
			// Видима область меню періоду: екран, звужений предками, що обрізають вміст, —
			// зокрема областю сторінки, поруч з якою стоїть бічне меню.
			periodMenuBounds(input) {
				let left  = 0;
				let right = document.documentElement.clientWidth;

				for ( let node = input.parentElement; node && node !== document.body; node = node.parentElement ) {
					if ( getComputedStyle(node).overflowX === 'visible' )
						continue;

					const rect = node.getBoundingClientRect();
					left  = Math.max(left, rect.left);
					right = Math.min(right, rect.right);
				}

				return { left: left + PERIOD_MENU_MARGIN, right: right - PERIOD_MENU_MARGIN };
			},
			// Пакет вирівнює меню лише по краю екрана й не знає про бічне меню, під яким
			// воно ховалося. Зсув у видиму область рахується відсотками від ширини самого
			// меню, тож він правильний, ще до того як меню намальоване.
			placePeriodMenu(input) {
				const rect   = input.getBoundingClientRect();
				const bounds = this.periodMenuBounds(input);
				const min    = bounds.left - rect.left;
				const max    = bounds.right - rect.left;
				// На телефоні поле стоїть між стрілками — меню по центру видимої області.
				const wanted = this.is_mobile ? `calc(${(min + max) / 2}px - 50%)` : '0px';

				this.period_menu_narrow = bounds.right - bounds.left < PERIOD_TWO_CALENDARS_WIDTH;

				return {
					top:       `${rect.height + 10}px`,
					left:      '0px',
					transform: `translateX(max(${min}px, min(${wanted}, calc(${max}px - 100%))))`,
				};
			},
			// Пресет із блоку телефона — те саме, що вибір пресета в самому календарі.
			applyPeriodPreset(value) {
				this.filters.date_range = value;
				this.handlePeriodChange(value);

				// Поле стоїть у переліку фільтрів, тому посилання на нього — масив.
				[].concat(this.$refs.period_picker || [])[0]?.closeMenu();
			},

			removeChip(key) {
				if (key === 'product_id') {
					delete this.filters.product_id;
					this.changeFilter('product');
				} else if (key === 'cashbox_id') {
					delete this.filters.cashbox_id;
					this.changeFilter('cashboxes');
				} else if (key === 'pos_id') {
					delete this.filters.pos_id;
					this.changeFilter('poses');
				} else if (key === 'counterparty_id') {
					delete this.filters.counterparty_id;
					this.changeFilter('counterparties');
				} else if (key === 'cashier_id') {
					delete this.filters.cashier_id;
					this.changeFilter('cashiers');
				}
			},

			resetAllFilters() {
				const optional = ['product_id', 'cashbox_id', 'pos_id',
								  'counterparty_id', 'cashier_id', 'doctype_id', 'paytype_id'];
				optional.forEach(key => {
					if (key in this.filters) delete this.filters[key];
				});
				this.setCurrentDate();
				this.resolveDictFilters();
				this.takeSnapshot();
				this.$emit('onChange', 'reset');
			},

			// filterChanges() {
			// 	let result = {};

			// 	for (const key in this.filters) {
			// 		if (String(this.filters_snapshot[key]) !== String(this.filters[key]))
			// 			result[key] = {
			// 				old_val: String(this.filters_snapshot[key]),
			// 				new_val: String(this.filters[key])
			// 			}
			// 	}

			// 	return result;
			// },

			// saveFilters: function() {
			// 	// console.log('saveFilters');
            //     let cache = JSON.parse(localStorage.getItem('filters')) || {};
            //     let filters = {
            //         'pos_id'    : this.filters.pos_id     ? this.filters.pos_id     : null,
            //         'cashbox_id': this.filters.cashbox_id ? this.filters.cashbox_id : null,
            //     }

            //     cache[this.$page.props.account] = filters;

            //     localStorage.setItem('filters', JSON.stringify(cache));
			// },
		}
	}
</script>

<style>
	.date-picker {
		min-width: 15rem;
	}
</style>

<style lang="scss" scoped>
	.filter-inputs {
		gap: 0.4rem 0.75rem;

		@media (max-width: 767px) {
			&.is-collapsed {
				display: none !important;
			}
		}
	}
	.filter-item {
		min-width: 100px;
	}
	// Меню періоду: пресети колонкою ліворуч від календаря.
	.period-filter {
		:deep(.dp--preset-dates) {
			display: grid;
			gap: 0.5rem;
			align-content: start;
			min-width: 10.5rem;
			padding: 0.5rem 1rem 0.5rem 0.5rem;
			margin-right: 0.5rem;
		}

		// Меню зсувається у видиму область, і стрілка вказувала б повз поле.
		:deep(.dp__arrow_top),
		:deep(.dp__arrow_bottom) {
			display: none;
		}

		// На телефоні колонка збоку робить меню ширшим за екран: пресети — згорнутим
		// блоком під календарем, календар — на всю доступну ширину.
		@media (max-width: 767px) {
			:deep(.dp__menu) {
				width: 20rem;
				max-width: calc(100vw - 2rem);
			}

			// Бічна вставка в розмітці пакета стоїть перед календарем.
			:deep(.dp__menu_content_wrapper) {
				flex-direction: column-reverse;
			}

			:deep(.dp__sidebar_left) {
				padding: 0.5rem 0.25rem 0.25rem;
				margin-top: 0.25rem;
				border: 0;
				border-top: 1px solid var(--dp-border-color);
			}

			// Колонка дня тут ширша за сам день, і заливка діапазону рвалася б
			// проміжками: день займає всю колонку — смуга суцільна, як на десктопі.
			:deep(.dp__calendar_item) {
				flex: 1 1 0;
				min-width: 0;
			}

			:deep(.dp__cell_inner) {
				width: 100%;
			}		}
	}
</style>
