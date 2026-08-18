<template>

	<teleport to="body">
		<transition
			enter-active-class="transition ease-out duration-100"
			enter-from-class="opacity-0 scale-95"
			enter-to-class="opacity-100 scale-100"
			leave-active-class="transition ease-in duration-75"
			leave-from-class="opacity-100 scale-100"
			leave-to-class="opacity-0 scale-95"
			>
			<div v-if="visible"
				ref="menu"
				class="table-context-menu"
				:style="{ top: coordinates.y + 'px', left: coordinates.x + 'px' }"
				@contextmenu.prevent.stop=""
				>
				<SelectableItems
					:in_data="menu_items"
					@selectItem="(e, item) => onSelect(item)"
					/>
			</div>
		</transition>
	</teleport>

</template>

<script>
	import SelectableItems from '@/js/Elements/Forms/SelectableItems.vue';

	// Standard items, shared by every table. Each carries an `event` the host
	// table re-emits (the page already listens for onDelete / onRestore).
	const STANDARD_DELETE  = { key: 'delete',  name: 'Delete',  icon: 'material-symbols:delete-outline-rounded',           event: 'onDelete' };
	const STANDARD_RESTORE = { key: 'restore', name: 'Restore', icon: 'material-symbols:restore-from-trash-outline-rounded', event: 'onRestore' };

	export default {
		name: 'TableContextMenu',
		components: { SelectableItems },
		props: {
			// Extra, table-specific actions declared in settings.contextmenu.
			// Each: { name, icon?, event?, action?, disabled? } where `disabled`
			// is a boolean or a (row) => boolean predicate.
			actions: {
				type: Array,
				default: () => [],
			},
			// The row the menu was opened on — used to resolve per-row disabled state.
			row: {
				type: Object,
				default: () => ({}),
			},
			// Whether the host table works with the "deleted" filter (drives Delete/Restore).
			deleted_filter: {
				type: Boolean,
				default: false,
			},
			// Include the standard Delete/Restore item.
			show_delete: {
				type: Boolean,
				default: true,
			},
			// Hide the standard Delete/Restore for specific rows. Boolean or a
			// (row) => boolean predicate (e.g. collapsed receipt summaries that
			// are not standalone documents and cannot be deleted).
			hide_delete: {
				type: [Boolean, Function],
				default: false,
			},
		},
		emits: ['select'],
		data() {
			return {
				visible: false,
				coordinates: { x: 0, y: 0 },
			}
		},
		computed: {
			menu_items() {
				// TableContextMenu.menu_items
				let items = [];

				// Table-specific actions first; the standard Delete/Restore is a
				// destructive action and goes to the bottom of the menu.
				this.actions.forEach(action => {
					items.push({
						...action,
						disabled: this.resolveDisabled(action),
					});
				});

				if ( this.show_delete && !this.resolveHideDelete() ) {
					if ( this.deleted_filter && this.row?.is_deleted )
						items.push({ ...STANDARD_RESTORE });
					else
						items.push({ ...STANDARD_DELETE });
				}

				return items;
			},
		},
		methods: {
			resolveDisabled(action) {
				// TableContextMenu.resolveDisabled
				if ( typeof action.disabled === 'function' )
					return !!action.disabled(this.row);

				return !!action.disabled;
			},
			resolveHideDelete() {
				// TableContextMenu.resolveHideDelete
				if ( typeof this.hide_delete === 'function' )
					return !!this.hide_delete(this.row);

				return !!this.hide_delete;
			},
			open(x, y) {
				// TableContextMenu.open
				this.coordinates = { x, y };
				this.visible = true;

				this.$nextTick(() => {
					this.clampToViewport();
					document.addEventListener('mousedown', this.onOutside, true);
					document.addEventListener('contextmenu', this.onOutside, true);
					document.addEventListener('scroll', this.close, true);
					document.addEventListener('keydown', this.onKeydown);
				});
			},
			close() {
				// TableContextMenu.close
				if ( !this.visible )
					return;

				this.visible = false;
				document.removeEventListener('mousedown', this.onOutside, true);
				document.removeEventListener('contextmenu', this.onOutside, true);
				document.removeEventListener('scroll', this.close, true);
				document.removeEventListener('keydown', this.onKeydown);
			},
			clampToViewport() {
				// TableContextMenu.clampToViewport
				const el = this.$refs.menu;
				if ( !el )
					return;

				const rect = el.getBoundingClientRect();
				const pad = 8;
				let { x, y } = this.coordinates;

				if ( x + rect.width + pad > window.innerWidth )
					x = Math.max(pad, window.innerWidth - rect.width - pad);

				if ( y + rect.height + pad > window.innerHeight )
					y = Math.max(pad, window.innerHeight - rect.height - pad);

				this.coordinates = { x, y };
			},
			onOutside(event) {
				// TableContextMenu.onOutside
				if ( this.$refs.menu && this.$refs.menu.contains(event.target) )
					return;

				this.close();
			},
			onKeydown(event) {
				// TableContextMenu.onKeydown
				if ( event.key === 'Escape' )
					this.close();
			},
			onSelect(item) {
				// TableContextMenu.onSelect
				if ( !item || item.disabled )
					return;

				this.close();
				this.$emit('select', item);
			},
		},
		beforeUnmount() {
			this.close();
		},
	}
</script>

<style lang="scss" scoped>
	.table-context-menu {
		position: fixed;
		z-index: 1000;
		min-width: 12rem;
		border-radius: 0.375rem;
		background-color: var(--dropdown-background-color);
		box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
		overflow: hidden;
	}
</style>
