import { reactive } from 'vue';

// Спільна черга плаваючих елементів нижнього правого кута екрана: кнопка дії
// списку, нагадування про перші кроки, помічник. Кожен елемент стає НАД
// сусідами, а не поверх них; коли нижній зникає — верхні опускаються.
// Порядок стовпчика задає вага елемента (менша — ближче до краю екрана), тож
// місце кожного стале й не залежить від того, хто зʼявився раніше.

// Проміжок між сусідніми елементами стовпчика.
const GAP = 12;

const state = reactive({
	items: [],
	// Розкритий віджет у стовпчику завжди один: два розкритих накрили б і один
	// одного, і робочу область під ними.
	opened: null,
});

let last_id = 0;

export function joinDock(weight) {
	const id = ++last_id;
	state.items.push({ id, weight: weight || 0, height: 0 });

	return id;
}

export function leaveDock(id) {
	const index = state.items.findIndex(item => item.id === id);
	if ( index >= 0 )
		state.items.splice(index, 1);
}

export function setDockHeight(id, height) {
	const item = state.items.find(item => item.id === id);
	if ( item )
		item.height = height;
}

// Відступ від низу для конкретного елемента — сумарна висота всіх, хто стоїть
// під ним. Елемент, який ще не виміряний (нульова висота), місця не займає.
export function dockOffsetOf(id) {
	let offset = 0;

	// Копія перед упорядкуванням: сортування на місці перемішало б спільний
	// масив і зробило б черговий перерахунок залежним від попереднього.
	const column = state.items.slice().sort((a, b) => a.weight - b.weight);

	for ( const item of column ) {
		if ( item.id === id )
			break;

		if ( item.height )
			offset += item.height + GAP;
	}

	return offset;
}

// Розкриття віджета витісняє попередній: власник кожного слухає, чи він досі
// той самий, і згортається сам.
export function openDockWidget(id) {
	state.opened = id;
}

export function closeDockWidget(id) {
	if ( state.opened === id )
		state.opened = null;
}

export function openedDockWidget() {
	return state.opened;
}
