<template>

	<div
		class="nt-item"
		:class="[`nt-item--${item.type}`, { 'nt-item--clickable': !!item.onClick }]"
		role="status"
		aria-live="polite"
		@mouseenter="pauseTimer"
		@mouseleave="resumeTimer"
		@click="onClick"
	>

		<!-- Custom component -->
		<template v-if="item.type === 'custom' && item.component">
			<component
				:is="item.component"
				v-bind="item.props"
				:toast-id="item.id"
				@close="close"
			/>
		</template>

		<!-- Standard message -->
		<template v-else>
			<div class="nt-item__icon-wrap">
				<Icon :icon="iconName" class="icon icon-md" />
			</div>

			<div class="nt-item__body">
				<div v-if="title" class="nt-item__title">{{ title }}</div>
				<div v-if="message" class="nt-item__message" v-html="message"></div>
			</div>
		</template>

		<button
			v-if="item.closable"
			class="nt-item__close"
			:aria-label="$t ? $t('Close') : 'Close'"
			@click.stop="close"
		>
			<Icon icon="mdi:close" class="icon icon-sm" />
		</button>

		<div
			v-if="item.autoClose"
			class="nt-item__progress"
			:style="{ animationDuration: item.autoClose + 'ms', animationPlayState: paused ? 'paused' : 'running' }"
		></div>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	const DEFAULT_ICONS = {
		info: 'mdi:information',
		success: 'mdi:check-circle',
		error: 'mdi:close-circle',
		warning: 'mdi:alert-circle',
	};

	export default {
		name: 'NewToastItem',

		components: { Icon },

		props: {
			item: {
				type: Object,
				required: true,
			},
		},

		emits: ['close'],

		data() {
			return {
				paused: false,
				_timer: null,
				_remaining: 0,
				_startedAt: 0,
			};
		},

		computed: {
			iconName() {
				return this.item.icon || DEFAULT_ICONS[this.item.type] || DEFAULT_ICONS.info;
			},
					title() {
				if (!this.item.title) return '';
				return this.$t ? this.$t(this.item.title) : this.item.title;
			},
			message() {
				if (!this.item.message) return '';
				return this.$t ? this.$t(this.item.message) : this.item.message;
			},
		},

		mounted() {
			this.startTimer();
		},

		beforeUnmount() {
			this.clearTimer();
		},

		methods: {
			startTimer() {
				if (!this.item.autoClose) return;
				this._remaining = this.item.autoClose;
				this._startedAt = Date.now();
				this._timer = setTimeout(this.close, this._remaining);
			},
			pauseTimer() {
				if (!this.item.autoClose || !this._timer) return;
				clearTimeout(this._timer);
				this._timer = null;
				this._remaining -= Date.now() - this._startedAt;
				this.paused = true;
			},
			resumeTimer() {
				if (!this.item.autoClose || this._timer) return;
				if (this._remaining <= 0) { this.close(); return; }
				this._startedAt = Date.now();
				this._timer = setTimeout(this.close, this._remaining);
				this.paused = false;
			},
			clearTimer() {
				if (this._timer) clearTimeout(this._timer);
				this._timer = null;
			},
			close() {
				this.clearTimer();
				this.$emit('close', this.item.id);
			},
			onClick(e) {
				if (!this.item.onClick) return;
				try { this.item.onClick(e, this.item); } catch (_) {}
				if (this.item.closeOnClick !== false) this.close();
			},
		},
	};
</script>

<style lang="scss">
	// Не-scoped: темы и CSS-переменные (работает через Teleport)

	// --- Theme: light (default) ---
	.nt-item {
		--nt-bg:       rgba(255, 255, 255, .82);
		--nt-fg:       #374151;
		--nt-fg-muted: #9ca3af;
		--nt-border:   rgba(15, 23, 42, .10);
		--nt-shadow:   0 2px 12px rgba(0, 0, 0, .10), 0 1px 3px rgba(0, 0, 0, .06);
	}

	// --- Theme: dark (через data-theme на стеке) ---
	.nt-stack[data-theme="dark"] .nt-item {
		--nt-bg:       rgba(26, 34, 54, .78);
		--nt-fg:       #d1d5db;
		--nt-fg-muted: #64748b;
		--nt-border:   rgba(148, 163, 184, .14);
		--nt-shadow:   0 4px 20px rgba(0, 0, 0, .4);
	}

	// --- Type accent tokens ---
	.nt-item--info    { --nt-accent: #38bdf8; --nt-accent-bg: rgba(56, 189, 248, .14); --nt-accent-bar: #38bdf8; }
	.nt-item--success { --nt-accent: #4ade80; --nt-accent-bg: rgba(74, 222, 128, .14); --nt-accent-bar: #4ade80; }
	.nt-item--warning { --nt-accent: #fbbf24; --nt-accent-bg: rgba(251, 191, 36,  .14); --nt-accent-bar: #fbbf24; }
	.nt-item--error   { --nt-accent: #f87171; --nt-accent-bg: rgba(248, 113, 113, .14); --nt-accent-bar: #f87171; }
	.nt-item--custom  { --nt-accent: #818cf8; --nt-accent-bg: rgba(129, 140, 248, .14); --nt-accent-bar: #818cf8; }

	// Light-тема — более насыщенные акценты
	.nt-stack[data-theme="light"] .nt-item--info    { --nt-accent: #0284c7; --nt-accent-bg: rgba(2, 132, 199,   .10); --nt-accent-bar: #0284c7; }
	.nt-stack[data-theme="light"] .nt-item--success { --nt-accent: #16a34a; --nt-accent-bg: rgba(22, 163, 74,   .10); --nt-accent-bar: #16a34a; }
	.nt-stack[data-theme="light"] .nt-item--warning { --nt-accent: #d97706; --nt-accent-bg: rgba(217, 119, 6,   .10); --nt-accent-bar: #d97706; }
	.nt-stack[data-theme="light"] .nt-item--error   { --nt-accent: #dc2626; --nt-accent-bg: rgba(220, 38,  38,  .10); --nt-accent-bar: #dc2626; }
	.nt-stack[data-theme="light"] .nt-item--custom  { --nt-accent: #4f46e5; --nt-accent-bg: rgba(79,  70,  229, .10); --nt-accent-bar: #4f46e5; }
</style>

<style lang="scss" scoped>
	// Scoped: структура карточки

	.nt-item {
		position: relative;
		display: grid;
		grid-template-columns: auto 1fr auto;
		align-items: start;
		gap: 0 12px;
		min-width: 300px;
		max-width: 420px;
		padding: 14px 16px;
		background: var(--nt-bg);
		color: var(--nt-fg);
		border: 1px solid var(--nt-border);
		border-left: 4px solid var(--nt-accent-bar);
		border-radius: 12px;
		box-shadow: var(--nt-shadow);
		font-family: var(--font-ui, system-ui, sans-serif);
		overflow: hidden;
		pointer-events: auto;
		backdrop-filter: blur(12px) saturate(140%);
		-webkit-backdrop-filter: blur(12px) saturate(140%);
		transition: transform 200ms ease, box-shadow 200ms ease;
	}

	.nt-item--clickable {
		cursor: pointer;

		&:hover {
			transform: translateY(-1px);
			box-shadow: 0 6px 24px rgba(0, 0, 0, .18), 0 2px 6px rgba(0, 0, 0, .08);
		}

		&:active {
			transform: translateY(0);
		}
	}

	.nt-item__icon-wrap {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		flex: none;
		width: 32px;
		height: 32px;
		border-radius: 50%;
		background: var(--nt-accent-bg);
		color: var(--nt-accent);
		margin-top: 1px;

		// .icon наследует background-color — без сброса поверх круга виден
		// более тёмный квадрат из-за повторного наложения полупрозрачного цвета
		:deep(.icon) {
			background-color: transparent !important;
		}
	}

	.nt-item__body {
		min-width: 0;
		padding: 3px 0;
	}

	.nt-item__title {
		font-size: 14px;
		font-weight: 600;
		line-height: 1.3;
		color: var(--nt-accent);
	}

	.nt-item__message {
		margin-top: 3px;
		font-size: 13px;
		line-height: 1.45;
		color: var(--nt-fg);
		word-break: break-word;

		:deep(a) {
			color: var(--nt-accent);
			text-decoration: underline;
			text-underline-offset: 2px;
			&:hover { opacity: .8; }
		}
	}

	.nt-item__close {
		flex: none;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 22px;
		height: 22px;
		margin-top: 1px;
		padding: 0;
		background: transparent;
		border: 0;
		color: var(--nt-fg-muted);
		cursor: pointer;
		border-radius: 6px;
		transition: background 150ms, color 150ms;

		&:hover {
			background: rgba(127, 127, 127, .12);
			color: var(--nt-fg);
		}
	}

	.nt-item__progress {
		position: absolute;
		left: 4px;
		bottom: 0;
		height: 2px;
		width: calc(100% - 4px);
		background: var(--nt-accent-bar);
		opacity: .35;
		transform-origin: left center;
		animation: nt-progress linear forwards;
	}

	@keyframes nt-progress {
		from { transform: scaleX(1); }
		to   { transform: scaleX(0); }
	}

	@media (prefers-reduced-motion: reduce) {
		.nt-item__progress { animation: none; display: none; }
	}
</style>
