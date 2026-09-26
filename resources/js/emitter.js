export function createEmitter() {
    const handlers = new Map();

    return {
        // Подписки по событиям — как all у mitt: перенесённый код чистит шину через него.
        all: handlers,
        on(type, handler) {
            const existing = handlers.get(type);
            existing ? existing.push(handler) : handlers.set(type, [handler]);
        },
        // Без обработчика снимает все подписки события — как mitt, на который
        // рассчитаны перенесённые компоненты: иначе подписки копятся при каждом
        // повторном монтировании, и событие срабатывает несколько раз.
        off(type, handler) {
            const existing = handlers.get(type);
            if (!existing) return;

            if (handler) existing.splice(existing.indexOf(handler) >>> 0, 1);
            else handlers.set(type, []);
        },
        emit(type, event) {
            (handlers.get(type) || []).slice().forEach((handler) => handler(event));
            (handlers.get('*') || []).slice().forEach((handler) => handler(type, event));
        },
    };
}
