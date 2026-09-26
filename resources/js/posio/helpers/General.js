export const General = {
    pause: async function(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    },

    detect_json: function(data) {
        try { 
            return JSON.parse(data); 
        } catch (e) { 
            return data; 
        }
    },

    parse_json: function(data, default_data={}) {
        if ( !data || data==null || data==undefined || data=='' || data=="{}"  || data=="\"{}\"") 
            return default_data;
        
        try {
            // console.log('set parsejson 2', data, JSON.parse(data), typeof data);
            // data = data.replace(/[\\/]/g, ''); // Удалить экранирующие слеши
            data = data.replace(/^"|"$/g, ''); // Удалить двойные кавычки в начале и конце
            // console.log('parsed data', data);
            
            return JSON.parse(data);
        } catch {
            // console.log('set parsejson 3', data, data);
            return data;
        }
    },

    stringifyReplacer: function(key, value) {
        // console.log('a', key, value, String(value));
        
        if ( value=="0" || (Number(value) && value.length>1 && value[0]!='0'))
            return Number(value);
    
        if (value=="true" || value=="false") 
            return Boolean(value)
    
        return value;
    },

    deProxy: function(proxyObject) {
        return JSON.parse(JSON.stringify(proxyObject));
    },

    cloneObject: function(sourceObject) {
        return JSON.parse(JSON.stringify(sourceObject));
    }
}