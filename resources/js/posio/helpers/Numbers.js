export const Numbers = {
    
    between: function(number, min, max) {
        if ( number >= min && number <=max)
            return true;

        return false;
    },

    addZeroes: function(num, count) {
        return num.toLocaleString("en", {useGrouping: false, minimumFractionDigits: count})
    }

    // function numToString(number) {
    //     return String(number).padStart( this.defaultNumberDigits - String(number).length, 0 );
    // }
}