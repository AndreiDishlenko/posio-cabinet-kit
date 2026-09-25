<script>
	// Drag-and-drop reorder handler for Table.vue.
	//
	// Renderless — owns all DnD mechanics:
	//   • mouse listeners on document
	//   • drag threshold (5px) to distinguish click from drag
	//   • cloned-cell ghost that follows the cursor and reproduces the dragged row
	//   • hit-testing for the drop target row
	//   • outside-table cancel
	//
	// Тягнути можна і шапку групи: тоді ціллю є шапка іншої групи (порядок груп), а
	// рядок, кинутий на шапку, переходить у цю групу. Що саме тягнуть, видно з того,
	// який кандидат заповнений — рядок чи ключ групи.
	//
	// Parent is expected to keep a reactive `drag` object on data and mirror it
	// from the `drag-update` emit; the parent renders placeholder rows itself
	// based on that state, and reacts to `drop` to emit `rowReorder`.
	//
	// Тачскрін — окремими touch-подіями, а не pointer-подіями: спільний код тримає
	// планку Safari 12, де їх ще немає. Рядок береться довгим натисканням — рух
	// пальця до цього моменту лишається прокруткою списку.

	// Натискання не починає перетягування на керуючих елементах рядка / шапки.
	const ROW_SKIP   = '.rowbar, button, input, select, textarea, a, label, [contenteditable="true"]';
	const GROUP_SKIP = '.group-actions, button, input, select, textarea, a, label, [contenteditable="true"]';

	const MOUSE_THRESHOLD = 5;
	const LONG_PRESS_MS   = 350;
	const TOUCH_SLOP      = 10;

	// Смуга біля краю області прокрутки, що прокручує її під час перетягування.
	const AUTOSCROLL_EDGE     = 48;
	const AUTOSCROLL_MAX_STEP = 16;

	export default {
		name: 'TableDragHandler',
		props: {
			enabled:      { type: Boolean, default: false },
			group_key:    { type: String,  default: '' },
			row_key:      { type: String,  default: 'id' },      // field used as the stable per-row key
			table_ref:    { type: String,  default: 'table' },   // parent ref name of table root element
			grouped_data: { type: Object,  default: () => ({}) },
			// Режим дерева: у рядка-цілі три зони замість двох — верхня/нижня третини
			// роблять перетягнутий рядок сусідом, середина — вкладає його всередину.
			tree_mode:    { type: Boolean, default: false },
			// Валідатор цілі (row, overRow, pos) => bool. Заборонена ціль не підсвічується
			// й не приймає drop — напр. вкладення гілки саму в себе.
			can_drop:     { type: Function, default: null },
			// Шапки груп самі стають перетягуваними (зміна порядку груп) — і в будь-якому
			// разі приймають скидання рядка (перенесення рядка в іншу групу).
			groups_draggable: { type: Boolean, default: false },
			// Шапки груп приймають скинутий рядок (перенесення в іншу групу), навіть
			// коли самі групи не перетягуються.
			group_drop:   { type: Boolean, default: false },
			// Рядки лише виносяться в сусідню таблицю: своя таблиця скидання не приймає.
			external_only: { type: Boolean, default: false },
		},
		emits: ['drag-update', 'drop'],
		data() {
			return {
				candidateRow:   null,
				candidateGroup: null,
				// Ключ групи, шапку якої тягнуть (замість рядка).
				candidateGroupKey: null,
				startX: 0,
				startY: 0,
				grabOffsetX: 0,
				grabOffsetY: 0,
				active: false,
				overRow:   null,
				overGroup: null,
				// Ключ групи, над шапкою якої зараз курсор.
				overGroupKey: null,
				// Рядок сусідньої таблиці, що оголосила прийом цієї групи перетягування.
				overExternal: null,
				overPos:   'before',
				ignoreClick: false,
			};
		},
		beforeUnmount() {
			this.cleanup();
		},
		methods: {
			rowKey(row) {
				// TableDragHandler.rowKey — stable identity used for hit-testing / ghost.
				return String(row?.[this.row_key]);
			},
			escapeKey(key) {
				return window.CSS && CSS.escape
					? CSS.escape(key)
					: key.replace(/"/g, '\\"');
			},
			isSkippedTarget(target, selector) {
				return !!( target && target.closest && target.closest(selector) );
			},
			isDragPending() {
				return !!this.candidateRow || this.candidateGroupKey != null;
			},
			onRowMouseDown(row, group, event) {
				// TableDragHandler.onRowMouseDown
				if ( !this.enabled ) return;
				if ( event.button !== 0 ) return;
				if ( this.isDragPending() ) return;
				if ( this.isSkippedTarget(event.target, ROW_SKIP) ) return;

				this.candidateRow   = row;
				this.candidateGroup = group;

				this.startDrag(event, this.measureRowRect(row));
			},
			// Натискання на шапку групи: тягнуть саму групу (порядок груп).
			onGroupMouseDown(group_key, event) {
				// TableDragHandler.onGroupMouseDown
				if ( !this.groups_draggable ) return;
				if ( event.button !== 0 ) return;
				if ( this.isDragPending() ) return;
				if ( this.isSkippedTarget(event.target, GROUP_SKIP) ) return;

				this.candidateGroupKey = String(group_key);

				this.startDrag(event, this.measureElRect(this.groupHeaderEl(this.candidateGroupKey)));
			},
			onRowTouchStart(row, group, event) {
				// TableDragHandler.onRowTouchStart
				if ( !this.enabled ) return;
				if ( event.touches.length !== 1 ) return;
				if ( this.isDragPending() ) return;
				if ( this.isSkippedTarget(event.target, ROW_SKIP) ) return;

				this.candidateRow   = row;
				this.candidateGroup = group;

				this.startTouchDrag(event, this.measureRowRect(row));
			},
			onGroupTouchStart(group_key, event) {
				// TableDragHandler.onGroupTouchStart
				if ( !this.groups_draggable ) return;
				if ( event.touches.length !== 1 ) return;
				if ( this.isDragPending() ) return;
				if ( this.isSkippedTarget(event.target, GROUP_SKIP) ) return;

				this.candidateGroupKey = String(group_key);

				this.startTouchDrag(event, this.measureElRect(this.groupHeaderEl(this.candidateGroupKey)));
			},
			// Точка захоплення і скидання цілі — спільне для миші й дотику.
			resetPointer(point, sourceRect) {
				this.startX = point.clientX;
				this.startY = point.clientY;
				this._lastPoint   = { clientX: point.clientX, clientY: point.clientY };
				this.overRow      = null;
				this.overGroup    = null;
				this.overGroupKey = null;
				this.overPos      = 'before';

				// Record where inside the row the user grabbed it, so the ghost
				// keeps the cursor at the exact same spot while dragging.
				if ( sourceRect ) {
					this.grabOffsetX = point.clientX - sourceRect.left;
					this.grabOffsetY = point.clientY - sourceRect.top;
				} else {
					this.grabOffsetX = 0;
					this.grabOffsetY = 0;
				}
			},
			startDrag(event, sourceRect) {
				this.resetPointer(event, sourceRect);

				this._onDocMouseMove = this.onDocMouseMove.bind(this);
				this._onDocMouseUp   = this.onDocMouseUp.bind(this);
				document.addEventListener('mousemove', this._onDocMouseMove);
				document.addEventListener('mouseup',   this._onDocMouseUp);

				event.preventDefault();
			},
			// Без preventDefault на старті: інакше ні прокрутка, ні звичайний тап по
			// рядку не спрацюють. Прокрутку гасить уже рух після довгого натискання.
			startTouchDrag(event, sourceRect) {
				this.resetPointer(event.touches[0], sourceRect);

				this._touchDrag      = true;
				this._movedSinceLift = false;

				// Не пасивний: iOS інакше ігнорує preventDefault і прокручує сторінку
				// під пальцем, що тягне рядок.
				this._onDocTouchMove = this.onDocTouchMove.bind(this);
				this._onDocTouchEnd  = this.onDocTouchEnd.bind(this);
				document.addEventListener('touchmove',   this._onDocTouchMove, { passive: false });
				document.addEventListener('touchend',    this._onDocTouchEnd,  { passive: false });
				document.addEventListener('touchcancel', this._onDocTouchEnd,  { passive: false });

				this._longPressTimer = setTimeout(() => this.onLongPress(), LONG_PRESS_MS);
			},
			onLongPress() {
				// TableDragHandler.onLongPress
				this._longPressTimer = null;
				if ( !this.isDragPending() ) return;

				this.beginDrag();

				if ( navigator.vibrate )
					navigator.vibrate(15);

				this.updateDrag(this._lastPoint);
			},
			onDocMouseMove(event) {
				if ( !this.isDragPending() ) return;

				if ( !this.active ) {
					const dx = event.clientX - this.startX;
					const dy = event.clientY - this.startY;
					if ( Math.hypot(dx, dy) < MOUSE_THRESHOLD ) return;
					this.beginDrag();
				}

				this.updateDrag(event);
			},
			onDocTouchMove(event) {
				const touch = event.touches[0];
				if ( !touch || !this.isDragPending() ) return;

				const point = { clientX: touch.clientX, clientY: touch.clientY };

				if ( !this.active ) {
					// Палець зрушив раніше, ніж рядок узяли, — це прокрутка списку.
					if ( Math.hypot(point.clientX - this.startX, point.clientY - this.startY) > TOUCH_SLOP )
						this.cleanup();
					else
						this._lastPoint = point;
					return;
				}

				if ( event.cancelable )
					event.preventDefault();

				this._movedSinceLift = true;
				this.updateDrag(point);
			},
			onDocTouchEnd(event) {
				// Скасований синтетичний клік: інакше відпущений рядок ще й відкрився б.
				if ( this.active && event.cancelable )
					event.preventDefault();

				this.finishDrag();
			},
			// Довге натискання на тачскріні — це ще й жест контекстного меню. Поки
			// піднятий рядок не зрушили, перевага за меню, і перетягування знімається.
			yieldToContextMenu() {
				// TableDragHandler.yieldToContextMenu
				if ( !this._touchDrag || !this.active || this._movedSinceLift )
					return false;

				this.cleanup();
				return true;
			},
			getTableEl() {
				return this.$parent?.$refs?.[this.table_ref] || null;
			},
			measureRowRect(row) {
				// TableDragHandler.measureRowRect — union rect of a row's cells
				// (`.table-row` has display:contents, so its own rect is empty).
				const tableEl = this.getTableEl();
				if ( !tableEl || !row ) return null;

				const safeId = this.escapeKey(this.rowKey(row));

				const rowEl = tableEl.querySelector(`.table-row[data-row-key="${safeId}"]`);
				if ( !rowEl ) return null;

				const cells = rowEl.children;
				if ( !cells.length ) return null;

				let left = Infinity, right = -Infinity, top = Infinity, bottom = -Infinity;
				for ( const c of cells ) {
					const r = c.getBoundingClientRect();
					if ( r.left   < left   ) left   = r.left;
					if ( r.right  > right  ) right  = r.right;
					if ( r.top    < top    ) top    = r.top;
					if ( r.bottom > bottom ) bottom = r.bottom;
				}
				return { left, top, right, bottom, width: right - left, height: bottom - top };
			},
			groupHeaderEl(group_key) {
				const tableEl = this.getTableEl();
				if ( !tableEl || group_key == null ) return null;

				return tableEl.querySelector(`[data-group-key="${this.escapeKey(String(group_key))}"]`);
			},
			measureElRect(el) {
				if ( !el ) return null;

				const r = el.getBoundingClientRect();
				return { left: r.left, top: r.top, right: r.right, bottom: r.bottom, width: r.width, height: r.height };
			},
			// Габарити цілі скидання: шапка групи — власний прямокутник, рядок — обʼєднання клітинок.
			measureTargetRect() {
				return this.overGroupKey != null
					? this.measureElRect(this.groupHeaderEl(this.overGroupKey))
					: this.measureRowRect(this.overRow);
			},
			beginDrag() {
				// TableDragHandler.beginDrag
				this.active      = true;
				this.ignoreClick = true;

				this.buildGhost();

				document.body.classList.add('table-dragging');
				window.__tableDragSource = {
					group_key: this.group_key,
					row:       this.candidateRow,
					dataset:   this.candidateGroup,
				};

				this.startAutoScroll();
				this.notify();
			},
			// Біля краю області прокрутки її прокручує саме перетягування: на тачскріні
			// палець зайнятий рядком, і ціль поза екраном інакше недосяжна.
			startAutoScroll() {
				if ( this._autoScrollFrame ) return;

				const tick = () => {
					this._autoScrollFrame = requestAnimationFrame(tick);

					// Під нерухомим курсором проїхав інший вміст — ціль треба знайти заново.
					if ( this._lastPoint && this.autoScrollAt(this._lastPoint) )
						this.updateDrag(this._lastPoint);
				};

				this._autoScrollFrame = requestAnimationFrame(tick);
			},
			stopAutoScroll() {
				if ( this._autoScrollFrame )
					cancelAnimationFrame(this._autoScrollFrame);

				this._autoScrollFrame = null;
			},
			autoScrollAt(point) {
				// TableDragHandler.autoScrollAt
				const root = document.scrollingElement || document.documentElement;
				const el   = document.elementFromPoint(point.clientX, point.clientY);

				// Від найближчої області назовні: упершу в край внутрішнього списку,
				// а коли той уже докручений — сторінка.
				for ( const scroller of this.scrollChainOf(el, root) ) {
					const rect   = scroller === root ? { top: 0, bottom: window.innerHeight } : scroller.getBoundingClientRect();
					const top    = Math.max(rect.top, 0);
					const bottom = Math.min(rect.bottom, window.innerHeight);

					let step = 0;
					if ( point.clientY < top + AUTOSCROLL_EDGE )
						step = -this.autoScrollStep(top + AUTOSCROLL_EDGE - point.clientY);
					else if ( point.clientY > bottom - AUTOSCROLL_EDGE )
						step = this.autoScrollStep(point.clientY - (bottom - AUTOSCROLL_EDGE));

					if ( !step ) continue;

					const max  = scroller.scrollHeight - scroller.clientHeight;
					const next = Math.min(max, Math.max(0, scroller.scrollTop + step));
					if ( next === scroller.scrollTop ) continue;

					scroller.scrollTop = next;
					return true;
				}

				return false;
			},
			// Що глибше в смузі краю, то швидше.
			autoScrollStep(depth) {
				return Math.ceil(AUTOSCROLL_MAX_STEP * Math.min(1, depth / AUTOSCROLL_EDGE));
			},
			scrollChainOf(el, root) {
				const chain = [];

				for ( let node = el; node && node !== root && node !== document.body; node = node.parentElement ) {
					if ( node.scrollHeight <= node.clientHeight + 1 ) continue;

					const overflow_y = window.getComputedStyle(node).overflowY;
					if ( overflow_y === 'auto' || overflow_y === 'scroll' )
						chain.push(node);
				}

				chain.push(root);
				return chain;
			},
			buildGhost() {
				// TableDragHandler.buildGhost
				// Clone the dragged row's cell elements so the user sees the actual
				// row content travelling under the cursor.
				const tableEl = this.getTableEl();
				if ( !tableEl ) return;

				let cellEls = [];

				if ( this.candidateGroupKey != null ) {
					const headerEl = this.groupHeaderEl(this.candidateGroupKey);
					if ( headerEl )
						cellEls = [headerEl];
				}
				else if ( this.candidateRow ) {
					const safeId = this.escapeKey(this.rowKey(this.candidateRow));
					const rowEl  = tableEl.querySelector(`.table-row[data-row-key="${safeId}"]`);
					if ( rowEl )
						cellEls = Array.from(rowEl.children);
				}

				if ( !cellEls.length ) return;

				const container = document.createElement('div');
				container.className = 'table-drag-ghost';

				cellEls.forEach(cell => {
					const rect  = cell.getBoundingClientRect();
					const clone = cell.cloneNode(true);

					clone.style.flex      = '0 0 auto';
					clone.style.width     = rect.width  + 'px';
					clone.style.minHeight = rect.height + 'px';
					clone.style.opacity   = '1';

					// Disable interactive elements inside the clone.
					clone.querySelectorAll('input, textarea, select, button, a').forEach(el => {
						el.style.pointerEvents = 'none';
						if ( el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT' )
							el.disabled = true;
					});

					container.appendChild(clone);
				});

				document.body.appendChild(container);
				this._ghost = container;
			},
			updateDrag(event) {
				this._lastPoint = { clientX: event.clientX, clientY: event.clientY };

				if ( this._ghost )
					this._ghost.style.transform =
						`translate(${event.clientX - this.grabOffsetX}px, ${event.clientY - this.grabOffsetY}px)`;

				const target = this.findDropTarget(event);
				if ( target?.external ) {
					this.overRow      = null;
					this.overGroup    = null;
					this.overGroupKey = null;
					this.overPos      = target.pos;
					this.setExternal(target.external);
					this.hideIndicator();
				} else if ( target ) {
					this.setExternal(null);
					this.overRow      = target.row      ?? null;
					this.overGroup    = target.group    ?? null;
					this.overGroupKey = target.group_key ?? null;
					this.overPos      = target.pos;
					this.updateIndicator();
				} else {
					this.setExternal(null);
					this.overRow      = null;
					this.overGroup    = null;
					this.overGroupKey = null;
					this.hideIndicator();
				}

				this.notify();
			},
			ensureIndicator() {
				if ( this._indicator ) return;
				const ind = document.createElement('div');
				ind.className = 'table-drop-indicator';
				ind.style.display = 'none';
				document.body.appendChild(ind);
				this._indicator = ind;
			},
			updateIndicator() {
				// Вкладення показує сам рядок-приймач (підсвічення через клас у таблиці),
				// лінія-роздільник тут була б хибним сигналом «між рядками».
				if ( this.overPos === 'into' ) { this.hideIndicator(); return; }

				this.ensureIndicator();
				const rect = this.measureTargetRect();
				if ( !rect ) { this.hideIndicator(); return; }

				const y = this.overPos === 'before' ? rect.top : rect.bottom;
				this._indicator.style.display = 'block';
				this._indicator.style.left    = rect.left + 'px';
				this._indicator.style.width   = rect.width + 'px';
				this._indicator.style.top     = (y - 1) + 'px';
			},
			hideIndicator() {
				if ( this._indicator ) this._indicator.style.display = 'none';
			},
			findDropTarget(event) {
				// TableDragHandler.findDropTarget
				const tableEl = this.getTableEl();
				if ( !tableEl ) return null;

				// Межа зони скидання — тільки належність елемента під курсором таблиці.
				// Габарити кореневої коробки для цього не годяться: її висота обмежена
				// контейнером сторінки, а рядки сітки малюються нижче за неї, і все, що
				// нижче цієї межі, переставало прийматися як ціль.
				const el = document.elementFromPoint(event.clientX, event.clientY);
				if ( !el ) return null;
				if ( !tableEl.contains(el) ) return this.findExternalTarget(el);

				if ( this.external_only ) return null;

				// Тягнуть групу — цілями є лише шапки інших груп.
				if ( this.candidateGroupKey != null )
					return this.findGroupTarget(el, event);

				const rowEl = el.closest('.table-row[data-row-key]');
				// Рядок кинули на шапку групи — це перенесення в цю групу цілком.
				if ( !rowEl )
					return this.groups_draggable || this.group_drop ? this.findGroupIntoTarget(el) : null;

				if ( rowEl.classList.contains('drop-placeholder') ) return null;

				const rowKey = rowEl.getAttribute('data-row-key');

				let foundRow = null, foundGroup = null;
				for ( const groupName in this.grouped_data ) {
					const grp = this.grouped_data[groupName];
					const r   = grp.find(item => this.rowKey(item) === rowKey);
					if ( r ) { foundRow = r; foundGroup = grp; break; }
				}
				if ( !foundRow ) return null;
				if ( foundRow === this.candidateRow ) return null;

				const cells = rowEl.children;
				if ( !cells.length ) return null;

				let top = Infinity, bottom = -Infinity;
				for ( const c of cells ) {
					const r = c.getBoundingClientRect();
					if ( r.top    < top    ) top    = r.top;
					if ( r.bottom > bottom ) bottom = r.bottom;
				}

				const pos = this.tree_mode
					? this.treePosition(event.clientY, top, bottom)
					: ( event.clientY < top + (bottom - top) / 2 ? 'before' : 'after' );

				if ( this.can_drop && !this.can_drop(this.candidateRow, foundRow, pos) )
					return null;

				return { row: foundRow, group: foundGroup, pos };
			},
			// Ціль для перетягуваної групи: шапка іншої групи, сторона — по її середині.
			findGroupTarget(el, event) {
				const headerEl = el.closest('[data-group-key]');
				if ( !headerEl ) return null;

				const group_key = headerEl.getAttribute('data-group-key');
				if ( !group_key || group_key === this.candidateGroupKey ) return null;

				const rect = headerEl.getBoundingClientRect();
				const pos  = event.clientY < rect.top + rect.height / 2 ? 'before' : 'after';

				return { group_key, pos };
			},
			// Ціль поза власною таблицею: рядок сусідньої таблиці, яка оголосила прийом
			// цієї групи перетягування (напр. список категорій приймає товар). Цілі як
			// такої в моделі приймача тут немає — віддаємо лише ключ рядка, решту
			// резолвить сторінка.
			//
			// У згрупованому приймачі ціль — група: її шапка чи будь-який її рядок.
			// Порожня група рядків не має, і кинути в неї можна лише так.
			findExternalTarget(el) {
				// TableDragHandler.findExternalTarget
				if ( !this.group_key || this.candidateGroupKey != null ) return null;
				if ( !el.closest ) return null;

				const rootEl = el.closest('[data-drag-accept]');
				if ( !rootEl ) return null;

				const accepts = (rootEl.getAttribute('data-drag-accept') || '').split(',');
				if ( !accepts.includes(this.group_key) ) return null;

				const group = rootEl.getAttribute('data-drag-group') || '';

				const headerEl = el.closest('[data-group-key]');
				if ( headerEl && rootEl.contains(headerEl) )
					return this.externalGroupTarget(headerEl, headerEl.getAttribute('data-group-key'), group);

				const rowEl = el.closest('.table-row[data-row-key]');
				if ( !rowEl || !rootEl.contains(rowEl) ) return null;

				const row_group = rowEl.getAttribute('data-row-group');
				if ( row_group ) {
					const rowHeaderEl = rootEl.querySelector(`[data-group-key="${this.escapeKey(row_group)}"]`);
					return this.externalGroupTarget(rowHeaderEl || rowEl, row_group, group);
				}

				return {
					pos: 'into',
					external: {
						el:        rowEl,
						highlight: 'drop-into-row',
						group,
						row_key:   rowEl.getAttribute('data-row-key'),
						group_key: null,
					},
				};
			},
			externalGroupTarget(el, group_key, group) {
				return {
					pos: 'into',
					external: {
						el,
						highlight: 'drop-into-group',
						group,
						row_key:   null,
						group_key,
					},
				};
			},
			// Підсвічення приймача в сусідній таблиці робиться класом прямо по DOM:
			// приймач — інший екземпляр таблиці, його реактивного стану звідси не видно.
			setExternal(external) {
				// TableDragHandler.setExternal
				const nextEl = external?.el || null;
				if ( this._externalEl === nextEl ) {
					this.overExternal = external || null;
					return;
				}

				if ( this._externalEl )
					this._externalEl.classList.remove(this._externalHighlight);

				this._externalEl        = nextEl;
				this._externalHighlight = external?.highlight || '';

				if ( this._externalEl )
					this._externalEl.classList.add(this._externalHighlight);

				this.overExternal = external || null;
			},
			// Ціль для перетягуваного рядка: шапка групи — рядок переходить у цю групу.
			findGroupIntoTarget(el) {
				const headerEl = el.closest('[data-group-key]');
				if ( !headerEl ) return null;

				const group_key = headerEl.getAttribute('data-group-key');
				if ( !group_key ) return null;

				return { group_key, pos: 'into' };
			},
			// Три зони по висоті рядка: крайні третини — стати сусідом до/після,
			// середина — стати вкладеним у цей рядок.
			treePosition(y, top, bottom) {
				const step = (bottom - top) / 3;

				if ( y < top + step )
					return 'before';

				if ( y > bottom - step )
					return 'after';

				return 'into';
			},
			onDocMouseUp() {
				// TableDragHandler.onDocMouseUp
				this.finishDrag();
			},
			finishDrag() {
				// TableDragHandler.finishDrag
				const wasActive    = this.active;
				const row          = this.candidateRow;
				const group        = this.candidateGroup;
				const group_key    = this.candidateGroupKey;
				const overRow      = this.overRow;
				const overGroup    = this.overGroup;
				const overGroupKey = this.overGroupKey;
				const overExternal = this.overExternal;
				const overPos      = this.overPos;

				this.cleanup();

				if ( !wasActive ) return;

				// Перетягували шапку групи — ціллю була шапка іншої групи.
				if ( group_key != null ) {
					if ( overGroupKey == null ) return;
					return this.$emit('drop', { group_key, overGroupKey, overPos });
				}

				if ( !row || !group ) return;

				// Рядок кинули в сусідню таблицю-приймач.
				if ( overExternal )
					return this.$emit('drop', { row, group, external: overExternal });

				// Рядок кинули на шапку групи — перенесення в іншу групу.
				if ( overGroupKey != null )
					return this.$emit('drop', { row, group, overGroupKey, overPos });

				if ( !overRow ) return;

				this.$emit('drop', { row, group, overRow, overGroup, overPos });
			},
			cleanup() {
				this.stopAutoScroll();
				this.removeTouchListeners();
				this._lastPoint = null;
				this.setExternal(null);
				if ( this._ghost )     { this._ghost.remove();     this._ghost     = null; }
				if ( this._indicator ) { this._indicator.remove(); this._indicator = null; }
				document.body.classList.remove('table-dragging');
				if ( window.__tableDragSource ) window.__tableDragSource = null;

				const wasActive = this.active;
				this.active            = false;
				this.candidateRow      = null;
				this.candidateGroup    = null;
				this.candidateGroupKey = null;
				this.overRow           = null;
				this.overGroup         = null;
				this.overGroupKey      = null;
				this.overPos           = 'before';
				this.notify();

				if ( wasActive )
					setTimeout(() => { this.ignoreClick = false; this.notify(); }, 50);
				else
					this.ignoreClick = false;

				if ( this._onDocMouseMove ) {
					document.removeEventListener('mousemove', this._onDocMouseMove);
					this._onDocMouseMove = null;
				}
				if ( this._onDocMouseUp ) {
					document.removeEventListener('mouseup', this._onDocMouseUp);
					this._onDocMouseUp = null;
				}
			},
			removeTouchListeners() {
				if ( this._longPressTimer ) {
					clearTimeout(this._longPressTimer);
					this._longPressTimer = null;
				}

				if ( this._onDocTouchMove ) {
					document.removeEventListener('touchmove', this._onDocTouchMove, { passive: false });
					this._onDocTouchMove = null;
				}
				if ( this._onDocTouchEnd ) {
					document.removeEventListener('touchend',    this._onDocTouchEnd, { passive: false });
					document.removeEventListener('touchcancel', this._onDocTouchEnd, { passive: false });
					this._onDocTouchEnd = null;
				}

				this._touchDrag = false;
			},
			notify() {
				this.$emit('drag-update', {
					active:            this.active,
					candidateRow:      this.candidateRow,
					candidateGroup:    this.candidateGroup,
					candidateGroupKey: this.candidateGroupKey,
					overRow:           this.overRow,
					overGroup:         this.overGroup,
					overGroupKey:      this.overGroupKey,
					overPos:           this.overPos,
					ignoreClick:       this.ignoreClick,
				});
			},
		},
		render() {
			return null;
		},
	}
</script>

<style lang="scss">
	body.table-dragging,
	body.table-dragging * {
		cursor: grabbing !important;
		user-select: none !important;
	}

	.table-drag-ghost {
		position: fixed;
		top: 0;
		left: 0;
		display: flex;
		align-items: stretch;
		background: var(--table-body-background, #fff);
		border-radius: 0.35rem;
		box-shadow: 0 10px 24px rgba(0, 0, 0, 0.25);
		pointer-events: none;
		z-index: 99999;
		opacity: 0.95;
		max-width: 90vw;
		overflow: hidden;
	}

	.table-drag-ghost > * {
		background: var(--table-body-background, #fff);
		opacity: 1 !important;
	}

	.table-drop-indicator {
		position: fixed;
		height: 2px;
		background: var(--primary-color, #3b82f6);
		box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.35);
		pointer-events: none;
		z-index: 99998;
	}
</style>
