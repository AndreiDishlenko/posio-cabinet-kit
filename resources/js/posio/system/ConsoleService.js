import Helpers      from "../helpers.js"

import $dayjs       from 'dayjs';
import utc          from 'dayjs/plugin/utc';

$dayjs.extend(utc);

// Буфер строк для показа в консоли и структурированный журнал для выгрузки на
// сервер держатся в одних границах: и то и другое живёт всю смену.
const MAX_BUFFER_LINES = 500;

// Колонка сообщения в журнале кассы ограничена — обрезаем на клиенте, чтобы
// длинный трейс не ронял вставку всей пачки.
const MAX_MESSAGE_LENGTH = 200;

class ConsoleServiceClass {
    constructor() {
        // this.logs = [];
        console.logs = [];
        console.Buffer = [];
        this.originalConsole = { ...console };

        console.originalLog = console.log;	
        console.originalWarn = console.warn;

		console.msg = console.log
		console.wrn = console.warn
    }

	addCustomFunctions() {
		console.log('[ConsoleService.addCustomFunctions]');

		console.msg = (...args) => {
            let arr = [Helpers.dt.toShortTimeString(new Date()), ...args];
            console.originalLog(...arr);
            this.store('info', arr);
        };

        console.wrn = (...args) => {
            let arr = ['!', Helpers.dt.toShortTimeString(new Date()), ...args];
            console.originalWarn( ...arr );
            this.store('warn', arr);
        };
	}

	// Журнал для выгрузки наполняется теми же вызовами, что и консоль. Раньше он
	// собирался отдельным механизмом, который никто не включал, и на сервер уезжал
	// пустой пакет — разбирать инцидент офлайн-смены было нечем.
	store(type, args) {
		const line = args.map(item => {
			if ( item !== null && typeof item === 'object' ) {
				try {
					return JSON.stringify(item);
				} catch (e) {
					return '[object]';
				}
			}
			return String(item);
		}).join(' ');

		console.Buffer.push(line);
		console.logs.push({
			type,
			timestamp: $dayjs().format('YYYY-MM-DD HH:mm:ss'),
			message:   line.slice(0, MAX_MESSAGE_LENGTH),
		});

		if ( console.Buffer.length > MAX_BUFFER_LINES )
			console.Buffer.splice(0, console.Buffer.length - MAX_BUFFER_LINES);

		if ( console.logs.length > MAX_BUFFER_LINES )
			console.logs.splice(0, console.logs.length - MAX_BUFFER_LINES);
	}

	removeOriginalFunctions() {
		console.log('[ConsoleService.removeOriginalFunctions]');

		console.log = function(){};
		console.warn = function(){};
	}

    // changeCustomFunctions() {
		// console.log('addCustomFunctions');
		
        // console.Buffer = [];


        
		// this.addCustomFunctions
        
        // console.err = function(...args) {
        //      console.log(...args);        
        // 	    console.error(Helpers.dt.toShortTimeString(new Date()), ...args);
        // };
    // }

    // removeOriginal() {
	// 	// console.log('removeOriginal');
    //     if (process.env.NODE_ENV == 'production') {

            
    //         // console = {
    //         // 	log_reserve: originalLog,
    //         // 	msg: console.msg,
    //         // 	wrn: console.wrn,
    //         // 	warn: console.warn,
    //         // 	error: console.error,
    //         // 	log: noOp
    //         // };
    //     }
    // }

    enableStore() {
        ['log', 'warn', 'error', 'info'].forEach(method => {
            console[method] = (...args) => {
                this.originalConsole[method](...args); 
                console.logs.push({
                    type: method,
                    timestamp: $dayjs().format('YYYY-MM-DD HH:mm:ss'),
                    message: args.map(arg => typeof arg === 'object' ? JSON.stringify(arg) : arg).join(' ')
                });

                // Опционально: ограничиваем размер массива, чтобы не переполнить память (например, последние 100 логов)
                if (console.logs.length > 200) 
                    console.logs.shift();
            };
        });
    }

    getLogs() {
        return console.logs;
    }
}

const ConsoleService = new ConsoleServiceClass()

console.getLogs = ConsoleService.getLogs;

export { ConsoleService }