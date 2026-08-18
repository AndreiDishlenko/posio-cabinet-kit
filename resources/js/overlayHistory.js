// Реакция оверлеев на кнопку/жест «назад».
//
// Safari на iPhone и iPad ведёт назад свайпом от края, а Android — аппаратной
// кнопкой; и то и другое приходит одним событием истории. Пока каждый оверлей
// слушал историю сам, порядок закрытия зависел от порядка подписки: верхний
// мог остаться открытым, а нижний — исчезнуть, либо назад уводил с маршрута,
// оставив открытую карточку висеть поверх чужого экрана.
//
// Здесь один слушатель и один стек: назад всегда закрывает самый верхний
// оверлей и только его, а маршрут трогается лишь когда закрывать нечего.

const stack = [];

let listening = false;

// Снятие своей записи из истории тоже приходит событием — столько ближайших
// событий нужно пропустить, чтобы не закрыть заодно соседний оверлей.
let self_pops = 0;

// «Назад», потраченное на оверлей, никому больше не показывается.
let exclusive = false;

// Открытие помечается своей записью в истории, чтобы «назад» тратился на
// закрытие оверлея, а не на уход со страницы.
const MARKER = { overlay: true };

// Событие истории, потраченное на оверлей, — не навигация, и маршрутизатор
// страницы не должен его видеть: он трактует любой возврат назад как переход и
// пересоздаёт всё дерево страницы заново, хотя адрес не менялся.
function keepToOurselves(event) {
	if (exclusive)
		event.stopImmediatePropagation();
}

function onPopState(event) {
	if (self_pops > 0) {
		self_pops--;
		keepToOurselves(event);
		return;
	}

	const top = stack.pop();

	if (!top)
		return;

	keepToOurselves(event);

	// Запись уже снята браузером — закрываем, не трогая историю повторно.
	top.marked = false;
	top.close();
}

function startListening() {
	if (listening || typeof window === 'undefined')
		return;

	window.addEventListener('popstate', onPopState);
	listening = true;
}

// Подключается один раз при старте приложения — и обязательно до создания
// маршрутизатора: обработчики истории срабатывают в порядке подписки, и оборвать
// цепочку может только тот, кто подписался первым.
export function takeOverBackButton() {
	exclusive = true;
	startListening();
}

// Оверлей открыт: встаёт на вершину стека и получает свою запись в истории.
// `close` вызывается, когда пользователь возвращается назад.
export function pushOverlay(owner, close) {
	if (typeof window === 'undefined' || stack.some(item => item.owner === owner))
		return;

	startListening();

	window.history.pushState(MARKER, '');
	stack.push({ owner, close, marked: true });
}

// Оверлей закрыт своими средствами (кнопка, оверлей, Esc). Свою запись в
// истории он забирает обратно — иначе следующий «назад» уйдёт впустую.
export function popOverlay(owner) {
	const index = stack.findIndex(item => item.owner === owner);

	if (index === -1)
		return;

	const [entry] = stack.splice(index, 1);

	if (entry.marked && typeof window !== 'undefined') {
		self_pops++;
		window.history.back();
	}
}

// Оверлей закрылся как следствие перехода по ссылке внутри него. history.back()
// здесь недопустим: он бьёт synchronous popstate, а обработчик Inertia на
// popstate заводит новый visitId — уже стартовавший переход по ссылке теряет
// гонку и его ответ (приходит позже) молча отбрасывается как устаревший.
// Запись остаётся в истории — лишний шаг «назад» безопаснее сорванной навигации.
export function popOverlaySilent(owner) {
	const index = stack.findIndex(item => item.owner === owner);

	if (index === -1)
		return;

	stack.splice(index, 1);
}

export function hasOpenOverlays() {
	return stack.length > 0;
}
