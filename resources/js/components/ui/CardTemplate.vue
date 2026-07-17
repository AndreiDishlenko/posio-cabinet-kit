<template>

	<div class="ak-card-template">

		<div class="ak-card-header flex flex-row items-center justify-between">
			<h3 class="ak-card-title">{{ $t ? $t(title) : title }}</h3>
			<button type="button" class="ak-card-close" @click="$emit('close')">
				<Icon icon="mdi:close" class="ak-icon"/>
			</button>
		</div>

		<div class="ak-card-body">
			<slot/>
		</div>

		<div v-if="$slots.footer" class="ak-card-footer">
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
	.ak-card-template {
		min-width: 320px;
	}

	.ak-card-header {
		margin-bottom: 1rem;
	}

	.ak-card-title {
		font-size: 1.1rem;
		font-weight: 600;
	}

	.ak-card-close {
		opacity: .6;

		&:hover {
			opacity: 1;
		}
	}

	.ak-card-footer {
		margin-top: 1.25rem;
		display: flex;
		justify-content: flex-end;
		gap: .5rem;
	}
</style>
