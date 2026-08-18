/**
 * Единый источник состава табов страницы настроек кабинета (CabinetSettings).
 *
 * Используется и самой страницей (CabinetSettings.vue), и выпадающим меню
 * настроек в SideMenu — чтобы список в меню автоматически совпадал с составом
 * табов страницы при его изменении. Меняешь табы здесь — меняется в обоих местах.
 *
 * @param {boolean} canManageMembers — право manage-members (owner / manager);
 *   открывает доступ к account-wide табам. На странице приходит из пропа
 *   can_manage_members, в SideMenu — из $page.props.user.can_manage_members.
 * @returns {{ id: string, label: string, label_mobile?: string }[]}
 */
export function buildSettingsTabs(canManageMembers = false) {
	const tabs = [
		{ id: 'settings', label: 'User profile', label_mobile: 'Profile' },
		{ id: 'account',  label: 'Account' },
	];

	if (canManageMembers) {
		tabs.push(
			{ id: 'users',          label: 'Users' },
			{ id: 'integrations',   label: 'Integrations' },
			{ id: 'cashflow_items', label: 'Cash flow items' },
		);
	}

	return tabs;
}
