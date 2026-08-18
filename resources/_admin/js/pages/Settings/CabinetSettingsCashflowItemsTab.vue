<template>

	<div class="h-full v-flex space-y-1">

		<Table class="grow table-md"
			ref			= "cashflow_table"
			:settings  	= "table_settings"
			:in_data   	= "$dictionaries.cashflow_items"
			:selects	= "dynamic_selects"
			:groupBy	= "'group_name'"
			:expandedByDefault = "true"
			:scrolled  	= "true"

			@rowSelect  = "(row) => openItem(row)"
			@onAdd		= "(row) => addItem(row)"
			@onAddNew	= "(row) => addInGroup(row)"
			@onDelete	= "(row) => removeItem(row)"
			@onRestore	= "(row) => restoreItem(row)"
			/>

		<ModalForm ref="modalform" :outsideClickClose="false">
			<CashflowItemCard
				ref="cashflowitemcard"
				:in_data="currentRow"
				:route_prefix	= "route_prefix"
				:dictionary_name= "dictionary_name"
				@close="closeTableModal({ table_data: $dictionaries.cashflow_items })"
				/>
		</ModalForm>

	</div>

</template>

<script>
	import tableformMixins  from '@/js/_tableformMixins.js';

	import ModalForm        from '@/js/Elements/ModalForm.vue';

	import CashflowItemCard from './Cards/CashflowItemCard.vue'

	export default {
		name: 'CashflowItemsTab',
		mixins: [tableformMixins],
		components: { ModalForm, CashflowItemCard },
		data() {
			return {
				route_prefix: 'cabinet.api.cashflowitem',
				dictionary_name: 'cashflow_items',

				table_settings: {
					columns: [
						{ field: 'code',       title: 'Code',       width:'min-content', type:'string', align:'center' },
						{ field: 'name',       title: 'Name',       width:'1fr',         type:'string', align:'start', translate:true },
						{ field: 'short_name', title: 'Short name', width:'auto',        type:'string', align:'start', translate:true, show:'md' },
						{ field: 'direction',  title: 'Direction',  width:'min-content', type:'select', align:'center' },
					],
					groupactions: { add: true },
					// Мʼяке видалення: тулбар отримує чекбокс «Show deleted», а контекстне
					// меню — стандартні пункти Delete/Restore (за станом is_deleted рядка).
					filters: { deleted: true },
					// На десктопі дії видалення/відновлення — через контекстне меню;
					// рядковий бар з іконками показуємо лише на мобільному.
					rowbar_mobile_only: true,
					contextmenu: [
						// Додавання нової статті в групу рядка, на якому відкрито меню.
						// Видалення/відновлення додаються таблицею автоматично (filters.deleted).
						{
							name: 'Add',
							icon: 'mdi:plus',
							event: 'onAddNew',
						},
					],
					// Підсвічуємо власні (несистемні) статті акаунта лівим акцентним баром.
					row_class: (row) => row.is_system ? '' : 'accent-row',
					// Порядок усередині групи: системні → власні (за алфавітом), з кодом → без коду.
					orderby: ['group_order', 'sort_system', 'sort_no_code', 'sort_tiebreak'],
				},
			}
		},
		computed: {
			// Мапа group_id -> { label, order } для accordion-групування й сортування груп за кодом.
			group_meta() {
				const meta = {};
				(this.$dictionaries.cashflow_groups ?? []).forEach(group => {
					meta[group.id] = {
						label: `${group.code} ${this.$t(group.name)}`,
						order: Number(group.code) || 0,
					};
				});
				return meta;
			},
			dynamic_selects() {
				return {
					"group_id":  (this.$dictionaries.cashflow_groups ?? []).map(group => ({ id: group.id, name: `${group.code} ${this.$t(group.name)}` })),
					"direction": [
						{ id: 'in',  name: 'Income' },
						{ id: 'out', name: 'Expense' },
					],
				}
			},
		},
		watch: {
			// Перезбираємо підписи груп при оновленні словників (додавання/видалення статті).
			// deep — щоб спрацьовувало і при мутаціях масиву на місці (push/splice/оновлення рядка
			// після збереження), бо посилання на масив словника при цьому не змінюється.
			'$dictionaries.cashflow_items': { handler() { this.enrichGroupNames(); }, immediate: true, deep: true },
			group_meta() { this.enrichGroupNames(); },
		},
		methods: {
			// Таблиця групує за group_name і сортує групи за group_order (код групи) — проставляємо обидва поля.
			enrichGroupNames() {
				const meta = this.group_meta;
				(this.$dictionaries.cashflow_items ?? []).forEach(item => {
					const group = meta[item.group_id];
					item.group_name  = group ? group.label : '';
					item.group_order = group ? group.order : 0;

					// Ключі сортування всередині групи:
					//   sort_system   — системні (0) перед власними (1);
					//   sort_no_code  — з кодом (0) перед без коду (1);
					//   sort_tiebreak — системні в природному порядку (order_id), власні за алфавітом назви.
					const has_code = item.code != null && String(item.code).trim() !== '';
					item.sort_system   = item.is_system ? 0 : 1;
					item.sort_no_code  = has_code ? 0 : 1;
					item.sort_tiebreak = item.is_system ? (Number(item.order_id) || 0) : String(item.name ?? '');
				});
			},
			// Системні статті (is_system) спільні для всіх акаунтів — лише для читання.
			openItem(row) {
				if ( row.is_system )
					return this.$toast.error('System cash flow item is read-only');

				this.openTableRecord(row);
			},
			async removeItem(row) {
				if ( row.is_system )
					return this.$toast.error('System cash flow item can`t be deleted');

				// Мʼяке видалення статті: документи, що її використовують, можуть зникнути зі словників і звітів.
				if ( !await this.$popup.confirm_yn(this.$t('cashflow-item-delete-confirm'), { danger: true }) )
					return;

				await this.deleteDictionaryRecord(row, {}, false);
				this.$dictionaries.update(this.dictionary_name);
			},
			// Відновлення мʼяко видаленої статті (стандартний пункт контекстного меню Restore).
			async restoreItem(row) {
				// Поки статтю було видалено, її код могли зайняти новою активною статтею —
				// перед відновленням перевіряємо, що код не дублюється (перевірка лише для цього табу).
				const code = String(row.code ?? '').trim()
				const code_taken = (this.$dictionaries.cashflow_items ?? []).some(item =>
					item.id != row.id &&
					!item.is_deleted &&
					String(item.code ?? '').trim() === code
				)
				if ( code && code_taken )
					return this.$toast.error('Cash flow item code must be unique')

				await this.restoreDictionaryRecord(row, true);
				this.$dictionaries.update(this.dictionary_name);
			},
			addItem(row) {
				this.addTableRecord(row);
				// Підсвічуємо новий рядок одразу при натисканні «Додати».
				this.$refs.cashflow_table.selectRow(row);
			},
			// Контекстне меню «Add» — нова стаття у групі рядка, на якому відкрито меню.
			addInGroup(row) {
				this.$refs.cashflow_table.addRow({ group_id: row.group_id });
			},
		}
	}
</script>

<style lang="scss" scoped>
</style>
