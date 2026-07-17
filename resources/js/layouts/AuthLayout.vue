<template>

	<Head :title="$t ? $t(page_name || 'Cabinet') : (page_name || 'Cabinet')"/>

	<div class="ck-auth-wrapper flex items-center justify-center min-h-[100dvh] p-4">
		<div class="ck-card ck-auth-card w-full max-w-sm">
			<h1 class="ck-auth-title">{{ $t ? $t(page_name || '') : page_name }}</h1>

			<slot/>
		</div>
	</div>

</template>

<script>
	import { Head } from '@inertiajs/vue3';

	export default {
		name: 'AuthLayout',
		components: { Head },
		props: {
			page_name: {
				type: String,
				default: '',
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-auth-wrapper {
		background: var(--ck-background-color, #f6f7f9);
	}

	.ck-auth-title {
		font-size: 1.2rem;
		font-weight: 600;
		margin-bottom: 1.25rem;
	}
</style>
