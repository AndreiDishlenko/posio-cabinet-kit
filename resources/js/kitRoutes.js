// Хост со своими именами маршрутов кабинета присылает карту подмены; имя пакета —
// её ключ. Без карты имя остаётся пакетным.
export function kitRouteName(page, name) {
	return page?.props?.cabinet_kit_frontend?.routes?.[name] || name;
}

// Встроенный маршрут выхода принимает только POST; у хоста он может отвечать и на GET.
export function kitLogoutMethod(page) {
	return page?.props?.cabinet_kit_frontend?.logout_method || 'post';
}

// Настройка оболочки из конфига хоста; проп приходит, только когда хост что-то поменял.
export function kitFrontendOption(page, key, fallback = null) {
	return page?.props?.cabinet_kit_frontend?.[key] ?? fallback;
}
