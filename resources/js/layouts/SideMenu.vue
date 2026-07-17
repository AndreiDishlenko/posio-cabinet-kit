<template>

	<!--
		Collapsible rail-style side menu (icons-only rail, pins open on click,
		pushes content when pinned). Mobile (<1024px): slides in as an overlay.
		Genericized from posio.cabinet's Gemini-style menu — no product tour,
		no per-account persisted expand state (host can add that back via an
		override if it needs the extra polish).
	-->
	<div class="ck-shell" :class="{ 'is-pinned': !isFolded, 'is-pullout': isPullout, 'is-expanded': isExpanded }">

		<div ref="panel" class="ck-panel">

			<div class="ck-header">
				<slot name="brand">
					<span class="ck-brand-fallback">{{ $t ? $t('Cabinet') : 'Cabinet' }}</span>
				</slot>

				<button type="button" class="ck-toggle" @click="togglePinned">
					<Icon icon="material-symbols:left-panel-close-outline-rounded" class="ck-icon"/>
				</button>
			</div>

			<nav class="ck-nav">
				<div v-for="(group, groupIndex) in in_data" :key="groupIndex" class="ck-group">

					<div class="ck-group-label">{{ $t ? $t(group.label) : group.label }}</div>

					<ul class="ck-group-list">
						<li v-for="item in group.children" :key="item.id"
							class="ck-item"
							:class="{ 'is-active': item.id === current_id }"
							>
							<Link v-if="item.route" class="ck-link" :href="route(item.route)">
								<Icon :icon="item.icon" class="ck-icon"/>
								<span class="ck-label">{{ $t ? $t(item.label) : item.label }}</span>
							</Link>
							<a v-else-if="item.link" class="ck-link" :href="item.link">
								<Icon :icon="item.icon" class="ck-icon"/>
								<span class="ck-label">{{ $t ? $t(item.label) : item.label }}</span>
							</a>
						</li>
					</ul>

				</div>
			</nav>

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
				type: Array,
				default: () => [],
			},
			current_id: {
				type: [Number, String],
				default: null,
			},
		},
		data() {
			return {
				isFolded: true,
				isPullout: false,
			}
		},
		computed: {
			isExpanded() {
				return !this.isFolded || this.isPullout;
			},
		},
		created() {
			const saved = localStorage.getItem('cabinetKitSideMenuState');
			if (saved === 'false') this.isFolded = false;
			if (saved === 'true') this.isFolded = true;

			this.$emitter?.on('ck_burger_click', this.onBurger);
		},
		beforeUnmount() {
			this.$emitter?.off('ck_burger_click', this.onBurger);
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
		},
	}
</script>

<style lang="scss" scoped>
	.ck-shell {
		position: relative;
		flex-shrink: 0;
		height: 100%;
		width: var(--ck-rail-width, 64px);
		z-index: 40;
		transition: width .2s ease;
	}

	.ck-shell.is-pinned {
		width: var(--ck-expanded-width, 240px);
	}

	@media (max-width: 1023.98px) {
		.ck-shell, .ck-shell.is-pinned {
			width: 0;
		}
	}

	.ck-panel {
		position: absolute;
		inset-inline-start: 0;
		top: 0;
		height: 100%;
		width: var(--ck-rail-width, 64px);
		display: flex;
		flex-direction: column;
		background: var(--ck-sidemenu-bg, #f0f4f9);
		overflow-x: hidden;
		overflow-y: auto;
		transition: width .2s ease, transform .2s ease;
	}

	.ck-shell.is-pinned .ck-panel {
		width: var(--ck-expanded-width, 240px);
	}

	@media (max-width: 1023.98px) {
		.ck-panel {
			width: var(--ck-expanded-width, 240px);
			transform: translateX(-100%);
			z-index: 10000;
		}
		.ck-shell.is-pullout .ck-panel {
			transform: translateX(0);
			box-shadow: 0 8px 24px rgba(0, 0, 0, .18);
		}
	}

	.ck-header {
		height: var(--ck-header-height, 60px);
		display: flex;
		align-items: center;
		padding-inline: .5rem;
		flex-shrink: 0;
	}

	.ck-toggle {
		margin-inline-start: auto;
		opacity: 0;
		pointer-events: none;
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: .35rem;
	}

	.ck-shell.is-expanded .ck-toggle {
		opacity: 1;
		pointer-events: auto;
	}

	.ck-toggle:hover {
		background: var(--ck-item-hover-bg, #e1e3e6);
	}

	.ck-nav {
		padding: .25rem .5rem .75rem;
		flex: 1 1 auto;
	}

	.ck-group {
		margin-top: .5rem;
	}

	.ck-group-label {
		font-size: .7rem;
		font-weight: 500;
		opacity: 0;
		padding: .3rem .5rem;
		color: var(--ck-group-label-color, #5f6368);
		transition: opacity .2s ease;
	}

	.ck-shell.is-expanded .ck-group-label {
		opacity: 1;
	}

	.ck-group-list {
		list-style: none;
		margin: 0;
		padding: 0;
		display: flex;
		flex-direction: column;
		gap: .1rem;
	}

	.ck-link {
		display: flex;
		align-items: center;
		height: 40px;
		border-radius: .5rem;
		color: var(--ck-item-color, #444746);
		text-decoration: none;
		overflow: hidden;
		padding-inline: .6rem;
		transition: background-color .2s ease, color .2s ease;
	}

	.ck-icon {
		width: 20px;
		height: 20px;
		flex: 0 0 auto;
	}

	.ck-label {
		margin-inline-start: .6rem;
		white-space: nowrap;
		opacity: 0;
		transition: opacity .2s ease;
	}

	.ck-shell.is-expanded .ck-label {
		opacity: 1;
	}

	.ck-link:hover {
		background: var(--ck-item-hover-bg, #e1e3e6);
	}

	.ck-item.is-active .ck-link {
		background: var(--ck-item-active-bg, #d3e3fd);
		color: var(--ck-item-active-color, #0842a0);
	}
</style>
