<template>

	<!-- Рядок-ілюстрація під шапкою таблиці: CSS-бари + лінія, вирівняні по колонках.
	     Рендериться як display:contents-рядок (клас .contents), тож кожна клітинка —
	     прямий grid-елемент .t-block і потрапляє точно у свою колонку (як звичайні рядки).
	     Порядок клітинок дублює TableHeader: selector? → slave? → колонки → rowbar?. -->
	<div class="table-row contents chart-row" :style="{ '--chart-row-pad-y': row_pad_y + 'px' }">

		<!-- Порожні клітинки-заповнювачі для ведучих службових колонок -->
		<div v-if="show_selector" class="chart-cell chart-spacer"></div>
		<div v-if="has_slaves"    class="chart-cell chart-spacer"></div>

		<!-- По одній клітинці на кожну видиму колонку таблиці -->
		<template v-for="column in columns" :key="column.field">

			<!-- Колонка-графік (позначена column.chart_field) — бари + сегменти лінії -->
			<div v-if="column.chart_field" class="chart-cell">
				<div class="chart-plot" :style="{ height: plot_height + 'px' }">

					<!-- Базова лінія (нуль) — рецесивна волосяна -->
					<div class="chart-baseline" :style="{ top: baseline_top + 'px' }"></div>

					<!-- Бари (кожна bar-серія): група центрується на спільному центрі клітинки, -->
					<!-- товщина обмежена, заокруглений кінець з боку значення, квадратний — біля базової лінії. -->
					<div v-for="(bar, bi) in cell_map[column.field].bars"
						:key="'bar' + bi"
						class="chart-bar"
						:class="'viz-slot-' + bar.slot"
						:title="bar.title"
						:style="barStyle(bar, bi, cell_map[column.field].bars.length, cell_map[column.field].center)"
						></div>

					<!-- Сегменти лінії: ліва й права половини до сусідів (стик по межі колонки).
					     vector-effect зберігає товщину при нерівномірному масштабі viewBox. -->
					<svg v-if="cell_map[column.field].lines.length"
						class="chart-line"
						:viewBox="'0 0 100 ' + plot_height"
						preserveAspectRatio="none"
						:style="{ transform: 'translateX(' + center_offset + 'px)' }"
						>
						<template v-for="(line, li) in cell_map[column.field].lines" :key="'ln' + li">
							<polyline v-if="line.leftY != null"
								class="chart-line-seg"
								:class="'viz-slot-' + line.slot"
								:points="'0,' + line.leftY + ' ' + cell_map[column.field].anchor + ',' + line.y"
								/>
							<polyline v-if="line.rightY != null"
								class="chart-line-seg"
								:class="'viz-slot-' + line.slot"
								:points="cell_map[column.field].anchor + ',' + line.y + ' 100,' + line.rightY"
								/>
						</template>
					</svg>

					<!-- Точки лінії (div, щоб не спотворювались масштабом viewBox): 8px,
					     на спільному центрі клітинки (як бари), у колір серії. -->
					<div v-for="(line, li) in cell_map[column.field].lines"
						:key="'dot' + li"
						class="chart-dot"
						:class="'viz-slot-' + line.slot"
						:title="line.title"
						:style="{ top: line.y + 'px', left: 'calc(' + cell_map[column.field].anchor + '%' + center_offset_term + ')' }"
						></div>

				</div>
			</div>

			<!-- Колонка-підпис (перша не-графікова) — компактна легенда для ідентичності серій -->
			<div v-else-if="column.field === legend_field" class="chart-cell chart-legend-cell">
				<span v-for="(item, idx) in legend" :key="'lg' + idx" class="chart-legend-item">
					<span class="chart-legend-swatch"
						:class="[ item.type === 'line' ? 'is-line' : 'is-bar', 'viz-slot-' + item.slot ]"
						></span>
					<span class="chart-legend-label">{{ item.label }}</span>
				</span>
			</div>

			<!-- Інша звичайна колонка — порожня клітинка-заповнювач -->
			<div v-else class="chart-cell chart-spacer"></div>

		</template>

		<!-- Порожня клітинка для колонки rowbar -->
		<div v-if="show_rowbar" class="chart-cell chart-spacer"></div>

	</div>

</template>

<script>
	export default {
		name: 'TableChartRow',
		props: {
			// Видимі колонки таблиці (Table.seenColumns) — у тому ж порядку, що й у шапці.
			columns: {
				type: Array,
				default: () => [],
			},
			// Дані таблиці (Table.table_data) — звідси беремо рядки-серії за key_field.
			data: {
				type: Array,
				default: () => [],
			},
			// Дескриптор графіка (settings.chart_row):
			// { height, pad_top, pad_bottom, key_field, bar_width,
			//   series: [{ source, type:'bar'|'line', label?, color? }] }
			config: {
				type: Object,
				default: () => ({}),
			},
			show_selector: {
				type: Boolean,
				default: false,
			},
			has_slaves: {
				type: Boolean,
				default: false,
			},
			show_rowbar: {
				type: Boolean,
				default: false,
			},
		},
		computed: {
			// Повна висота смуги графіка (px), включно з внутрішніми відступами.
			plot_height() {
				return Number(this.config.height) || 60;
			},
			// Зовнішній вертикальний padding рядка-графіка (px зверху й знизу від смуги
			// марок) — щоб рядок «дихав» над/під сусідніми рядками. Data-driven через
			// config.pad_y; дефолт 12.
			row_pad_y() {
				return this.config.pad_y != null ? Number(this.config.pad_y) : 12;
			},
			// Горизонтальний зсув центру марок (px): + праворуч, − ліворуч. Застосовується
			// однаково до барів, точок і лінії, тож взаємне вирівнювання зберігається.
			// Data-driven через config.center_offset; дефолт 0.
			center_offset() {
				return Number(this.config.center_offset) || 0;
			},
			// Готовий доданок для calc() лівої координати марки: ` + Npx` / ` - Npx` / ''.
			center_offset_term() {
				const value = this.center_offset;
				if (!value) return '';
				return value > 0 ? ` + ${value}px` : ` - ${Math.abs(value)}px`;
			},
			// Внутрішні відступи, щоб марки не торкались шапки/сусідніх рядків.
			pad_top() {
				return this.config.pad_top != null ? Number(this.config.pad_top) : 9;
			},
			pad_bottom() {
				return this.config.pad_bottom != null ? Number(this.config.pad_bottom) : 7;
			},
			// Колонки, що входять у графік (позначені chart_field — «сирим» полем місяця).
			chart_columns() {
				return this.columns.filter(column => column.chart_field);
			},
			// Перша не-графікова колонка — приймач компактної легенди.
			legend_field() {
				const column = this.columns.find(c => !c.chart_field);
				return column ? column.field : null;
			},
			// Серії з підвантаженим рядком-джерелом та слотом кольору (за фіксованим порядком).
			series_rows() {
				const key_field = this.config.key_field || 'key';
				return (this.config.series || []).map((series, index) => ({
					...series,
					slot: index + 1, // фіксований порядок кольорів (не циклимо)
					row: this.data.find(row => row[key_field] === series.source) || null,
				}));
			},
			// Легенда: підпис + тип + слот кольору кожної серії.
			legend() {
				return this.series_rows.map(series => ({
					label: series.label || series.source,
					type:  series.type,
					slot:  series.slot,
				}));
			},
			// Спільна шкала Y для барів і лінії (з нулем у діапазоні — прибуток буває відʼємним).
			scale() {
				const values = [0];

				this.chart_columns.forEach(column => {
					this.series_rows.forEach(series => {
						if (!series.row) return;
						const value = Number(series.row[column.chart_field]);
						if (!Number.isNaN(value)) values.push(value);
					});
				});

				let min = Math.min(...values);
				let max = Math.max(...values);
				if (min === max) max = min + 1; // уникаємо ділення на нуль

				return { min, max };
			},
			// Y (px від верху) для нульової базової лінії.
			baseline_top() {
				return this.valueToY(0);
			},
			// Частка ширини клітинки для «крайових» центрів (align start/end): напівширина
			// групи барів + невеликий відступ, щоб марки не торкались краю клітинки.
			edge_frac() {
				const group = Number(this.config.bar_width) || 0.42;
				return Math.min(group / 2 + 0.04, 0.5);
			},
			// Готові дані для рендера кожної графік-колонки: бари + сегменти лінії.
			cell_map() {
				const columns     = this.chart_columns;
				const bar_series  = this.series_rows.filter(series => series.type === 'bar');
				const line_series = this.series_rows.filter(series => series.type === 'line');
				const y0 = this.valueToY(0);

				// Значення кожної лінії по колонках (для обчислення сусідніх точок).
				const line_values = line_series.map(series =>
					columns.map(column => {
						const value = series.row ? Number(series.row[column.chart_field]) : NaN;
						return Number.isNaN(value) ? null : value;
					})
				);

				const map = {};

				columns.forEach((column, ci) => {
					// Спільний центр клітинки (частка ширини) — навколо нього шикуються всі марки.
					const a      = this.centerFrac(column);
					const anchor = a * 100;

					// Бари
					const bars = bar_series.map(series => {
						const value = series.row ? Number(series.row[column.chart_field]) : NaN;
						if (Number.isNaN(value)) return null;

						const yv = this.valueToY(value);
						return {
							slot:     series.slot,
							top:      Math.min(y0, yv),
							height:   Math.abs(y0 - yv),
							negative: value < 0,
							title:    String(Math.round(value)),
						};
					}).filter(Boolean);

					// Сегменти лінії (половини до лівого/правого сусіда — стик по межі колонки).
					// Y на межі — лінійна інтерполяція між сусідніми точками з урахуванням зсуву
					// центру `a`: при a=0.5 — середнє; інакше — зважене, щоб півсегменти сусідніх
					// клітинок збігались по Y на спільній межі навіть при зсуненому центрі.
					const lines = line_series.map((series, si) => {
						const value = line_values[si][ci];
						if (value == null) return null;

						const yc   = this.valueToY(value);
						const prev = ci > 0 ? line_values[si][ci - 1] : null;
						const next = ci < columns.length - 1 ? line_values[si][ci + 1] : null;

						return {
							slot:   series.slot,
							y:      yc,
							leftY:  prev != null ? this.valueToY(prev) * a + yc * (1 - a) : null,
							rightY: next != null ? yc * a + this.valueToY(next) * (1 - a) : null,
							title:  String(Math.round(value)),
						};
					}).filter(Boolean);

					map[column.field] = { bars, lines, center: a, anchor };
				});

				return map;
			},
		},
		methods: {
			// Значення → Y (px від верху) за спільною шкалою, у межах внутрішніх відступів.
			valueToY(value) {
				const { min, max } = this.scale;
				const top    = this.pad_top;
				const bottom = this.plot_height - this.pad_bottom;
				return top + (bottom - top) * (max - value) / (max - min);
			},
			// Спільний центр клітинки (частка ширини 0..1), навколо якого шикуються всі марки
			// (бари, точки, лінія). Пріоритет: config.align (глобальний оверрайд графіка) →
			// column.align. Значення: число [0..1] — пряма частка; 'start'/'end' — крайові
			// (edge_frac); 'center'/'' — 0.5.
			centerFrac(column) {
				const override = this.config.align;
				const align = (override != null && override !== '') ? override : column.align;

				if (typeof align === 'number')
					return Math.min(Math.max(align, 0), 1);

				if (align === 'start')
					return this.edge_frac;

				if (align === 'end')
					return 1 - this.edge_frac;

				return 0.5; // center / '' / інше
			},
			// Inline-стиль бара: вертикаль (top/height) — зі спільної шкали; горизонталь —
			// центрування групи барів на спільному центрі клітинки `center` (частка ширини).
			// Кожен бар зсунутий від центру групи за індексом; товщина обмежена (max-width),
			// 2px-проміжок між барами; заокруглений кінець з боку значення.
			barStyle(bar, index, count, center) {
				const group_frac = Number(this.config.bar_width) || 0.42; // частка ширини клітинки під групу
				const each_frac  = group_frac / count;
				const each_pct   = each_frac * 100;
				const width      = count > 1 ? `calc(${each_pct}% - 2px)` : `${each_pct}%`;
				const radius     = bar.negative ? '0 0 4px 4px' : '4px 4px 0 0';

				// Зсув i-го бара від центру групи (у частках ширини) → абсолютний центр бара.
				const offset     = (index - (count - 1) / 2) * each_frac;
				const bar_center = center + offset;

				return {
					top:          bar.top + 'px',
					height:       Math.max(bar.height, 2) + 'px',
					width,
					maxWidth:     '24px',
					borderRadius: radius,
					left:         `calc(${bar_center * 100}%${this.center_offset_term})`,
					transform:    'translateX(-50%)',
				};
			},
		},
	}
</script>

<style lang="scss" scoped>
	// Валідована категорійна палітра (blue → orange), тема-залежна.
	// Слоти призначаються серіям у фіксованому порядку й не циклляться.
	.chart-row {
		--viz-slot-1: #2a78d6; // сер. 1 (бари) — blue
		--viz-slot-2: #eb6834; // сер. 2 (лінія) — orange
		--viz-slot-3: #008300; // green (запас)
		--viz-slot-4: #4a3aa7; // violet (запас)
	}

	.viz-slot-1 { --c: var(--viz-slot-1); }
	.viz-slot-2 { --c: var(--viz-slot-2); }
	.viz-slot-3 { --c: var(--viz-slot-3); }
	.viz-slot-4 { --c: var(--viz-slot-4); }

	// Клітинка графіка — контейнер для абсолютно позиціонованих барів/лінії.
	// Вертикальний padding (--chart-row-pad-y) додає «повітря» над/під смугою марок;
	// висота рядка = висота смуги (chart-plot) + цей padding зверху й знизу.
	.chart-cell {
		position: relative;
		min-width: 0;
		padding-top: var(--chart-row-pad-y, 0);
		padding-bottom: var(--chart-row-pad-y, 0);
	}

	.chart-plot {
		position: relative;
		width: 100%;
	}

	// Нульова базова лінія — рецесивна волосяна (не перетягує увагу з даних).
	.chart-baseline {
		position: absolute;
		left: 0;
		right: 0;
		height: 1px;
		background: var(--table-border-color);
		opacity: 0.55;
	}

	// Бар: форма й колір; геометрія (top/height/left/width) — інлайном.
	.chart-bar {
		position: absolute;
		min-height: 2px;
		background: var(--c);
	}

	// SVG із сегментами лінії на всю область клітинки.
	.chart-line {
		position: absolute;
		// Сокращённая запись четырёх сторон отбрасывается на Safari 13.1.
		top: 0;
		right: 0;
		bottom: 0;
		left: 0;
		width: 100%;
		height: 100%;
		overflow: visible;
		pointer-events: none;
	}

	.chart-line-seg {
		fill: none;
		stroke: var(--c);
		stroke-width: 2;
		stroke-linecap: round;
		stroke-linejoin: round;
		vector-effect: non-scaling-stroke;
	}

	// Точка лінії — 8px, у колір серії (без обводки). Горизонталь (left) — інлайном,
	// на спільному центрі клітинки.
	.chart-dot {
		position: absolute;
		width: 8px;
		height: 8px;
		border-radius: 50%;
		transform: translate(-50%, -50%);
		background: var(--c);
	}

	// Легенда в колонці-підписі: маленькі свотчі + текст у вторинному чорнилі.
	.chart-legend-cell {
		display: flex;
		flex-direction: column;   // кожен елемент легенди — завжди у своєму ряду
		justify-content: center;
		align-items: flex-start;
		@include flex-gap(4px, column);
		padding-left: 8px;
		padding-right: 8px;
	}

	.chart-legend-item {
		display: inline-flex;
		align-items: center;
		@include flex-gap(6px);
		white-space: nowrap;
	}

	.chart-legend-label {
		font-size: var(--text-xs, 12px);
		color: var(--text-color-secondary);
	}

	.chart-legend-swatch {
		display: inline-block;
		flex: 0 0 auto;

		&.is-bar {
			width: 10px;
			height: 10px;
			border-radius: 2px;
			background: var(--c);
		}

		&.is-line {
			width: 16px;
			height: 2px;
			border-radius: 1px;
			background: var(--c);
		}
	}

	// Тема dark: ті самі відтінки, крок під темну поверхню (валідовано під #1a1a19).
	:global(html.dark) .chart-row {
		--viz-slot-1: #3987e5;
		--viz-slot-2: #d95926;
		--viz-slot-3: #009000;
		--viz-slot-4: #9085e9;
	}
</style>
