<script>
	// Shared copy-to-clipboard handler for Table.vue and Tabledit.vue.
	//
	// Listens for `copy` on the parent's $refs[<target_ref>] (defaults to "table")
	// and rewrites the clipboard payload so multi-cell selections paste into
	// Excel / Google Sheets as a proper grid (TSV + <table> HTML) instead of a
	// vertical list.
	//
	// How it figures out the selection:
	//   1. Take window.getSelection() bounding rect.
	//   2. Walk every `.table-row > .table-cell` and keep cells whose rect overlaps.
	//   3. Group by their parent `.table-row` (rows in document order).
	//   4. Extract value per cell: input.value → checkbox checked → textContent.
	//
	// Falls through to native copy when the user is just selecting text inside a
	// single input (so plain Ctrl+C in one field still works).

	export default {
		name: 'TableCopyHandler',
		props: {
			target_ref: {
				type: String,
				default: 'table',
			},
		},
		mounted() {
			// TableCopyHandler.mounted
			this.attachTo(this.$parent?.$refs?.[this.target_ref]);
		},
		beforeUnmount() {
			this.detach();
		},
		methods: {
			attachTo(el) {
				// TableCopyHandler.attachTo
				this.detach();
				if ( !el )
					return;

				this._target = el;
				el.addEventListener('copy', this.onCopy);
			},
			detach() {
				if ( !this._target )
					return;

				this._target.removeEventListener('copy', this.onCopy);
				this._target = null;
			},

			onCopy(e) {
				// TableCopyHandler.onCopy
				const sel = window.getSelection();
				if ( !sel || sel.rangeCount === 0 || sel.isCollapsed )
					return;

				// Plain text-selection inside a single input → don't override native copy
				const active = document.activeElement;
				if ( active && active.tagName === 'INPUT' &&
					this._target.contains(active) &&
					active.selectionStart !== active.selectionEnd ) {
					const txt = sel.toString();
					if ( !txt.includes('\n') )
						return;
				}

				const range   = sel.getRangeAt(0);
				const selRect = range.getBoundingClientRect();
				if ( !selRect.width && !selRect.height )
					return;

				const rows = Array.from(this._target.querySelectorAll('.table-row'));
				const tsvRows  = [];
				const htmlRows = [];

				rows.forEach(rowEl => {
					const cells = Array.from(rowEl.querySelectorAll(':scope > .table-cell'));
					const hit   = cells.filter(c => this.rectsOverlap(c.getBoundingClientRect(), selRect));
					if ( !hit.length )
						return;

					const tsvCells  = [];
					const htmlCells = [];
					hit.forEach(c => {
						const val = this.cellValue(c);
						tsvCells.push(String(val).replace(/[\t\n\r]/g, ' '));
						htmlCells.push(`<td>${this.escapeHtml(val)}</td>`);
					});
					tsvRows.push(tsvCells.join('\t'));
					htmlRows.push(`<tr>${htmlCells.join('')}</tr>`);
				});

				if ( !tsvRows.length )
					return;

				e.clipboardData.setData('text/plain', tsvRows.join('\n'));
				e.clipboardData.setData('text/html',  `<table>${htmlRows.join('')}</table>`);
				e.preventDefault();
			},

			cellValue(cellEl) {
				// TableCopyHandler.cellValue
				const checkbox = cellEl.querySelector('input[type="checkbox"]');
				if ( checkbox )
					return checkbox.checked ? '1' : '0';

				const input = cellEl.querySelector('input, textarea');
				if ( input )
					return input.value ?? '';

				return (cellEl.textContent ?? '').trim();
			},

			rectsOverlap(a, b) {
				return !(a.right < b.left || a.left > b.right || a.bottom < b.top || a.top > b.bottom);
			},

			escapeHtml(s) {
				return String(s ?? '')
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;');
			},
		},
		render() {
			return null;
		},
	}
</script>
