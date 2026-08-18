export const Arrays = {
    // Transform [{'id':1, 'name'}] to {key:{}} array
    toAssociative: function(arr=[], key='') {
        var result = {};
        for (let i = 0; i < arr.length; i++) {
            result[ arr[i][key] ] = arr[i];
        };

        return result;
    },

    // parse [] array into strings, including array from 1 value
    parseSimpleArray: function (array) {
        if (!array)
            array = [];

        if (Array.isArray(array))
            array = array.map((val)=>Number(val));


        return array;
    },

    // Pluck array, remove doubles
    pluck: function (arr, key) {
        var newArr = [];
        for (let i = 0; i < arr.length; i++) {
            newArr.push( arr[i][key] );
        };

        newArr = newArr.filter((element, index) => {
            return newArr.indexOf(element) === index;
        });

        return newArr;
    },

    // Get array without key
    reduce(array, excludeKey) {
        return Object.entries(array).reduce((acc, [key, value]) => {
            if (key !== excludeKey)
                acc[key] = value;
            return acc;
        })
    },

    filterByKey: function(aarr, filter) {
        let result = {};

        for (var key in aarr)
            if (filter.includes(key))
                result[key] = aarr[key];

        return result;
    },

    orderBy: function(aarr, field, order='asc') {
        for (let i1=0; i1<aarr.length-1; i1++) {
            for (let i=0; i<aarr.length-1; i++) {
                if ( order=='asc' && aarr[i][field] > aarr[i+1][field] ) {
                    let temp = aarr[i];
                    aarr[i] = aarr[i+1];
                    aarr[i+1] = temp;
                }
                if ( order=='desc' && aarr[i][field] < aarr[i+1][field] ) {
                    let temp = aarr[i];
                    aarr[i] = aarr[i+1];
                    aarr[i+1] = temp;
                }

            }
        }
    },

}
