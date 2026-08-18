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

export function resolveTabSet(configCode, role) {
	const configBlock = sets[configCode] ?? {};
	const defaultBlock = sets.default;

	return configBlock[role]
		?? defaultBlock[role]
		?? configBlock.default
		?? defaultBlock.default;
}
