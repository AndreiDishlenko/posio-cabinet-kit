// Журнал устройства ведёт хост (например, касса): записи общего кода (клиент
// запросов, словари) уходят в журнал, который хост зарегистрировал под именем
// контура. Пока журнала нет — запись уходит в консоль.
const console_sink = {
	msg  : (...args) => ( console.msg ?? console.log  ).call(console, ...args),
	warn : (...args) => ( console.wrn ?? console.warn ).call(console, ...args),
	error: (...args) => ( console.originalWarn ?? console.warn ).call(console, '[error]', ...args),
	debug: () => {},
};

const device_logs = {};

// Логер обязан отвечать на msg / warn / error / debug.
export function registerDeviceLog(name, logger) {
	device_logs[name] = logger;
}

export function deviceLog(name) {
	return device_logs[name] ?? console_sink;
}
