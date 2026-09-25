// Журнал устройства в исходном проекте ведёт касса: записи общего кода (клиент
// запросов, словари) уходят туда, пока журнал заведён. В кабинете журнала нет,
// поэтому здесь только его точка входа — запись всегда уходит в консоль.
const console_sink = {
	msg  : (...args) => ( console.msg ?? console.log  ).call(console, ...args),
	warn : (...args) => ( console.wrn ?? console.warn ).call(console, ...args),
	error: (...args) => ( console.originalWarn ?? console.warn ).call(console, '[error]', ...args),
	debug: () => {},
};

export function deviceLog() {
	return console_sink;
}
