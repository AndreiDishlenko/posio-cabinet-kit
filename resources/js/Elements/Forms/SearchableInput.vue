<template>

	<label ref="root" class="searchable-input relative block">

		<Icon icon="mdi:magnify" class="icon absolute left-3 top-1/2 -translate-y-1/2" />

		<input
			ref="input"
			type="text"
			:value="modelValue"
			:placeholder="$t(placeholder)"
			class="form-control w-full !pl-10 !pr-9"
			:class="size ? `form-control-${size}` : ''"
			@input="onInput"
			@change="$emit('change', $event)"
			@keydown.down.prevent="onArrowDown"
			@keydown.up.prevent="onArrowUp"
			@keydown.enter.prevent="onEnter"
			@keydown.esc="onEscape"
		/>

		<button
			v-if="modelValue"
			type="button"
			class="absolute right-2 top-1/2 -translate-y-1/2 p-1 hover:opacity-70"
			:aria-label="$t('Clear search')"
			@click="onClear"
		>
			<Icon icon="mdi:close" class="icon icon-md" />
		</button>

		<Teleport to="body">
			<div
				v-if="dropdownVisible && suggestions.length"
				ref="dropdown"
				class="search-dropdown fixed z-[9999] rounded-md shadow-lg w-max max-w-md overflow-y-auto overflow-x-hidden scrollbar-thin"
				:style="dropdownStyle"
			>
				<div
					v-for="(s, idx) in suggestions"
					:key="s.id"
					class="search-dropdown-item px-3 py-2 cursor-pointer whitespace-nowrap"
					:class="{ 'is-active': idx === activeIndex }"
					@mousemove="onItemHover(idx)"
					@mousedown.prevent="$emit('select', s)"
				>
					{{ s[textField] }}
				</div>
			</div>
		</Teleport>

	</label>

</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'SearchableInput',

		components: { Icon },

		emits: ['update:modelValue', 'change', 'clear', 'select', 'blur'],

		props: {
			modelValue: {
				type: String,
				default: '',
			},
			placeholder: {
				type: String,
				default: 'Search by name',
			},
			size: {
				type: String,
				default: '',
			},
			suggestions: {
				type: Array,
				default: () => [],
			},
			dropdownVisible: {
				type: Boolean,
				default: false,
			},
			textField: {
				type: String,
				default: 'name',
			},
		},

		data() {
			return {
				activeIndex: -1,
				dropdownStyle: {},
			};
		},

		watch: {
			suggestions() {
				this.activeIndex = -1;
				if (this.dropdownVisible) this.$nextTick(this.updateDropdownPosition);
			},
			dropdownVisible(val) {
				if (!val) {
					this.activeIndex = -1;
				} else {
					this.$nextTick(this.updateDropdownPosition);
				}
			},
		},

		mounted() {
			this._onDocumentMousedown = (e) => {
				const root = this.$refs.root;
				const dropdown = this.$refs.dropdown;
				if (root && root.contains(e.target)) return;
				if (dropdown && dropdown.contains(e.target)) return;
				this.$emit('blur');
			};
			document.addEventListener('mousedown', this._onDocumentMousedown);

			this._onReposition = () => {
				if (this.dropdownVisible) this.updateDropdownPosition();
			};
			window.addEventListener('scroll', this._onReposition, true);
			window.addEventListener('resize', this._onReposition);
		},

		beforeUnmount() {
			document.removeEventListener('mousedown', this._onDocumentMousedown);
			window.removeEventListener('scroll', this._onReposition, true);
			window.removeEventListener('resize', this._onReposition);
		},

		methods: {
			focus() {
				this.$refs.input?.focus();
			},
			onInput(event) {
				this.$emit('update:modelValue', event.target.value);
			},
			onClear() {
				this.$emit('update:modelValue', '');
				this.$emit('clear');
				this.$refs.input?.focus();
			},
			updateDropdownPosition() {
				const root = this.$refs.root;
				if (!root) return;
				const rect = root.getBoundingClientRect();

				const gap    = 4;  // відступ між інпутом і дропдауном
				const margin = 8;  // мінімальний відступ від краю екрана

				const spaceBelow = window.innerHeight - rect.bottom - gap - margin;
				const spaceAbove = rect.top - gap - margin;

				// Розгортаємо вгору лише якщо знизу замало місця, а зверху помітно більше
				const openUp = spaceBelow < 160 && spaceAbove > spaceBelow;

				// Обмежуємо висоту так, щоб нижній (або верхній) край не виходив за екран
				const maxHeight = Math.max(120, Math.floor(openUp ? spaceAbove : spaceBelow));

				const style = {
					left:      `${rect.left}px`,
					minWidth:  `${rect.width}px`,
					maxHeight: `${maxHeight}px`,
				};

				if (openUp) {
					style.bottom = `${window.innerHeight - rect.top + gap}px`;
					style.top    = 'auto';
				} else {
					style.top    = `${rect.bottom + gap}px`;
					style.bottom = 'auto';
				}

				this.dropdownStyle = style;
			},
			onArrowDown() {
				if (!this.dropdownVisible || !this.suggestions.length) return;
				this.activeIndex = (this.activeIndex + 1) % this.suggestions.length;
				this.scrollActiveIntoView();
			},
			onArrowUp() {
				if (!this.dropdownVisible || !this.suggestions.length) return;
				this.activeIndex = this.activeIndex <= 0
					? this.suggestions.length - 1
					: this.activeIndex - 1;
				this.scrollActiveIntoView();
			},
			onEnter() {
				if (!this.dropdownVisible || !this.suggestions.length) return;
				const idx = this.activeIndex >= 0 ? this.activeIndex : 0;
				this.$emit('select', this.suggestions[idx]);
			},
			onEscape() {
				this.$emit('blur');
			},
			onItemHover(idx) {
				// Ігноруємо hover, що виникає під час прокрутки клавіатурою:
				// scroll зсуває елементи під нерухомим курсором і викликає mouseenter/mousemove,
				// що збиває activeIndex. Реагуємо тільки на реальний рух миші.
				if (this._suppressHoverUntil && performance.now() < this._suppressHoverUntil) return;
				this.activeIndex = idx;
			},
			scrollActiveIntoView() {
				this.$nextTick(() => {
					const dropdown = this.$refs.dropdown;
					if (!dropdown) return;
					const el = dropdown.children?.[this.activeIndex];
					if (!el) return;

					const elTop      = el.offsetTop;
					const elBottom   = elTop + el.offsetHeight;
					const viewTop    = dropdown.scrollTop;
					const viewBottom = viewTop + dropdown.clientHeight;

					if (elTop < viewTop)         dropdown.scrollTop = elTop;
					else if (elBottom > viewBottom) dropdown.scrollTop = elBottom - dropdown.clientHeight;

					this._suppressHoverUntil = performance.now() + 200;
				});
			},
		},
	};
</script>

<style lang="scss" scoped>
	.search-dropdown {
		background-color: var(--selectable-background-color, var(--dropdown-background-color, var(--card-background)));
		border: 1px solid var(--card-divider, rgba(148, 163, 184, 0.2));
	}

	.search-dropdown-item:hover,
	.search-dropdown-item.is-active {
		background-color: var(--brand-background);
		color: var(--brand-text-color);
	}
</style>
