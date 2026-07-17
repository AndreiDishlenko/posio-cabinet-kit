<template>

	<div class="ak-card">
		<h3 class="ak-card-title">{{ $t ? $t('Users') : 'Users' }}</h3>

		<table class="ak-simple-table">
			<thead>
				<tr>
					<th>{{ $t ? $t('Name') : 'Name' }}</th>
					<th>{{ $t ? $t('Email') : 'Email' }}</th>
					<th v-if="can_manage_account"></th>
				</tr>
			</thead>
			<tbody>
				<tr v-for="member in members" :key="member.id">
					<td>{{ member.name }} <span v-if="member.is_owner" class="ak-badge">{{ $t ? $t('Owner') : 'Owner' }}</span></td>
					<td>{{ member.email }}</td>
					<td v-if="can_manage_account">
						<button v-if="!member.is_owner" type="button" class="button ghost-button button-sm" @click="removeMember(member)">
							{{ $t ? $t('Remove') : 'Remove' }}
						</button>
					</td>
				</tr>
			</tbody>
		</table>
	</div>

</template>

<script>
	import { router } from '@inertiajs/vue3';

	export default {
		name: 'UsersTab',
		props: {
			members: {
				type: Array,
				default: () => [],
			},
			can_manage_account: {
				type: Boolean,
				default: false,
			},
		},
		methods: {
			removeMember(member) {
				router.post(route('admin-kit.account.member.remove'), { user_id: member.id }, { preserveScroll: true });
			},
		},
	}
</script>

<style lang="scss" scoped>
	.ak-simple-table {
		width: 100%;
		border-collapse: collapse;

		th, td {
			text-align: start;
			padding: .5rem;
			border-bottom: 1px solid var(--ak-border-color, #e5e7eb);
		}
	}

	.ak-badge {
		font-size: .7rem;
		opacity: .6;
		margin-inline-start: .35rem;
	}
</style>
