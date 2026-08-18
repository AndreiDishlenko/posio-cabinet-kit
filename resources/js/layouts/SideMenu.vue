<template>
	<div
		ref="shell"
		class="ck-shell"
		:class="{
			'is-pinned': !isFolded,
			'is-pullout': isPullout,
			'is-expanded': isExpanded,
		}"
	>
		<div ref="panel" class="ck-panel">
			<div class="ck-panel-scroll">
				<div class="ck-header">
					<button type="button" class="ck-brand" @click="onBurger">
						<slot name="brand">
							<span class="ck-brand-mark">C</span>
							<span class="ck-brand-label">{{ translate('Cabinet') }}</span>
						</slot>
					</button>

					<button type="button" class="ck-toggle" :aria-label="translate('Collapse menu')" @click="onBurger">
						<Icon icon="material-symbols:left-panel-close-outline-rounded" class="ck-icon"/>
					</button>
				</div>

				<nav class="ck-nav">
					<div v-for="(group, groupIndex) in normalizedMenu" :key="group.id ?? groupIndex" class="ck-group">
						<button
							v-if="group.label"
							type="button"
							class="ck-group-label"
							:aria-expanded="isGroupExpanded(groupIndex)"
							@click="toggleGroup(groupIndex)"
						>
							<span class="ck-group-label-text">{{ translate(group.label) }}</span>
							<Icon icon="ep:arrow-down-bold" class="ck-group-arrow" :class="{ 'is-open': isGroupExpanded(groupIndex) }"/>
						</button>

						<transition name="ck-collapse">
							<ul v-if="isGroupExpanded(groupIndex)" class="ck-group-list">
								<li
									v-for="item in group.children"
									:key="item.id ?? item.route ?? item.link"
									class="ck-item"
									:class="{ 'is-active': item.id == current_id, 'is-disabled': !item.route && !item.link }"
								>
									<Link v-if="item.route" class="ck-link" :href="route(item.route)">
										<Icon :icon="item.icon || 'mdi:circle-medium'" class="ck-icon"/>
										<span class="ck-label">{{ translate(item.label) }}</span>
									</Link>

									<a v-else-if="item.link" class="ck-link" :href="item.link">
										<Icon :icon="item.icon || 'mdi:circle-medium'" class="ck-icon"/>
										<span class="ck-label">{{ translate(item.label) }}</span>
									</a>

									<span v-else class="ck-link">
										<Icon :icon="item.icon || 'mdi:circle-medium'" class="ck-icon"/>
										<span class="ck-label">{{ translate(item.label) }}</span>
									</span>
								</li>
							</ul>
						</transition>
					</div>
				</nav>
			</div>
		</div>
	</div>
</template>

<script>
	import { Link } from '@inertiajs/vue3';
	import { Icon } from '@iconify/vue';

	export default {
		name: 'SideMenu',
		components: { Link, Icon },
		props: {
			in_data: {
				type: [Array, Object],
				default: () => [],
			},
			current_id: {
				type: [Number, String],
				default: null,
			},
			active_account_id: {
				type: [Number, String],
				default: null,
			},
		},
		data() {
			return {
				isFolded: true,
				isPullout: false,
				collapsedGroups: [],
			}
		},
		computed: {
			normalizedMenu() {
				return Array.isArray(this.in_data) ? this.in_data : Object.values(this.in_data ?? {});
			},
			isExpanded() {
				return !this.isFolded || this.isPullout;
			},
			activeGroupIndex() {
				if (!this.current_id) return -1;

				return this.normalizedMenu.findIndex(group =>
					group.children?.some(item => item.id == this.current_id)
				);
			},
			storageSuffix() {
				return this.active_account_id ? `:${this.active_account_id}` : '';
			},
		},
		watch: {
			active_account_id() {
				this.restoreCollapsedGroups();
			},
		},
		created() {
			const saved = localStorage.getItem('cabinetKitSideMenuState');
			if (saved === 'false') this.isFolded = false;
			if (saved === 'true') this.isFolded = true;

			this.restoreCollapsedGroups();

			this.$emitter?.on('ck_burger_click', this.onBurger);
			this.$emitter?.on('burger_button_click', this.onBurger);
			this.$emitter?.on('open_side_menu', this.openSideMenu);
			this.$emitter?.on('close_side_menu', this.closeSideMenu);
		},
		mounted() {
			document.addEventListener('click', this.handleClickOutside);
		},
		beforeUnmount() {
			document.removeEventListener('click', this.handleClickOutside);
			this.$emitter?.off('ck_burger_click', this.onBurger);
			this.$emitter?.off('burger_button_click', this.onBurger);
			this.$emitter?.off('open_side_menu', this.openSideMenu);
			this.$emitter?.off('close_side_menu', this.closeSideMenu);
		},
		methods: {
			onBurger() {
				if (window.innerWidth < 1024) {
					this.isPullout = !this.isPullout;
					return;
				}

				this.togglePinned();
			},
			togglePinned() {
				this.isFolded = !this.isFolded;
				localStorage.setItem('cabinetKitSideMenuState', this.isFolded);
			},
			openSideMenu() {
				if (window.innerWidth < 1024) {
					this.isPullout = true;
					return;
				}

				this.isFolded = false;
				localStorage.setItem('cabinetKitSideMenuState', this.isFolded);
			},
			closeSideMenu() {
				this.isPullout = false;
			},
			handleClickOutside(event) {
				if (!this.isPullout || this.$refs.shell?.contains(event.target)) return;

				this.isPullout = false;
			},
			isGroupExpanded(groupIndex) {
				return groupIndex === this.activeGroupIndex || !this.collapsedGroups.includes(groupIndex);
			},
			toggleGroup(groupIndex) {
				if (!this.isExpanded || groupIndex === this.activeGroupIndex) return;

				if (this.collapsedGroups.includes(groupIndex)) {
					this.collapsedGroups = this.collapsedGroups.filter(index => index !== groupIndex);
				} else {
					this.collapsedGroups = [...this.collapsedGroups, groupIndex];
				}

				localStorage.setItem(this.groupStorageKey(), JSON.stringify(this.collapsedGroups));
			},
			restoreCollapsedGroups() {
				try {
					const saved = localStorage.getItem(this.groupStorageKey());
					this.collapsedGroups = saved ? JSON.parse(saved) : [];
				} catch {
					this.collapsedGroups = [];
				}
			},
			groupStorageKey() {
				return `cabinetKitCollapsedGroups${this.storageSuffix}`;
			},
			translate(value) {
				return this.$t ? this.$t(value) : value;
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-shell {
		position: relative;
		z-index: 40;
		flex: 0 0 auto;
		width: var(--ck-rail-width);
		height: 100%;
		font-family: var(--ck-font, inherit);
		transition: width var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-shell.is-pinned {
		width: var(--ck-expanded-width);
	}

	.ck-panel {
		position: absolute;
		inset-inline-start: 0;
		top: 0;
		width: var(--ck-rail-width);
		height: 100%;
		background: var(--ck-sidemenu-bg);
		overflow: hidden;
		transition:
			width var(--ck-ease-dur) var(--ck-ease),
			transform var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-shell.is-expanded .ck-panel {
		width: var(--ck-expanded-width);
	}

	.ck-panel-scroll {
		display: flex;
		flex-direction: column;
		height: 100%;
		overflow-x: hidden;
		overflow-y: auto;
		scrollbar-width: none;
	}

	.ck-panel-scroll::-webkit-scrollbar {
		display: none;
	}

	.ck-header {
		position: relative;
		display: flex;
		align-items: center;
		height: var(--ck-header-height);
		padding: 0 var(--ck-panel-pad);
		flex: 0 0 auto;
	}

	.ck-brand {
		position: relative;
		display: flex;
		align-items: center;
		width: 100%;
		height: var(--ck-item-height);
		border: 0;
		padding: 0 10px;
		background: transparent;
		color: var(--ck-item-color);
		cursor: pointer;
		text-align: start;
		border-radius: var(--ck-item-radius);
		overflow: hidden;
	}

	.ck-brand:hover,
	.ck-toggle:hover,
	.ck-group-label:hover,
	.ck-link:hover {
		background-color: var(--ck-item-hover-bg);
	}

	.ck-brand-mark {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 20px;
		height: 20px;
		flex: 0 0 auto;
		border-radius: 6px;
		background: var(--ck-brand-bg);
		color: var(--ck-brand-color);
		font-size: 12px;
		font-weight: 700;
	}

	.ck-brand-label {
		display: none;
		min-width: 0;
		margin-left: 12px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		font-weight: 600;
		opacity: 0;
		transition: opacity var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-shell.is-expanded .ck-brand-label {
		display: block;
		opacity: 1;
	}

	.ck-toggle {
		position: absolute;
		right: var(--ck-panel-pad);
		top: 0;
		bottom: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		width: var(--ck-item-height);
		height: var(--ck-item-height);
		margin: auto 0;
		border: 0;
		border-radius: var(--ck-item-radius);
		background: transparent;
		color: var(--ck-item-color);
		cursor: pointer;
		opacity: 0;
		pointer-events: none;
		transition:
			background-color var(--ck-ease-dur) var(--ck-ease),
			opacity var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-shell.is-expanded .ck-toggle {
		opacity: 1;
		pointer-events: auto;
	}

	.ck-nav {
		padding: 4px var(--ck-panel-pad) 12px;
		flex: 1 1 auto;
	}

	.ck-group {
		margin-top: 8px;
	}

	.ck-group-label {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
		width: 100%;
		margin: 12px 0 4px;
		padding: 6px 10px;
		border: 0;
		border-radius: var(--ck-item-radius);
		background: transparent;
		color: var(--ck-group-label-color);
		cursor: pointer;
		font-size: 12px;
		font-weight: 500;
		line-height: 1.4;
		text-align: start;
		opacity: 0;
		pointer-events: none;
		transition:
			background-color var(--ck-ease-dur) var(--ck-ease),
			opacity var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-shell.is-expanded .ck-group-label {
		opacity: 1;
		pointer-events: auto;
	}

	.ck-group-label-text {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.ck-group-arrow {
		width: 12px;
		height: 12px;
		flex: 0 0 auto;
		transition: transform var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-group-arrow.is-open {
		transform: rotate(180deg);
	}

	.ck-collapse-enter-active,
	.ck-collapse-leave-active {
		overflow: hidden;
		transition:
			max-height var(--ck-ease-dur) var(--ck-ease),
			opacity var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-collapse-enter-from,
	.ck-collapse-leave-to {
		max-height: 0;
		opacity: 0;
	}

	.ck-collapse-enter-to,
	.ck-collapse-leave-from {
		max-height: 600px;
		opacity: 1;
	}

	.ck-group-list {
		display: flex;
		flex-direction: column;
		gap: 2px;
		list-style: none;
		margin: 0;
		padding: 0;
	}

	.ck-item {
		list-style: none;
	}

	.ck-link {
		display: flex;
		align-items: center;
		width: 100%;
		height: var(--ck-item-height);
		box-sizing: border-box;
		border-radius: var(--ck-item-radius);
		padding: 0 10px;
		color: var(--ck-item-color);
		cursor: pointer;
		text-decoration: none;
		transition:
			background-color var(--ck-ease-dur) var(--ck-ease),
			color var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-icon {
		width: 20px;
		height: 20px;
		flex: 0 0 auto;
		color: inherit;
	}

	.ck-label {
		display: none;
		flex: 0 1 auto;
		min-width: 0;
		margin-left: 12px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
		line-height: 1.4;
		opacity: 0;
		transition: opacity var(--ck-ease-dur) var(--ck-ease);
	}

	.ck-shell.is-expanded .ck-label {
		display: block;
		opacity: 1;
	}

	.ck-item.is-active .ck-link,
	.ck-item.is-active .ck-link:hover {
		background-color: var(--ck-item-active-bg);
		color: var(--ck-item-active-color);
	}

	.ck-item.is-disabled .ck-link {
		opacity: .4;
		pointer-events: none;
		cursor: default;
	}

	@media (max-width: 1023.98px) {
		.ck-shell,
		.ck-shell.is-pinned {
			width: 0;
		}

		.ck-panel,
		.ck-shell.is-expanded .ck-panel {
			width: var(--ck-expanded-width);
			transform: translateX(-100%);
			z-index: 10000;
		}

		.ck-shell.is-pullout .ck-panel {
			transform: translateX(0);
			box-shadow: 0 8px 24px rgba(0, 0, 0, .22);
		}
	}
</style>
