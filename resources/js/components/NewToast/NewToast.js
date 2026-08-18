// NewToast — собственный сервис тостов
// Использование:
//   NewToast.success('Saved')
//   NewToast.error({ title: 'Oops', message: 'Failed', autoClose: 0 })
//   NewToast.show({ type: 'custom', component: MyComp, props: {...}, position: 'top-right' })
//
// Параметры одного сообщения:
//   id          — авто, можно задать вручную
//   type        — 'info' | 'success' | 'error' | 'warning' | 'custom'
//   title       — заголовок (string)
//   message     — текст (string | html)
//   icon        — iconify-имя (переопределяет дефолт)
//   color       — цвет иконки (переопределяет дефолт)
//   component   — Vue-компонент для кастомного контента
//   props       — пропы для компонента
//   position    — 'top-left' | 'top-center' | 'top-right' | 'bottom-left' | 'bottom-center' | 'bottom-right'
//   autoClose   — мс до автозакрытия; 0 или false — не закрывать (остаётся на экране)
//   closable    — показывать крестик (default true)
//   onClose     — callback при закрытии

import { reactive } from 'vue';

const DEFAULTS = {
	position: 'bottom-center',
	autoClose: 4000,
	closable: true,
};

let _id = 0;
const nextId = () => `nt-${Date.now()}-${++_id}`;

export const NewToastState = reactive({
	items: [],
});

function add(payload) {
	const item = {
		id: payload.id || nextId(),
		type: payload.type || 'info',
		title: payload.title || '',
		message: payload.message || '',
		icon: payload.icon || '',
		color: payload.color || '',
		component: payload.component || null,
		props: payload.props || {},
		position: payload.position || DEFAULTS.position,
		autoClose: payload.autoClose ?? DEFAULTS.autoClose,
		closable: payload.closable ?? DEFAULTS.closable,
		onClose: payload.onClose || null,
		onClick: payload.onClick || null,
		closeOnClick: payload.closeOnClick ?? true,
	};
	NewToastState.items.push(item);
	return item.id;
}

function remove(id) {
	const idx = NewToastState.items.findIndex(i => i.id === id);
	if (idx === -1) return;
	const [removed] = NewToastState.items.splice(idx, 1);
	if (removed?.onClose) {
		try { removed.onClose(); } catch (_) {}
	}
}

function clear() {
	NewToastState.items.splice(0);
}

function normalize(payload) {
	if (typeof payload === 'string') return { message: payload };
	return payload || {};
}

export const NewToast = {
	show(payload)    { return add(normalize(payload)); },
	info(payload)    { return add({ ...normalize(payload), type: 'info' }); },
	success(payload) { return add({ ...normalize(payload), type: 'success' }); },
	warning(payload) { return add({ ...normalize(payload), type: 'warning' }); },
	error(payload)   { return add({ ...normalize(payload), type: 'error' }); },
	custom(component, opts = {}) {
		return add({ ...opts, type: 'custom', component });
	},
	close(id) { remove(id); },
	clear,

	install(app) {
		app.config.globalProperties.$newToast = this;
	},
};

export default NewToast;
