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
