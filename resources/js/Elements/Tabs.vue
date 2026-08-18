<template>
	<div class="h-full flex flex-col space-y-3 min-w-0">
		<div ref="bar" class="tabs-bar flex border-b min-w-0" style="border-color: var(--border-color)">
			<component
				:is="useLinks ? 'a' : 'button'"
				v-for="tab in visibleTabs"
				:key="tab.id"
				class="tab-btn shrink-0"
				:class="{ 'tab-btn--active': modelValue === tab.id, 'tab-btn--disabled': tab.disabled }"
				:href="useLinks && !tab.disabled ? tabHref(tab) : null"
				:disabled="useLinks ? null : tab.disabled"
				:aria-disabled="tab.disabled ? 'true' : null"
				@click="onTabClick(tab, $event)"
			>
				<span class="hidden sm:inline">{{ $t(tab.label) }}</span>
				<span class="sm:hidden">{{ $t(tab.label_mobile || tab.label) }}</span>
			</component>

			<div v-if="overflowTabs.length" class="tab-more shrink-0">
				<button
					class="tab-btn tab-more-btn"
					@click="toggleMenu"
				>
					<Icon class="icon" icon="ph:dots-three-bold" />
				</button>
				<div v-if="menuOpen" class="tab-more-menu ">
					<component
						:is="useLinks ? 'a' : 'button'"
						v-for="tab in overflowTabs"
						:key="tab.id"
						class="tab-more-item"
						:class="{ 'tab-more-item--active': modelValue === tab.id, 'tab-more-item--disabled': tab.disabled }"
						:href="useLinks && !tab.disabled ? tabHref(tab) : null"
						:disabled="useLinks ? null : tab.disabled"
						:aria-disabled="tab.disabled ? 'true' : null"
						@click="onTabClick(tab, $event)"
					>
						{{ $t(tab.label) }}
					</component>
				</div>
			</div>
		</div>

		<div ref="measurer" class="tabs-measurer" aria-hidden="true">
			<button
				v-for="tab in tabs"
				:key="tab.id"
				ref="measureBtns"
				class="tab-btn"
			>
				<span class="hidden sm:inline">{{ $t(tab.label) }}</span>
				<span class="sm:hidden">{{ $t(tab.label_mobile || tab.label) }}</span>
			</button>
			<button ref="measureMore" class="tab-btn tab-more-btn">
				<Icon class="icon" icon="ph:dots-three-bold" />
			</button>
		</div>

		<div class="tab-wrapper grow min-h-0 px-1 lg:px-2">
		<!-- <div class="page-content-inner-scroller grow overflow-y-hidden scrolled-wrapper scrollbar  flex-col space-y-4"> -->
			<template v-for="tab in tabs" :key="tab.id">
				<!-- h-full: панель таба заполняет .tab-wrapper (grow min-h-0), чтобы
				     потомки могли получить ограниченную высоту (напр. таблица с
				     собственным скроллом и sticky-шапкой в CashflowReport). -->
				<div v-if="everActivated[tab.id]" v-show="modelValue === tab.id" class="h-full">
					<slot :name="tab.id" />
				</div>
			</template>
		</div>
	</div>
</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'Tabs',
		components: {
			Icon,
		},
		props: {
			tabs: {
				type: Array,
				required: true,
			},
			modelValue: {
				type: String,
				default: '',
			},
			storageKey: {
				type: String,
				default: null,
			},
		},
		emits: ['update:modelValue', 'tab-change'],
		data() {
			return {
				everActivated: {},
				visibleCount: 0,
				menuOpen: false,
				resizeObserver: null,
			}
		},
		computed: {
			// Раскладка табов на видимые и скрытые. Если активный таб попал в скрытые —
			// меняем его местами с последним видимым, чтобы выбранный всегда был в строке.
			arrangedTabs() {
				const visible = this.tabs.slice(0, this.visibleCount);
				const overflow = this.tabs.slice(this.visibleCount);

				if (this.visibleCount > 0 && overflow.length) {
					const activeIdx = overflow.findIndex(t => t.id === this.modelValue);
					if (activeIdx !== -1) {
						const displaced = visible[visible.length - 1];
						visible[visible.length - 1] = overflow[activeIdx];
						overflow[activeIdx] = displaced;
					}
				}

				return { visible, overflow };
			},
			visibleTabs() {
				return this.arrangedTabs.visible;
			},
			overflowTabs() {
				return this.arrangedTabs.overflow;
			},
			localeLabels() {
				return this.tabs.map(t => this.$t(t.label)).join('|') + '@' + (this.$i18n?.locale || '');
			},
			// Табы рендерятся ссылками (<a>) только когда у группы есть storageKey —
			// тогда активный таб кодируется в URL и работает нативное «Открыть в новой
			// вкладке» браузера. Без storageKey (напр. табы в карточках-модалках) —
			// обычные <button>, прежнее поведение.
			useLinks() {
				return !!this.storageKey;
			},
			// Имя query-параметра, в котором хранится активный таб. storageKey уникален
			// для страницы, поэтому несколько групп табов не конфликтуют.
			queryKey() {
				return this.storageKey || 'tab';
			},
		},
		watch: {
			modelValue: {
				immediate: true,
				handler(id) {
					if (id) this.everActivated = { ...this.everActivated, [id]: true };
				}
			},
			tabs() {
				this.$nextTick(this.recomputeOverflow);
			},
			localeLabels() {
				this.$nextTick(this.recomputeOverflow);
			},
		},
		mounted() {
			this.visibleCount = this.tabs.length;
			this.$nextTick(this.recomputeOverflow);

			if (typeof ResizeObserver !== 'undefined' && this.$refs.bar) {
				this.resizeObserver = new ResizeObserver(() => this.recomputeOverflow());
				this.resizeObserver.observe(this.$refs.bar);
			}
			document.addEventListener('click', this.onDocumentClick);

			// Активный таб из URL имеет приоритет (открыли ссылку в новой вкладке),
			// иначе восстанавливаем сохранённый в настройках.
			if (!this.applyQueryTab())
				this.restoreSavedTab();
		},
		beforeUnmount() {
			if (this.resizeObserver) {
				this.resizeObserver.disconnect();
				this.resizeObserver = null;
			}
			document.removeEventListener('click', this.onDocumentClick);
		},
		methods: {
			// Ссылка на текущую страницу с активным табом в query — для нативного
			// «Открыть в новой вкладке», ctrl/⌘+click и средней кнопки мыши.
			tabHref(tab) {
				if (typeof window === 'undefined') return null;
				const url = new URL(window.location.href);
				url.searchParams.set(this.queryKey, tab.id);
				return url.pathname + url.search + url.hash;
			},
			// Левый клик без модификаторов — обычное переключение таба (SPA, без
			// перезагрузки). С ctrl/⌘/shift или средней кнопкой — не мешаем браузеру
			// открыть ссылку в новой вкладке/окне.
			onTabClick(tab, e) {
				if (e && (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1)) return;
				if (e) e.preventDefault();
				this.selectTab(tab);
			},
			// Активирует таб из query-параметра при загрузке страницы.
			applyQueryTab() {
				if (!this.useLinks || typeof window === 'undefined') return false;

				const value = new URLSearchParams(window.location.search).get(this.queryKey);
				if (value && this.tabs.some(t => t.id === value && !t.disabled)) {
					this.$emit('update:modelValue', value);
					return true;
				}
				return false;
			},
			restoreSavedTab() {
				if (!this.storageKey || !this.$settings) return;

				const accountId = String(this.$page?.props?.account?.id ?? 'default');
				const activeTabs = this.$settings.getSetting('active_tabs', {}) || {};
				const saved = activeTabs?.[accountId]?.[this.storageKey];

				if (saved && this.tabs.some(t => t.id === saved)) {
					this.$emit('update:modelValue', saved);
				}
			},
			recomputeOverflow() {
				const bar = this.$refs.bar;
				const measureBtns = this.$refs.measureBtns;
				if (!bar || !measureBtns || !measureBtns.length) return;

				const containerWidth = bar.clientWidth;
				const widths = measureBtns.map(el => el.offsetWidth);
				const total = widths.reduce((sum, w) => sum + w, 0);

				if (total <= containerWidth) {
					this.visibleCount = this.tabs.length;
					return;
				}

				const moreWidth = this.$refs.measureMore ? this.$refs.measureMore.offsetWidth : 0;
				const available = containerWidth - moreWidth;
				let used = 0;
				let count = 0;
				for (let i = 0; i < widths.length; i++) {
					if (used + widths[i] <= available) {
						used += widths[i];
						count++;
					} else {
						break;
					}
				}
				this.visibleCount = count;
			},
			toggleMenu() {
				this.menuOpen = !this.menuOpen;
			},
			onDocumentClick(e) {
				if (!this.menuOpen) return;
				if (!this.$el.contains(e.target)) this.menuOpen = false;
			},
			selectTab(tab) {
				if (tab.disabled) return;
				const id = tab.id;
				if (this.storageKey && this.$settings) {
					const accountId = String(this.$page?.props?.account?.id ?? 'default');
					const activeTabs = this.$settings.getSetting('active_tabs', {}) || {};
					if (!activeTabs[accountId]) activeTabs[accountId] = {};
					activeTabs[accountId][this.storageKey] = id;
					this.$settings.setSetting('active_tabs', activeTabs);
				}
				// Держим URL в актуальном состоянии, чтобы ПКМ/копирование ссылки и
				// перезагрузка попадали на открытый таб. state сохраняем — иначе
				// сломаем историю Inertia.
				if (this.useLinks && typeof window !== 'undefined') {
					const url = new URL(window.location.href);
					url.searchParams.set(this.queryKey, id);
					window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
				}

				this.menuOpen = false;
				this.$emit('update:modelValue', id);
				this.$emit('tab-change', id);
			}
		}
	}
</script>

<style lang="scss" scoped>
	.tabs-bar {
		flex-shrink: 0;
		position: relative;
	}

	.tab-btn {
		color: var(--text-color-secondary);
		font-size: 0.875rem;
		font-weight: 500;
		padding: 8px 16px;
		border-bottom: 2px solid transparent;
		margin-bottom: -1px;
		border-radius: 4px 4px 0 0;
		text-decoration: none;
		transition: color 0.15s, background 0.15s;

		&:hover {
			color: var(--text-color);
			background: var(--background-light-color);
		}

		&--active {
			border-bottom-color: var(--brand-background);
		}

		&--disabled {
			opacity: 0.4;
			cursor: not-allowed;

			&:hover {
				color: var(--text-color-secondary);
				background: transparent;
			}
		}
	}

	.tab-more {
		position: relative;
		display: flex;
		align-items: stretch;
		margin-left: auto;
	}

	.tab-more-btn {
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.tab-more-menu {
		position: absolute;
		top: 100%;
		right: 0;
		z-index: 1100;
		margin-top: 4px;
		min-width: 180px;
		display: flex;
		flex-direction: column;
		align-items: stretch;
		padding: 4px;
		border-radius: 0.375rem;
		background-color: var(--dropdown-background-color);
		border: 1px solid var(--border-color);
		box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
	}

	.tab-more-item {
		text-align: left;
		color: var(--text-color-secondary);
		font-size: 0.875rem;
		font-weight: 500;
		padding: 8px 12px;
		border-radius: 0.25rem;
		text-decoration: none;
		transition: color 0.15s, background 0.15s;

		&:hover {
			color: var(--text-color);
			background: var(--background-light-color);
		}

		&--active {
			color: var(--text-color);
			background: var(--background-light-color);
		}

		&--disabled {
			opacity: 0.4;
			cursor: not-allowed;

			&:hover {
				color: var(--text-color-secondary);
				background: transparent;
			}
		}
	}

	.tabs-measurer {
		position: absolute;
		visibility: hidden;
		pointer-events: none;
		height: 0;
		overflow: hidden;
		display: flex;
		white-space: nowrap;
	}
</style>
