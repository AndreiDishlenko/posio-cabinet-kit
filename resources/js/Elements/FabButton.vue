<template>

	<button class="fab-button"
		:class="[
			`fab-button--${variant}`,
			`fab-button--${size}`,
		]"
		:style="opacity !== 1 ? { opacity } : undefined"
		:aria-label="ariaLabel"
		@click="$emit('click', $event)">
		<slot>
			<Icon v-if="icon || defaultIcon" :icon="icon || defaultIcon" class="icon" />
			<span v-if="label" class="fab-button__label">{{ label }}</span>
		</slot>
	</button>

</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		components: { Icon },
		emits: ['click'],
		props: {
			// 'primary' — blue circle FAB (default); 'add' — green squircle FAB
			variant: {
				type: String,
				default: 'primary',
			},
			// 'sm' 40px | 'md' 48px | 'base' 56px (default) | 'enlarge' 72px | 'lg' 96px | 'xl' h56 min-w80 extended
			size: {
				type: String,
				default: 'base',
			},
			icon: {
				type: String,
				default: '',
			},
			// Optional text shown next to the icon (mainly for 'extended' size)
			label: {
				type: String,
				default: '',
			},
			ariaLabel: {
				type: String,
				default: '',
			},
			opacity: {
				type: Number,
				default: 1,
			},
		},
		computed: {
			defaultIcon() {
				if (this.icon) return this.icon;

				return this.variant === 'add' ? 'ic:round-add' : 'mdi:basket-outline';
			},
		},
	}
</script>

<style lang="scss" scoped>
	// Default placement: absolutely positioned in the bottom-right corner of
	// its nearest positioned ancestor. Caller is responsible for anchoring
	// that ancestor where the FAB should appear.
	// Edge gaps: 16px from the bottom; 16px on mobile / 24px on desktop from the right.
	.fab-button {
		position: absolute;
		bottom: 16px;
		right: 16px;
		z-index: 1000;

		@media (min-width: 768px) {
			bottom: 24px;
			right: 24px;
		}
	}

	// blue circle FAB
	.fab-button--primary {
		border-radius: 50%;
		background: var(--fab-primary-bg);
		// background: green!important ;
		color: var(--fab-primary-fg);
		box-shadow: var(--fab-primary-shadow);

		&:hover  { background: var(--fab-primary-bg-hover); }
		&:active { background: var(--fab-primary-bg-press); }
	}

	// rounded square FAB
	.fab-button--square {
		border-radius: var(--ui-radius-xl, 16px);
		background: var(--fab-primary-bg);
		color: var(--fab-primary-fg);
		box-shadow: var(--fab-primary-shadow);

		&:hover  { background: var(--fab-primary-bg-hover); }
		&:active { background: var(--fab-primary-bg-press); }
	}

	.fab-cta {
		background: var(--bg-cta)!important;
		color: var(--text-color-inverted);
	}

	// green squircle FAB
	// .fab-button--add {
	// 	border-radius: var(--ui-radius-xl);
	// 	background: var(--fab-add-bg);
	// 	color: var(--fab-add-fg);
	// 	border: 1px solid var(--fab-add-border);
	// 	box-shadow: var(--fab-add-glow);

	// 	&:hover {
	// 		background: var(--fab-add-bg-hover);
	// 		box-shadow: var(--fab-add-glow-active);
	// 	}
	// 	&:active { background: var(--fab-add-bg-press); }
	// }

	// ── Sizes ─────────────────────────────────────────────────────────────────
	.fab-button--sm {
		width: 40px;
		height: 40px;

		:slotted(.icon),
		.fab-button__label {
			font-size: 18px;
		}
		:slotted(svg) { width: 18px; height: 18px; }
	}

	.fab-button--md {
		width: 48px;
		height: 48px;

		:slotted(.icon) { font-size: 20px; }
		:slotted(svg)   { width: 20px; height: 20px; }
	}

	.fab-button--base {
		width: 56px;
		height: 56px;

		:slotted(.icon) { font-size: 24px; }
		:slotted(svg)   { width: 24px; height: 24px; }
	}

	.fab-button--enlarge {
		width: 72px;
		height: 72px;

		:slotted(.icon) { font-size: 30px; }
		:slotted(svg)   { width: 30px; height: 30px; }
	}

	.fab-button--lg {
		width: 96px;
		height: 96px;

		:slotted(.icon) { font-size: 36px; }
		:slotted(svg)   { width: 36px; height: 36px; }
	}

	.fab-button--xl {
		height: 56px;
		min-width: 80px;
		padding: 0 20px;
		@include flex-gap(8px);

		:slotted(.icon) { font-size: 24px; }
		:slotted(svg)   { width: 24px; height: 24px; }
	}

	.fab-button__label {
		font-size: 14px;
		font-weight: 600;
		white-space: nowrap;
	}
</style>
