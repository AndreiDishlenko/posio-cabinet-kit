<template>

	<div class="ck-card-template">

		<div class="ck-card-header flex flex-row items-center justify-between">
			<h3 class="ck-card-title">{{ $t ? $t(title) : title }}</h3>
			<button type="button" class="ck-card-close" @click="$emit('close')">
				<Icon icon="mdi:close" class="ck-icon"/>
			</button>
		</div>

		<div class="ck-card-body">
			<slot/>
		</div>

		<div v-if="$slots.footer" class="ck-card-footer">
			<slot name="footer"/>
		</div>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue';

	export default {
		name: 'CardTemplate',
		components: { Icon },
		props: {
			title: {
				type: String,
				default: '',
			},
		},
		emits: ['close'],
	}
</script>

<style lang="scss" scoped>
	.ck-card-template {
		min-width: 320px;
	}

	.ck-card-header {
		margin-bottom: 1rem;
	}

	.ck-card-title {
		font-size: 1.1rem;
		font-weight: 600;
	}

	.ck-card-close {
		opacity: .6;

		&:hover {
			opacity: 1;
		}
	}

	.ck-card-footer {
		margin-top: 1.25rem;
		display: flex;
		justify-content: flex-end;
		gap: .5rem;
	}
</style>
