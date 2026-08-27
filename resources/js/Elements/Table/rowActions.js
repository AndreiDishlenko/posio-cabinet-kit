// Стандартні дії таблиці: іконка та підказка прошиті тут, тож сторінка в описі кнопки
// лишає тільки подію — { event: 'onOpen' }. Будь-який ключ дескриптора (icon, tooltip,
// color, умови видимості) перекриває стандарт.
//
// Дії над рядком описуються в settings.rowbar, дії над групою — в settings.group_actions;
// набір стандартів спільний, різняться лише події.

export const STANDARD_ROW_ACTIONS = {
	onOpen: {
		icon:    'material-symbols:folder-open-outline',
		tooltip: 'Open',
	},
	onEdit: {
		icon:    'akar-icons:edit',
		tooltip: 'Edit',
	},
	onAdd: {
		icon:    'mdi:plus',
		tooltip: 'Add',
	},
	onDelete: {
		icon:    'material-symbols:delete-outline-rounded',
		tooltip: 'Delete',
	},
	onRestore: {
		icon:    'material-symbols:restore-from-trash-outline-rounded',
		tooltip: 'Restore',
	},
	onActions: {
		icon:    'tabler:dots-vertical',
		tooltip: 'Actions',
	},
};

// Дії над групою: та сама четвірка, але видимість пари видалити/відновити задається
// прапорцем запису довідника груп — сторінці не треба його повторювати.
export const STANDARD_GROUP_ACTIONS = {
	onGroupAdd: {
		...STANDARD_ROW_ACTIONS.onAdd,
	},
	onGroupEdit: {
		...STANDARD_ROW_ACTIONS.onEdit,
	},
	onGroupDelete: {
		...STANDARD_ROW_ACTIONS.onDelete,
		flag_off: 'is_deleted',
	},
	onGroupRestore: {
		...STANDARD_ROW_ACTIONS.onRestore,
		flag: 'is_deleted',
	},
};

// Іменовані кольори іконок → класи палітри (colors_shared.scss). Значення поза списком
// вважається готовим класом, тож можна передати і власний.
const COLOR_CLASSES = {
	default:   'text-secondary',
	secondary: 'text-secondary',
	muted:     'text-lightgray',
	primary:   'text-color',
	danger:    'text-error',
	error:     'text-error',
	success:   'text-success',
	green:     'text-green',
	warning:   'text-warning',
	yellow:    'text-yellow',
};

// Дескриптор кнопки, доповнений стандартом своєї події.
export function resolveRowAction(bar) {
	const standard = STANDARD_ROW_ACTIONS[bar?.event] || STANDARD_GROUP_ACTIONS[bar?.event] || {};

	return {
		...standard,
		...bar,
	};
}

// Колір іконки: рядок або функція (row) => колір — для умовного підсвічування.
export function rowActionColorClass(bar, row) {
	const color = typeof bar?.color === 'function' ? bar.color(row) : bar?.color;

	if ( !color )
		return COLOR_CLASSES.default;

	return COLOR_CLASSES[color] || color;
}
