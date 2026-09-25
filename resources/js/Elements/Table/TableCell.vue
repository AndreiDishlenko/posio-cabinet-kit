<template>
	<div class="table-cell row-cell"
		:class="[
			is_bottom_left && 'bottom-left',
			column.align ? 'justify-' + column.align : '',
			(column.type == 'checkbox' || column.type == 'checkicon' || column.type == 'dot') && '!justify-center',
			column.type == 'indicator' && 'indicator-cell !justify-center',
			column.type == 'button' && column.icon && '!justify-center',
			column.hide && column.hide + '-hidden',
			column.nowrap && 'whitespace-nowrap',
			row.is_deleted && 'is-deleted',
			cell_value_class,
		]"
		:field="column.field"
		>
		<!-- Inline-додавання: назва нового запису вводиться прямо в рядку, поруч —
		     підтвердити / скасувати. Порожнє значення підтвердити не можна. -->
		<template v-if="inline_edit">
			<input ref="inline_input"
				class="form-control form-control-sm grow min-w-0"
				v-model="row[column.field]"
				:placeholder="column.title ? $t(column.title) : ''"
				@click.stop
				@keydown.enter.prevent="$emit('inline-confirm')"
				@keydown.esc.prevent="$emit('inline-cancel')"
				/>
			<Icon icon="mdi:check"
				class="icon icon-md ms-1 shrink-0 cursor-pointer text-success"
				:class="{ disabled: !inline_value_filled }"
				:title="$t('Save')"
				@click.stop="$emit('inline-confirm')"
				/>
			<Icon icon="mdi:close"
				class="icon icon-md ms-1 shrink-0 cursor-pointer text-secondary"
				:title="$t('Cancel')"
				@click.stop="$emit('inline-cancel')"
				/>
		</template>

		<!-- String -->
		<template v-else-if="column.type == 'string' || !column.type">
			<!-- Клітинка-розшифровка: коли задано column.drill і рядок має прапорець
			     (row[column.drill.flag]) — саме число стає підкресленим посиланням
			     (target=_blank → нова вкладка). Нулі теж (рядок '0' truthy), порожній '' —
			     звичайний текст. Інакше — plain-текст без посилання. -->
			<a v-if="column.drill && row[column.drill.flag] && row[column.field]"
				class="cell-drill-link"
				:class="text_flow_class"
				:href="column.drill.href(row, column)"
				target="_blank"
				:title="$t('Show breakdown')"
				@click.stop
				>{{ string_value }}</a>
			<!-- Іконка перед назвою, коли колонка задає поле-джерело іконки (наприклад,
			     значок категорії, обраний користувачем у картці) -->
			<template v-else-if="column.icon_field">
				<Icon :icon="row[column.icon_field] || column.icon_default || 'mdi:shape-outline'" class="icon icon-sm me-1.5 shrink-0" />
				<span :class="text_flow_class || 'truncate'">{{ string_value }}</span>
			</template>
			<span v-else-if="text_flow_class" :class="text_flow_class">{{ string_value }}</span>
			<template v-else>{{ string_value }}</template>
		</template>

		<!-- Main label + secondary sub-text (менший приглушений підпис під основним).
		     Значення підпису — з поля column.subfield того ж рядка. -->
		<template v-else-if="column.type == 'subtext'">
			<div class="cell-subtext !py-4">
				<span class="cell-subtext-main" :class="text_flow_class">{{ string_value }}</span>
				<span v-if="column.subfield && row[column.subfield]" class="cell-subtext-sub !text-xs disabled">
					{{ column.translate ? $t(row[column.subfield]) : row[column.subfield] }}
				</span>
			</div>
		</template>

		<!-- Прев'ю знімка. Порожній рядок теж займає плитку-заглушку, інакше
		     колонка стрибала б по ширині від рядка до рядка. -->
		<template v-else-if="column.type == 'image'">
			<span class="cell-image" :class="image_size_class">
				<img v-if="image_source" :src="image_source" :alt="image_alt" loading="lazy">
				<Icon v-else :icon="column.icon || 'ph:image'" class="icon icon-md text-secondary opacity-40" />
			</span>
		</template>

		<!-- Number -->
		<template v-else-if="column.type == 'number'">
			{{ row[column.field] == 0 ? (column.hide_zero ? '' : '0' + (column.suffix ?? '')) : Number(row[column.field]).toFixed(column.precision ?? 0) + (column.suffix ?? '') }}
		</template>

		<!-- Currency -->
		<template v-else-if="column.type == 'currency'">
			{{ Number(row[column.field] == 0 ? 0 : row[column.field]).toFixed(2) }} ₴
		</template>

		<!-- Date -->
		<template v-else-if="column.type == 'date'">
			{{ row[column.field] ? this.$dayjs(row[column.field]).format('DD.MM.YYYY') : '' }}
		</template>

		<!-- Time -->
		<template v-else-if="column.type == 'time'">
			{{ row[column.field] ? this.$dayjs(row[column.field]).format('HH:mm:ss') : '' }}
		</template>

		<!-- Select -->
		<template v-else-if="column.type == 'select' && select_sources[column.field] && select_sources[column.field][row[column.field] ?? column.default]?.name">
			{{ $t(select_sources[column.field][row[column.field] ?? column.default]?.name) }}
		</template>

		<!-- Checkbox -->
		<template v-else-if="column.type == 'checkbox'">
			<input type="checkbox" class="form-control !inline-block"
				:checked="!!row[column.field]"
				disabled
				>
		</template>

		<!-- Checkbox rendered as an icon (compact, e.g. mobile): coloured when truthy, faded when falsy -->
		<template v-else-if="column.type == 'checkicon'">
			<Icon :icon="column.icon" class="icon icon-md" :class="row[column.field] ? 'text-success' : 'opacity-20'" />
		</template>

		<!-- Точка стану замість чекбокса: колір і підпис беруть із мап колонки
		     (labels/classes по ключах on/off) або з дефолтів «увімкнено/вимкнено». -->
		<template v-else-if="column.type == 'dot'">
			<StatusDot :active="dot_on" :state="dot_state" :label="dot_label" :pulse="!!column.pulse" />
		</template>

		<!-- Indicator -->
		<template v-else-if="column.type == 'indicator'">
			<div class="status-indicator" :class="row[column.field]"></div>
		</template>

		<!-- Status icon (icon + colour driven by the cell's string value) -->
		<template v-else-if="column.type == 'icon'">
			<Icon v-if="row[column.field] && column.icons?.[row[column.field]]"
				:icon="column.icons[row[column.field]]"
				class="icon icon-md"
				:class="column.classes?.[row[column.field]]"
				:title="column.labels?.[row[column.field]] ? $t(column.labels[row[column.field]]) : null"
				/>
		</template>

		<!-- Edit -->
		<template v-else-if="column.type == 'edit'">
			<input
				class="form-control form-control-sm text-center"
				:class="{ 'input-error': edit_invalid }"
				v-model="row[column.field]"
				@click.stop
				@mousedown.stop
				@focus="startEdit($event)"
				@input="column.onInput ? column.onInput(row) : null"
				@change="commitEdit()"
				@blur="holdInvalidEdit($event)"
				@keydown.esc.prevent="cancelEdit($event)"
				/>
		</template>

		<!-- Button -->
		<template v-else-if="column.type == 'button'">
			<template v-if="column.getter ? column.getter(row) : row[column.field]">
				<Icon v-if="column.icon"
					:icon="column.icon"
					class="icon icon-md cursor-pointer text-secondary"
					@click.stop="column.onClick ? column.onClick(row) : null"
					/>
				<button v-else
					class="button button-xs outline-button"
					type="button"
					@click.stop="column.onClick ? column.onClick(row) : null"
					>{{ column.label ?? $t('Copy') }}</button>
			</template>
		</template>
	</div>
</template>

<script>
	import { Icon } from '@iconify/vue';

	import StatusDot from '@/js/Elements/StatusDot.vue';

	export default {
		name: 'TableCell',
		components: { Icon, StatusDot },
		props: {
			column: {
				type: Object,
				required: true,
			},
			row: {
				type: Object,
				required: true,
			},
			select_sources: {
				type: Object,
				default: () => ({}),
			},
			is_bottom_left: {
				type: Boolean,
				default: false,
			},
			// Комірка приймає ввід назви щойно доданого запису (inline-режим додавання).
			inline_edit: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['inline-confirm', 'inline-cancel'],
		data() {
			return {
				// Значення комірки на момент входу в редагування (type:'edit') —
				// точка відкату для Esc.
				editBaseline: null,
				// Введене значення не пройшло перевірку колонки: з комірки не випускаємо,
				// доки його не виправлять або не скасують по Esc.
				edit_invalid: false,
				// Помилку щойно показали при збереженні — при виході з комірки не дублюємо.
				edit_error_shown: false,
			};
		},
		computed: {
			// Оформлення комірки від даних рядка (напр. колір суми за знаком) — задає колонка.
			cell_value_class() {
				const cell_class = this.column.cell_class;

				return typeof cell_class === 'function' ? cell_class(this.row) : (cell_class ?? '');
			},
			inline_value_filled() {
				return !!String(this.row[this.column.field] ?? '').trim();
			},
			// Текстове значення комірки: словникові значення (англ. ключі) проганяються
			// через переклад, користувацькі — ні.
			string_value() {
				const value = this.row[this.column.field] ?? this.column.default;

				return this.column.translate ? this.$t(value ?? '') : value;
			},
			// Розкладка тексту в комірці: типово текст в один рядок і обрізається краєм
			// комірки. Колонка може ввімкнути перенос (без обмеження) або задати ліміт
			// рядків — після останнього ставиться трикрапка.
			text_flow_class() {
				const lines = Number(this.column.lines) || 0;

				if ( lines > 0 )
					return 'cell-multiline cell-lines-' + Math.min(lines, 5);

				return this.column.wrap ? 'cell-multiline' : '';
			},
			// Пресет розміру плитки прев'ю; без нього розмір задає таблиця (у режимі
			// високих рядків прев'ю більше, ніж у звичайному списку).
			image_size_class() {
				const size = this.column.image_size;

				return ['sm', 'md', 'lg', 'xl'].includes(size) ? 'cell-image-' + size : '';
			},
			// Посилання на знімок: або готове значення поля, або обчислене колонкою —
			// знімок зазвичай лежить у вкладеній структурі рядка, а не окремим полем.
			image_source() {
				const value = typeof this.column.getter === 'function'
					? this.column.getter(this.row)
					: this.row[this.column.field];

				return typeof value === 'string' ? value : '';
			},
			image_alt() {
				return this.column.alt_field ? (this.row[this.column.alt_field] ?? '') : '';
			},
			// Значення прапорця комірки: рядок '0' з бекенда — теж «вимкнено».
			dot_on() {
				const value = typeof this.column.getter === 'function'
					? this.column.getter(this.row)
					: this.row[this.column.field];

				return !!value && value !== '0';
			},
			dot_state() {
				if ( !this.column.classes )
					return '';

				return (this.dot_on ? this.column.classes.on : this.column.classes.off) ?? '';
			},
			// Підпис — або окремий на кожен стан, або спільний (стан тоді читається кольором).
			dot_label() {
				if ( this.column.labels )
					return (this.dot_on ? this.column.labels.on : this.column.labels.off) ?? '';

				return this.column.label ?? '';
			},
		},
		watch: {
			inline_edit: {
				immediate: true,
				handler(active) {
					if ( active )
						this.$nextTick(() => this.$refs.inline_input?.focus());
				},
			},
		},
		methods: {
			// Старе значення виділяється цілком: набране замінює його, а не дописується
			// в кінець (так «1350» перетворювалось на «13501350»).
			startEdit(event) {
				// Повернення фокусу після помилки — те саме редагування: точка відкату
				// для Esc лишається початковою, а не хибним значенням.
				if ( !this.edit_invalid )
					this.editBaseline = this.row[this.column.field];

				event.target.select();
			},
			editError() {
				return this.column.validate ? this.column.validate(this.row[this.column.field], this.row) : null;
			},
			// Колонка може перевірити значення до збереження: з помилкою запит не
			// відправляється, а введене лишається в комірці для виправлення.
			commitEdit() {
				const error = this.editError();
				if ( error ) {
					this.edit_invalid = true;
					this.edit_error_shown = true;
					this.$toast.error(this.$t(error));
					return;
				}

				this.edit_invalid = false;
				this.row.blocked = true;
				this.column.onUpdate(this.row);
			},
			// Вийти з комірки з хибним значенням не можна — фокус повертається назад.
			holdInvalidEdit(event) {
				if ( !this.edit_invalid )
					return;

				if ( !this.edit_error_shown )
					this.$toast.error(this.$t(this.editError()));

				this.edit_error_shown = false;

				const input = event.target;
				setTimeout(() => input.focus());
			},
			// Скасувати редагування комірки по Esc: повернути значення, яке було на
			// вході в комірку (editBaseline), і зняти фокус. Значення повертається до
			// фокусного (і в row, і в DOM), тож подія change не спрацьовує — onUpdate
			// (збереження/перерахунок) не викликається.
			cancelEdit(event) {
				const input = event.target;
				this.edit_invalid = false;
				this.edit_error_shown = false;
				this.row[this.column.field] = this.editBaseline;
				input.value = this.editBaseline ?? '';
				input.blur();
			},
		},
	}
</script>

<style lang="scss" scoped>
	// Тип 'subtext': основний підпис + менший приглушений підпис під ним.
	.cell-subtext {
		display: flex;
		flex-direction: column;
		justify-content: center;
		min-width: 0;
		line-height: 1.15;
	}

	.cell-subtext-main {
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.cell-subtext-sub {
		font-size: 0.8em;
	}

	// Тип 'image': плитка сталого розміру. Розмір задає таблиця змінною — у режимі
	// високих рядків прев'ю більше, ніж у звичайному списку.
	.cell-image {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex: 0 0 auto;
		width: var(--cell-image-size, 2rem);
		height: var(--cell-image-size, 2rem);
		border-radius: var(--ui-radius-md);
		background-color: var(--muted-tint-15);
		overflow: hidden;
	}

	// Пресети розміру плитки прев'ю (перекривають розмір, заданий таблицею).
	.cell-image.cell-image-sm { width: 1.5rem; height: 1.5rem; }
	.cell-image.cell-image-md { width: 2rem;   height: 2rem;   }
	.cell-image.cell-image-lg { width: 3rem;   height: 3rem;   }
	.cell-image.cell-image-xl { width: 5rem;   height: 5rem;   }

	.cell-image img {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	// Багаторядковий текст комірки. Комірка — flex-контейнер, тож перенос вмикається
	// на самому тексті; без min-width:0 довге слово розпирало б колонку.
	.cell-multiline {
		min-width: 0;
		white-space: normal;
		overflow-wrap: break-word;
		line-height: 1.25;
	}

	// Ліміт рядків: після останнього — трикрапка. Префіксована реалізація потрібна
	// нижній планці підтримуваних браузерів.
	.cell-multiline[class*="cell-lines-"] {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		overflow: hidden;
	}

	.cell-lines-1 { -webkit-line-clamp: 1; }
	.cell-lines-2 { -webkit-line-clamp: 2; }
	.cell-lines-3 { -webkit-line-clamp: 3; }
	.cell-lines-4 { -webkit-line-clamp: 4; }
	.cell-lines-5 { -webkit-line-clamp: 5; }

	// Клітинка-розшифровка: число-посилання. Підкреслення завжди видиме (сигнал «клікабельно»),
	// курсор — pointer навіть коли рядки таблиці мають default-курсор.
	.cell-drill-link {
		color: inherit;
		cursor: pointer;
		text-decoration: underline;
		text-decoration-style: dotted;
		text-underline-offset: 3px;
	}

	.cell-drill-link:hover {
		text-decoration-style: solid;
	}
</style>
