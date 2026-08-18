<template>

    <div class="table-tools-panel flex items-center space-x-3 pb-2 !ps-0 mt-1 overflow-x-auto no-scrollbar min-h-max">

		<!-- CTA Button -->
		<div v-if="settings.groupactions?.add || settings.ctabutton?.type === 'button' || (settings.ctabutton?.type === 'SelectButton' && ctabuttonActions.length)"
			class="cta-buttons flex items-center gap-3">

			<button v-if="settings.groupactions?.add" class="t-panel-item button button-sm primary-button" @click="$emit('addRow')">
				{{ $t('Add') }}
			</button>

			<button v-if="settings.ctabutton?.type === 'button'"
				class="t-panel-item button button-sm space-x-1"
				:class="[ settings.ctabutton.class || 'primary-button', { disabled: settings.ctabutton.disabled } ]"
				@click="onCtaButtonClick"
				>
				<Icon v-if="settings.ctabutton.icon" class="icon" :icon="settings.ctabutton.icon" />
				<span>{{ $t(settings.ctabutton.name) }}</span>
			</button>

			<SelectableButton
				v-if="settings.ctabutton?.type === 'SelectButton' && ctabuttonActions.length"
				:actions="ctabuttonActions"
				:size="'sm'"
				:offset="5"
				@click.stop
			/>

		</div>

		<!-- Panel items (left) -->
		<template v-for="(item, idx) in panelItemsList" :key="'p-'+idx">

			<SearchableInput
				v-if="item.type === 'search'"
				:class="['shrinkable max-w-xs w-full min-w-[80px]', item.class]"
				:model-value="item.model"
				:placeholder="item.placeholder"
				size="md"
				:suggestions="item.suggestions || []"
				:dropdown-visible="!!item.dropdown_visible"
				:text-field="item.text_field || 'name'"
				@update:modelValue="(v) => { item.model = v; if (item.onInput) item.onInput(v) }"
				@clear="() => { if (item.onClear) item.onClear() }"
				@select="(s) => { if (item.onSelect) item.onSelect(s) }"
				@blur="() => { if (item.onBlur) item.onBlur() }"
				/>

			<CheckboxButton v-else-if="item.type === 'checkbox_button'"
				v-model="item.model"
				:label="item.name"
				:icon="item.icon"
				:size="item.size || 'md'"
				:disabled="item.disabled"
				:custom-class="item.class"
				:hide-label-mobile="!!item.hide_label_mobile"
				@change="(value, e) => { if (item.action) item.action(value, e) }"
				/>

			<Checkbox v-else-if="item.type === 'checkbox'"
				class="t-panel-item"
				:class="item.class"
				:model-value="item.model"
				:label="item.name"
				:label-mobile="item.name_mobile"
				:size="item.size || 'md'"
				:disabled="item.disabled"
				@update:modelValue="(v) => { item.model = v; if (item.action) item.action(!!v) }"
				/>

			<div v-else
				class="t-panel-item button button-sm space-x-1"
				:class="[
					item.class,
					{
						'disabled'        : item.disabled,
						'primary-button'  : item.type=='button',
						'cursor-pointer'  : item.type!='static',
						'gap-0'           : item.type=='card',
					}
				]"
				@click="(e) => { panelItemClick(item, e) }"
				>
					<Icon v-if="item.icon" class="icon" :icon="item.icon" />
					<span v-if="item.prefix" class="me-1">{{ $t(item.prefix) }}</span>
					<span v-if="item.name" :class="{ 'lt-md:hidden': !!item.hide_label_mobile }">{{ $t(item.name) }}</span>
					<span v-if="item.currency" class="ms-1 lt-md:hidden">{{ item.currency }}</span>
			</div>

		</template>

		<div class="grow flex items-center">
			<slot name="tools" />
		</div>

		<!-- End Filters -->
		<div v-if="customToolsList.length || (settings.filters?.deleted && !show_rowbar)"
			class="end-filters flex items-center gap-3">

			<template v-for="(item, idx) in customToolsList" :key="'c-'+idx">

				<CheckboxButton v-if="item.type === 'checkbox_button'"
					v-model="item.model"
					:label="item.name"
					:icon="item.icon"
					:size="item.size || 'md'"
					:disabled="item.disabled"
					:custom-class="item.class"
					:hide-label-mobile="!!item.hide_label_mobile"
					@change="(value, e) => { if (item.action) item.action(value, e) }"
					/>

				<Checkbox v-else-if="item.type === 'checkbox'"
					class="t-panel-item"
					:class="item.class"
					:model-value="item.model"
					:label="item.name"
					:label-mobile="item.name_mobile"
					:size="item.size || 'sm'"
					:disabled="item.disabled"
					@update:modelValue="(v) => { item.model = v; if (item.action) item.action(!!v) }"
					/>

				<div v-else
					class="t-panel-item button button-sm"
					:class="[
						item.class,
						{
							'disabled'        : item.disabled,
							'primary-button'  : item.type=='button',
							'cursor-pointer'  : item.type!='static',
							'gap-0'           : item.type=='card',
						}
					]"
					@click="(e) => { panelItemClick(item, e) }"
					>
						<Icon v-if="item.icon" class="icon" :icon="item.icon" />
						<span v-if="item.prefix" class="me-1 lt-sm:hidden">{{ $t(item.prefix) }}</span>
						<span v-if="item.name" :class="{ 'lt-md:hidden': !!item.hide_label_mobile }">{{ $t(item.name) }}</span>
						<span v-if="item.currency" class="ms-1 lt-md:hidden">{{ item.currency }}</span>
				</div>

			</template>

			<!-- Show Deleted toggle — only when the rowbar column is hidden; otherwise it
			     lives in the rowbar header (see TableHeader). -->
			<Checkbox v-if="settings.filters?.deleted && !show_rowbar"
				v-model="panel_data.showDeleted"
				class="t-panel-item"
				size="md"
				:label="'Deleted'"
				/>

		</div>

		<!-- End Button — перенесено у Filters.vue (кнопка меню звіту з :menu). -->
		<!-- <SelectableButton class="export-selectable !ms-4"
			v-if="settings.dropdownmenu && Object.keys(settings.dropdownmenu).length"
			:actions="settings.dropdownmenu.items"
			:items_class="'rounded-md'"
			size="sm"
			type=""
			@click.stop
		>
			<template #default="{ current_action }">
				<Icon :icon="current_action?.icon || 'mdi:dots-vertical'" class="icon !icon-md !hidden lt-md:!flex"/>
			</template>
		</SelectableButton> -->

    </div>

</template>

<script>
    import { Icon }         from '@iconify/vue'

    import SelectableButton from '../Forms/SelectableButton.vue'
    import CheckboxButton   from '../Forms/CheckboxButton.vue'
    import Checkbox         from '../Forms/Checkbox.vue'
    import SearchableInput  from '../Forms/SearchableInput.vue'

    export default {
        components: { Icon, SelectableButton, CheckboxButton, Checkbox, SearchableInput },
        props: {
            settings: {
                type: Object,
                default: {}
            },
            panel_data: {
                type: Object,
                default: {}
            },
            filters: {
                type: Object,
                default: {}
            },
            // Whether the host table renders the rowbar column. When true, the
            // "Show deleted" toggle lives in the rowbar header, not in this panel.
            show_rowbar: {
                type: Boolean,
                default: false
            },
        },
		computed: {
			ctabuttonActions() {
				return (this.settings.ctabutton?.actions ?? []).map(item => ({
					...item,
					name: item.title ?? item.name,
				}))
			},
			panelItemsList() {
				return Object.values(this.settings.panelitems ?? {});
			},
			customToolsList() {
				return Object.values(this.settings.custom_tools ?? {});
			},
		},
		methods: {
			panelItemClick(item, e) {
				// console.log('[TableToolsPanel.panelItemClick]', item);
				if (item.action) item.action(e)
			},
			onCtaButtonClick(e) {
				// console.log('[TableToolsPanel.onCtaButtonClick]');
				const cta = this.settings.ctabutton;
				if (!cta || cta.disabled) return;
				if (typeof cta.action === 'function') cta.action(e);
			},
		},
    }
</script>

<style lang="scss" scoped>
	.table-tools-panel {
		background-color: var(--background-color, var(--table-body-background));

		// Стала висота ряду незалежно від набору контролів: інакше сусідні таблиці
		// на одній сторінці отримують шапки на різних рівнях — та, де стоїть поле
		// пошуку, нижча за ту, де лише кнопка. Висота найбільшого контрола + нижнє поле.
		min-height: calc(var(--ui-h-md) + 0.5rem);
	}

	.table-tools-panel > *:not(.shrinkable) {
		flex-shrink: 0;
	}

    .t-panel-item {
        display: inline-flex;
        align-items: center;
		// color: var(--primary-button-background);
		background-color: var(--table-selection-color)!important;

		span {
            // Не text-wrap: его Apple понимает только с 17.4, ниже подпись фильтра переносится.
            white-space: nowrap;
			line-height: 1;
			position: relative;
			top: 1px;
        }
    }

	:deep(.export-selectable .action-name) {
		@apply lt-md:hidden;
	}

</style>
