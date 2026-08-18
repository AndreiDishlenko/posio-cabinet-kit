<template>
	<CabinetLayout page_name="Logs">
		<div class="ck-logs-layout">
			<div class="ck-card ck-files">
				<Link
					v-for="file in files"
					:key="file.name"
					class="ck-file"
					:class="{ 'is-active': file.name === selected }"
					:href="route('cabinet-kit.logs', { file: file.name })"
				>
					<span>{{ file.name }}</span>
					<small>{{ formatSize(file.size) }} · {{ file.modified_at }}</small>
				</Link>
				<div v-if="!files.length" class="ck-muted">No log files</div>
			</div>

			<pre class="ck-card ck-log-content">{{ content }}</pre>
		</div>
	</CabinetLayout>
</template>

<script>
	import { Link } from '@inertiajs/vue3';
	import CabinetLayout from '../../layouts/CabinetLayout.vue';

	export default {
		name: 'AdminLogs',
		components: { CabinetLayout, Link },
		props: {
			files: { type: Array, default: () => [] },
			selected: { type: String, default: '' },
			content: { type: String, default: '' },
		},
		methods: {
			formatSize(bytes) {
				if (!bytes) return '0 B';
				const units = ['B', 'KB', 'MB', 'GB'];
				const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
				return `${(bytes / Math.pow(1024, index)).toFixed(index ? 1 : 0)} ${units[index]}`;
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ck-logs-layout { display: grid; grid-template-columns: minmax(220px, 300px) minmax(0, 1fr); gap: 1rem; min-height: 0; height: 100%; }
	.ck-files { overflow: auto; display: flex; flex-direction: column; gap: .25rem; }
	.ck-file { display: flex; flex-direction: column; gap: .15rem; padding: .55rem .65rem; border-radius: .35rem; text-decoration: none; color: inherit; }
	.ck-file:hover, .ck-file.is-active { background: var(--ck-item-hover-bg, #eef2f7); }
	.ck-file small { opacity: .55; font-size: .72rem; }
	.ck-log-content { overflow: auto; margin: 0; white-space: pre-wrap; font-size: .78rem; line-height: 1.45; }
	.ck-muted { opacity: .55; font-size: .85rem; }
	@media (max-width: 800px) { .ck-logs-layout { grid-template-columns: 1fr; } }
</style>
