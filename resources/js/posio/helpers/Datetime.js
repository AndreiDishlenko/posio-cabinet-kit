// 3 Date formats
// 11.08.2023 - simple
// 2023-08-11 - reverse
// 11.08.2023 Wed Nov 08 2023 00:00:00 GMT+0200 - ISO
// YYYY-MM-DDTHH:mm:ss.sssZ - ISO 8601

// Convert Date() => YYYY-MM-DDTHH:mm:ss.sssZ (local datetime)
// $.dateToLocalISOString = function(date) {
// 2023-10-25T22:33:44.000Z - by Zero UTC
export const Datetime = {
	dayOfWeek: function (date) {
		let dayofweek = ['НД', 'ПОН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ'];
		return dayofweek[date.getDay()];
	},

	// Date() => string
	// ----------------------------------
	// 'dd.mm.yyyy' format
	toSimpleDateString: function (date) {
		// console.log('a0', date);

		if (!date)
			return '';

		let day = String(date.getDate());
		if (day.length==1) day = '0'+day;

		let month = String(date.getMonth()+1);
		if (month.length==1) month = '0'+month;

		let year = date.getFullYear();

		return `${day}.${month}.${year}`;
	},
	// 'yyyy-mm-dd' format
	toReverseDateString: function (date) {
		// console.log('toReverseDateString', date);
		
		if (!date)
			return '';

		let day = String(date.getDate());
		if (day.length==1) day = '0'+day;

		let month = String(date.getMonth()+1);
		if (month.length==1) month = '0'+month;

		let result = date.getFullYear() + '-' + month + '-' + day;
		return result;
	},
	// dd.mm
	toShortDateString: function (date) {
		// console.log('a0', date);
	
		if (!date)
			return '';
	
		let day = String(date.getDate());
		if (day.length==1) day = '0'+day;
	
		let month = String(date.getMonth()+1);
		if (month.length==1) month = '0'+month;
	
		return `${day}.${month}`;
	},
	// 'hh:mm:ss' time format
	toTimeString: function (date) {
		// console.log('a0', date.getTimezoneOffset() );
		if (!date)
			return '';

		let hours = String(date.getHours());
		if (hours.length==1) hours = '0'+hours;
		
		let minutes = String(date.getMinutes());
		if (minutes.length==1) minutes = '0'+minutes;

		let seconds = String(date.getSeconds());
		if (seconds.length==1) seconds = '0'+seconds;

		let result = hours + ':' + minutes + ':' + seconds;
		
		return result;
	},
	// 'hh:mm' time format
	toShortTimeString: function (date) {
		// console.log('a0', date.getTimezoneOffset() );
		
		if (!date)
			return '';
		let minutes = String(date.getMinutes());
		if (minutes.length==1) minutes = '0'+minutes;

		let result = date.getHours() + ':' + minutes;
		return result;
	},
    // 'dd.mm.yyyy hh:mm:ss' format
    toSimpleDatetimeString: function(date){
        return `${this.toSimpleDateString(date)} ${this.toTimeString(date)}`
    },
    // 'yyyy-mm-dd hh:mm:ss' format
    toReverseDatetimeString: function(date){
        return `${this.toReverseDateString(date)} ${this.toTimeString(date)}`
    },
	// 2024-05-14T09:45:05.065Z
	// toLocalIsoZString: function(date) {
	// 	// console.log('to_local_iso_datetime_string', date);
		
	// 	if (!date)
	// 		date = new Date();
	
	// 	var tzoffset = date.getTimezoneOffset() * 60000; 	// get different UTC - current.timezone
	// 	return (new Date(date - tzoffset)).toISOString();   // convert UTC (+0) date to locale timezone
	// },
	// 2023-10-26T01:33:44.000+03:00
	toLocalIsoOffsetString: function(date) {
		// console.log('to_local_iso_fulldatetime_string', date);
		var tzoffset = date.getTimezoneOffset() * 60000; 	// get different UTC - current.timezone
		date = new Date(date - tzoffset);
		let addZero = function(number) {
			if (number < 10) {
			  return "0" + number;
			}
			return number;
		}
		
		return date.getUTCFullYear() +
		"-" +
		addZero(date.getUTCMonth() + 1) +
		"-" +
		addZero(date.getUTCDate()) +
		"T" +
		addZero(date.getUTCHours()) +
		":" +
		addZero(date.getUTCMinutes()) +
		":" +
		addZero(date.getUTCSeconds()) +
		"." +
		(date.getUTCMilliseconds() / 1000).toFixed(3).slice(2, 5) +
		"+" +
		addZero( -(date.getTimezoneOffset()/60) )  + ":00"
	},

	// string => Date()
	// --------------------
	// YYYY-MM-DDTHH:mm:ss.sssZ (local datetime)
	parseIsoString: function(date_string) {								// Convert ISO string to date object
		var userTimezoneOffset = new Date(date_string).getTimezoneOffset() * 60000;
		return new Date(Date.parse(date_string) + userTimezoneOffset);
	},
    // DD.MM.YYYY HH:mm:ss (local datetime)
    parseReverseString: function(datetime_string) {
        // console.log('parseSimpleString', datetime_string);
        const [dateStr, timeStr] = datetime_string.split(' ');        
        const [year, month, day] = dateStr.split('-').map(Number);
        const [hours, minutes, seconds] = timeStr ? timeStr.split(':').map(Number) : ['00', '00', '00'];      
        
        const date = new Date(year, month - 1, day, hours, minutes, seconds);        
        return date;
    },
    // DD.MM.YYYY HH:mm:ss (local datetime)
    parseSimpleString: function(datetime_string) {
        // console.log('parseSimpleString', datetime_string);
        const [dateStr, timeStr] = datetime_string.split(' ');        
        const [day, month, year] = dateStr.split('.').map(Number);
        const [hours, minutes, seconds] = timeStr ? timeStr.split(':').map(Number) : ['00', '00', '00'];      
        // console.log('00', year, month - 1, day, hours, minutes, seconds);
        
        const date = new Date(year, month - 1, day, hours, minutes, seconds);
        
        return date;
    },

	// string => string
	// -------------------------
	reverseToSimple: function( date_string ) {
		if ( !/\d{4,4}\-\d{2,2}\-\d{2,2}/.test(date_string) )
			return date_string;
	
		if (!date_string)
			return date_string;
		
		return date_string.slice(8,10) + '.' + date_string.slice(5,7) + '.' + date_string.slice(0,4)
	},
	simpleToReverse: function( date_string ) {
		if ( !/\d{2,2}\.\d{2,2}\.\d{4,4}/.test(date_string) )
			return date_string;
	
		return date_string.slice(6,10) + '-' + date_string.slice(3,5) + '-' + date_string.slice(0,2)
	},

    decSeconds: function(date, seconds=0) {
        let result = new Date(date);
        result.setSeconds(date.getSeconds()-seconds);
        return result;
    },
    decMinutes: function(date, minutes=0) {
        let result = new Date(date);
        result.setMinutes(date.getMinutes()-minutes);
        return result;
    },
    // decHours: function(date, hours=0) {
    //     date.setHours(date.getHours()-hours);
    //     return date;
    // },
    // decDays: function(date, days=0) {
    //     date.setDays(date.getHours()-days);
    //     return date;
    // }

    addSeconds: function(date, seconds=0) {
        let result = new Date(date);
        result.setSeconds(date.getSeconds()+seconds);
        return result;
    },
    addMinutes: function(date, minutes=0) {
        let result = new Date(date);
        result.setMinutes(date.getMinutes()+minutes);
        return result;
    },
    addHours: function(date, hours=0) {
        let result = new Date(date);
        result.setHours(date.getHours()+hours);
        return result;
    },

    differInSec: function(date1, date2) {
        let result = '';

        result = (date1 - date2) / 1000;
        return result;
    },

    differInHours: function(date1, date2) {
        return this.differInSec(date1, date2) / 3600;
    },

    differInDays: function(date1, date2) {
        return this.differInSec(date1, date2) / (3600*24);
    },

    biggerDate: function(date1, date2) {
        return (date1 > date2) ? date1 : date2;
    }

}
