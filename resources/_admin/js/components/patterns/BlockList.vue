<template>
	<div class="block-list">

		<slot />

		<!-- Add button card — same height as siblings via align-items:stretch on grid -->
		<button
			v-if="addable"
			class="compact-card compact-card--add block-list__add"
			:class="{ disabled: disabled }"
			:disabled="disabled"
			:title="disabled ? disabledHint : ''"
			@click="onAddClick"
		>
			<Icon icon="lucide:plus" class="icon icon-lg" />
			<span>{{ addLabel || $t('Add') }}</span>
		</button>

	</div>
</template>

<script>
import { Icon } from '@iconify/vue';

export default {
	name: 'BlockList',
	components: { Icon },
	emits: ['onAdd'],
	props: {
		addable: {
			type: Boolean,
			default: true,
		},
		addLabel: {
			type: String,
			default: '',
		},
		disabled: {
			type: Boolean,
			default: false,
		},
		disabledHint: {
			type: String,
			default: '',
		},
		minCardWidth: {
			type: String,
			default: '280px',
		},
	},
	methods: {
		onAddClick() {
			if ( this.disabled )
				return;

			this.$emit('onAdd');
		},
	},
}
</script>

<style lang="scss" scoped>
.block-list {
	display: grid;
	grid-template-columns: v-bind("'repeat(auto-fill, minmax(' + minCardWidth + ', 1fr))'");
	grid-auto-rows: 1fr;
	gap: 1rem;
	align-items: stretch;
}

.block-list__add {
	min-height: 100%;
}
</style>
