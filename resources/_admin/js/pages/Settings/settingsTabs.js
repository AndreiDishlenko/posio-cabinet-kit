/**
 * Единый источник состава табов страницы настроек кабинета (CabinetSettings).
 *
 * Используется и самой страницей (CabinetSettings.vue), и выпадающим меню
 * настроек в SideMenu — чтобы список в меню автоматически совпадал с составом
 * табов страницы при его изменении. Меняешь табы здесь — меняется в обоих местах.
 *
 * Таб попадает в список только если его файл реально лежит рядом: страницу
 * настроек можно перенести в другой проект вместе с частью табов — недостающие
 * молча исчезают и из страницы, и из меню, вместо ошибки сборки.
 *
 * @param {boolean} canManageMembers — право manage-members (owner / manager);
 *   открывает доступ к account-wide табам. На странице приходит из пропа
 *   can_manage_members, в SideMenu — из $page.props.user.can_manage_members.
 * @returns {{ id: string, label: string, label_mobile?: string }[]}
 */

// Ленивый glob: нужен только перечень имеющихся файлов, сами компоненты сюда не
// подтягиваются — иначе меню в боковой панели тянуло бы за собой все табы.
const present_tab_files = import.meta.glob('./CabinetSettings*Tab.vue');

// Полный каталог табов: порядок здесь — порядок в строке табов и в меню.
// account_wide — таб настраивает аккаунт целиком, доступен только с manage-members.
const SETTINGS_TABS = [
	{ id: 'settings',       label: 'User profile',    label_mobile: 'Profile', file: 'CabinetSettingsUserProfileTab.vue' },
	{ id: 'account',        label: 'Account',         file: 'CabinetSettingsAccountTab.vue' },
	{ id: 'cash_accounts',  label: 'Cash accounts',   file: 'CabinetSettingsCashAccountsTab.vue',  account_wide: true },
	{ id: 'users',          label: 'Users',           file: 'CabinetSettingsAccountUsersTab.vue',  account_wide: true },
	{ id: 'licenses',       label: 'Licenses',        file: 'CabinetSettingsLicensesTab.vue',      account_wide: true },
	{ id: 'integrations',   label: 'Integrations',    file: 'CabinetSettingsIntegrationsTab.vue',  account_wide: true },
	{ id: 'cashflow_items', label: 'Cash flow items', file: 'CabinetSettingsCashflowItemsTab.vue', account_wide: true },
];

export function buildSettingsTabs(canManageMembers = false) {
	return SETTINGS_TABS
		.filter(tab => (!tab.account_wide || canManageMembers) && `./${tab.file}` in present_tab_files)
		.map(tab => {
			const item = { id: tab.id, label: tab.label, file: tab.file };
			if (tab.label_mobile)
				item.label_mobile = tab.label_mobile;

			return item;
		});
}
