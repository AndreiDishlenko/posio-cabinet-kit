// Reusable drag-and-drop row reordering for table components.
//
// Two flavours, both driven by the `rowReorder` event ({ row, fromIndex, toIndex, dataset }):
//   • reorderTableRow  — server-backed: persists order_id via {route_prefix}.moveorder
//                        and applies an optimistic local renumber so the table re-sorts
//                        immediately. Used by dictionary tables.
//   • reorderLocalRows — client-only array move for collections whose order is persisted
//                        implicitly by their position on the parent save.
//
// moveRecordOrder — та же серверная перестановка, но заданная парой «запись + цель»
// (до / после), а не индексом: так приходит перенос между группами и перетаскивание
// самих групп, где индекса в общем наборе нет.
//
// This mixin is intentionally tiny and dependency-light so it can be mixed into any
// page/component, with or without the heavier _tableformMixins.
export default {
	methods: {
		// Resolve the API route prefix: explicit arg wins, otherwise the component's
		// own `route_prefix` (the convention used across dictionary pages).
		_reorderRoutePrefix(route_prefix = '') {
			const prefix = route_prefix || this.route_prefix;
			if ( !prefix ) {
				this.$toast?.error('Route prefix is undefined');
				return '';
			}
			return prefix;
		},

		// Drag-and-drop reorder handler — single API request.
		// Sends one POST to {route_prefix}.moveorder; the backend renumbers
		// every affected row's order_id in a single transaction.
		// Also applies an optimistic local renumber so the table re-sorts
		// immediately without waiting for the response.
		//
		// `dataset` is the array the dragged row belongs to. For grouped tables
		// it's the group subarray (provided by the Table `rowReorder` event); for
		// flat tables it defaults to the whole `table_data`. Either way it holds
		// references to the same reactive row objects, so renumbering re-sorts the
		// table immediately.
		async reorderTableRow(table_ref, row, toIndex, route_prefix = '', dataset = null) {
			// reorderMixins.reorderTableRow
			const tableComp = typeof table_ref == 'string' ? this.$refs[table_ref] : table_ref;
			if ( !tableComp || !row ) return false;

			const data = Array.isArray(dataset) ? dataset : tableComp.table_data;
			if ( !Array.isArray(data) || !data.length ) return false;

			const fromIndex = data.indexOf(row);
			if ( fromIndex < 0 ) return false;

			let clampedTarget = toIndex;
			if ( clampedTarget < 0 ) clampedTarget = 0;
			if ( clampedTarget > data.length - 1 ) clampedTarget = data.length - 1;
			if ( fromIndex == clampedTarget ) return true;

			const targetRow = data[clampedTarget];
			const position = fromIndex < clampedTarget ? 'after' : 'before';

			return this.moveRecordOrder(row, targetRow, position, route_prefix, data);
		},

		// Перестановка записи относительно другой записи (до / после) с сохранением
		// на сервере. Набор для пересчёта — список, где записи соседствуют (группа
		// таблицы или справочник целиком).
		async moveRecordOrder(row, targetRow, position, route_prefix = '', dataset = null) {
			// reorderMixins.moveRecordOrder
			if ( !row || !targetRow || row === targetRow ) return false;

			const data = Array.isArray(dataset) ? dataset : [];
			position = position === 'before' ? 'before' : 'after';

			const route_prefix_resolved = this._reorderRoutePrefix(route_prefix);
			if ( !route_prefix_resolved ) return false;

			// Snapshot original order_ids so we can roll back on server error.
			const originalOrderIds = new Map();
			data.forEach(item => originalOrderIds.set(item, item.order_id));

			this.renumberMovedRecord(data, row, targetRow, position);

			const route_name = route_prefix_resolved + '.moveorder';
			const result = await this.$apiClient.post( route( route_name ), {
				current_id: row.id,
				target_id:  targetRow.id,
				position:   position,
			});

			if ( result.error ) {
				// Roll back the optimistic renumber.
				originalOrderIds.forEach((order_id, item) => { item.order_id = order_id; });
				this.$toast.error( result.error );
				return false;
			}

			return true;
		},

		// Оптимистичный пересчёт номеров: запись переставляется по месту в списке, и
		// номера раздаются заново в новом порядке. Арифметика по самим номерам тут не
		// годится — они бывают одинаковыми, и тогда «до/после» неотличимы, а строка
		// остаётся на месте.
		renumberMovedRecord(dataset, row, targetRow, position) {
			// reorderMixins.renumberMovedRecord
			// Тот же порядок, что берёт бэкенд: номер, при равных — id.
			const items = [...dataset].sort((a, b) =>
				(a.order_id ?? 0) - (b.order_id ?? 0) || (a.id ?? 0) - (b.id ?? 0)
			);

			const from = items.indexOf(row);
			if ( from < 0 ) return false;

			items.splice(from, 1);

			const anchor = items.indexOf(targetRow);
			if ( anchor < 0 ) return false;

			items.splice(position === 'before' ? anchor : anchor + 1, 0, row);

			// Набор может быть частью справочника (группа таблицы, отфильтрованный
			// список), поэтому по возможности переиспользуем занятые им номера — так
			// не сбивается порядок относительно записей за пределами набора. Если
			// номера в наборе неразличимы, раздаём сквозные: порядок важнее, а
			// сервер всё равно перенумерует весь справочник.
			const slots = items.map(item => item.order_id).sort((a, b) => a - b);
			const distinct = slots.every((value, index) => !index || value > slots[index - 1]);

			items.forEach((item, index) => {
				item.order_id = distinct ? slots[index] : index + 1;
			});

			return true;
		},

		// Local (client-only) drag-and-drop reorder for arrays whose order is
		// persisted implicitly by their position. No API request: the new
		// array order is written back on the parent save.
		reorderLocalRows(dataset, fromIndex, toIndex) {
			// reorderMixins.reorderLocalRows
			if ( !Array.isArray(dataset) ) return false;
			if ( fromIndex < 0 || fromIndex >= dataset.length ) return false;
			if ( toIndex   < 0 || toIndex   >= dataset.length ) return false;
			if ( fromIndex === toIndex ) return true;

			const [moved] = dataset.splice(fromIndex, 1);
			dataset.splice(toIndex, 0, moved);

			return true;
		},
	}
};
