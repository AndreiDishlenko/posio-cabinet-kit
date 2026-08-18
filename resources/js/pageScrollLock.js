// Блокировка прокрутки страницы под оверлеями.
//
// overflow:hidden на body в Safari на iPhone/iPad не держит — страница продолжает
// скроллиться пальцем, и после закрытия оверлея пользователь оказывается в другом
// месте. Рабочий приём: зафиксировать страницу на текущей позиции и вернуть её
// обратно при снятии блокировки.
//
// Владельцем блокировки выступает компонент (передаёт `this`) — так повторный вызов
// от того же оверлея ничего не меняет, а одновременно открытые оверлеи не снимают
// блокировку друг друга раньше времени.

const owners = new Set();

let savedScroll = 0;
let savedStyles = null;

function applyLock() {
	const body = document.body;

	savedScroll = window.pageYOffset || document.documentElement.scrollTop || 0;
	savedStyles = {
		position:     body.style.position,
		top:          body.style.top,
		left:         body.style.left,
		right:        body.style.right,
		width:        body.style.width,
		overflow:     body.style.overflow,
		paddingRight: body.style.paddingRight,
	};

	// Полоса прокрутки исчезает вместе с прокручиваемой высотой — компенсируем её
	// ширину, иначе на десктопе контент дёргается вбок в момент открытия.
	const scrollbar = window.innerWidth - document.documentElement.clientWidth;

	body.style.position = 'fixed';
	body.style.top      = `-${savedScroll}px`;
	body.style.left     = '0';
	body.style.right    = '0';
	body.style.width    = '100%';
	body.style.overflow = 'hidden';

	if (scrollbar > 0)
		body.style.paddingRight = `${scrollbar}px`;
}

function releaseLock() {
	if (savedStyles)
		Object.assign(document.body.style, savedStyles);

	savedStyles = null;

	window.scrollTo(0, savedScroll);
}

export function lockPageScroll(owner) {
	if (typeof document === 'undefined' || owners.has(owner))
		return;

	owners.add(owner);

	if (owners.size === 1)
		applyLock();
}

export function unlockPageScroll(owner) {
	if (typeof document === 'undefined' || !owners.delete(owner))
		return;

	if (owners.size === 0)
		releaseLock();
}
