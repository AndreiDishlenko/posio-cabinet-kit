// Реестр разовых подсветок кабинета. Каждая показывается один раз на пользователя
// и ведёт внимание к тому, что само о себе не заявляет.
//
// Дескриптор: { key, target, title, textKey, position, visible(page) }
//   key     — ключ отметки «показано» на пользователе, латиница/цифры/_-
//   target  — CSS-селектор подсвечиваемого элемента (можно перечислить варианты
//             через запятую — берётся первый найденный)
//   visible — условие показа: подсвечивать имеет смысл только то, что уже на экране
//
// Порядок в массиве — порядок очереди: за раз показывается первая подходящая.
export const SPOTLIGHT_HINTS = [
	{
		key:      'first_steps',
		target:   '.checklist-panel, .checklist-pill',
		title:    'Your learning assistant',
		textKey:  'hint-first-steps-text',
		position: 'top',
		// Только новичку: закрытый шаг значит, что владелец нашёл дорогу и без
		// помощника. Панель приходит с бэкенда только тому, кому её вообще показывают.
		visible:  (page) => {
			const checklist = page.props.first_steps_checklist;

			return !!checklist && Object.values(checklist.steps ?? {}).every(done => !done);
		},
	},
];
