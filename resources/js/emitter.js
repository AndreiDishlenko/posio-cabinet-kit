export function createEmitter() {
    const handlers = new Map();

    return {
        on(type, handler) {
            const existing = handlers.get(type);
            existing ? existing.push(handler) : handlers.set(type, [handler]);
        },
        off(type, handler) {
            const existing = handlers.get(type);
            if (existing) existing.splice(existing.indexOf(handler) >>> 0, 1);
        },
        emit(type, event) {
            (handlers.get(type) || []).slice().forEach((handler) => handler(event));
            (handlers.get('*') || []).slice().forEach((handler) => handler(type, event));
        },
    };
}
