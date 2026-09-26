export const Validation = {
    isNumeric: function(number) {
        return !isNaN(parseFloat(number)) && !isNaN(+number);
    },
    
    isEmpty: function(variable) {
        // console.log('isEmpty'+variable);
        if ( variable==='' || !variable || variable.length === 0 )
            return true

        return false;
    },

    isArray: function(obj) {
        if ( this.isEmpty(obj) )
            return false;

        return Array.isArray(obj) ? true : false
    },

    isStringifiedArray(str) {
        // Step 1: Check if the string starts with [ and ends with ]
        const regex = /^\[.*\]$/
        if (!regex.test(str)) {
          return false;
        }
      
        // Step 2: Parse the JSON and check if it's an array
        try {
          const parsed = JSON.parse(str);
          return Array.isArray(parsed);
        } catch (e) {
          return false; // Invalid JSON
        }
    },

    isAssociative: function(obj) {
        if ( this.isEmpty(obj) )
            return false;

        if ( typeof obj === 'object' && !(obj instanceof Date) )
            return true;

        return false
    },

    check_valid: function(request, field, rules) {
        let result = "";
        for ( let i=0; i<rules.length; i++ ) {
            // console.log('check_rule', field, rules[i]);
            
            if (rules[i].substring(0, 6) == 'equal:') {
                let equal_field = rules[i].substring(6, rules[i].length);
                if ( request[field] != request[equal_field] )
                    result = result + 'Does not match the '+equal_field;
                continue;
            }
            if (rules[i].substring(0, 4) == 'min:') {
                let min_length = Number(rules[i].substring(4, rules[i].length));
                if (typeof min_length !== 'number')	
                    continue;
                if ( !request[field] || String(request[field]).trim().length < min_length ) {
                    // console.log(field, request[field], String(request[field]), String(request[field]).trim().length, min_length);			
                    result = result + msg('Must be at least')+' '+ min_length +' '+msg('characters')+'.';
                }
                continue;
            }

            if (rules[i].substring(0, 4) == 'max:') {
                let max_length = Number(rules[i].substring(4, rules[i].length));
                if (typeof max_length !== 'number')	
                    continue;
                if ( String(request[field]).trim().length > max_length ) 
                    result = result + msg('Must be max')+' '+ max_length +' '+msg('characters')+'.';
                continue;
            }


            switch (rules[i]) {
                case 'not_null':
                    // console.log('not_null', request[field], String(request[field]).trim());
                    if ( request[field]==undefined || !String(request[field]).trim() ) 
                        result = result + msg('Required for filling in')+'. ';
                    break;
                case 'integer':
                    if ( isNaN(request[field]) ) 
                        result = result + msg('Must be a number')+'. ';
                    break;
                case 'email':
                    if ( !String(request[field]).match(/^\S+@\S+\.\S+$/) )
                        result = result + msg('Wrong email format')+'. ';
                    break;
            }		
        }

        // if (result)
        // 	console.log('val_error:', field, result);

        return result;
    }
}