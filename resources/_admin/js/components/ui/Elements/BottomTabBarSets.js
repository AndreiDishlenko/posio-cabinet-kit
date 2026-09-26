export const MAX_TAB_BAR_ITEMS = 5;

const sets = {
	default: {
		default: [
			'cabinet-kit.users',
			'cabinet-kit.permissions',
			'cabinet-kit.permissions.account',
			'cabinet-kit.settings',
		],
	},
};

export default sets;

// Порядок выбора: роль в конфигурации → роль в общем блоке → набор конфигурации →
// общий набор. Наборы хоста заменяют наборы пакета целиком.
export function resolveTabSet(configCode, role, hostSets = null) {
	const source = hostSets ?? sets;
	const configBlock = source[configCode] ?? {};
	const defaultBlock = source.default ?? {};

	return configBlock[role]
		?? defaultBlock[role]
		?? configBlock.default
		?? defaultBlock.default;
}
