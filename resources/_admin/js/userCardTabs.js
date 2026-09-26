// Дополнительные разделы карточки пользователя, которые добавляет хост (например,
// аккаунт и лицензии). Регистрировать в точке входа кабинета, до первого открытия
// карточки:
//
//     registerUserCardTabs([
//         { id: 'licenses', label: 'Licenses', component: LicensesTab, permission: 'accounts' },
//     ]);
//
// permission — ключ прав карточки (users / roles / accounts): без него раздел не
// показывается. Компонент раздела получает пропсы user, perms, active (раздел сейчас
// открыт) и shared — общее хранилище разделов, пустое для каждого нового пользователя.
const user_card_tabs = [];

export function registerUserCardTabs(tabs) {
	user_card_tabs.push(...tabs);
}

export function userCardTabs() {
	return user_card_tabs;
}
