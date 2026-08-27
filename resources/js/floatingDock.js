import { reactive } from 'vue';

// Спільна черга плаваючих елементів нижнього правого кута екрана: кнопка дії
// списку, нагадування про перші кроки тощо. Кожен наступний елемент стає НАД
// уже наявними, а не поверх них; коли нижній зникає — верхні опускаються.
// Порядок стовпчика = порядок появи, тож перший елемент завжди стоїть на
// звичному місці біля краю екрана.

// Проміжок між сусідніми елементами стовпчика.
const GAP = 12;

const state = reactive({
	items: [],
});

let last_id = 0;

export function joinDock() {
	const id = ++last_id;
	state.items.push({ id, height: 0 });

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

	for ( const item of state.items ) {
		if ( item.id === id )
			break;

		if ( item.height )
			offset += item.height + GAP;
	}

	return offset;
}
