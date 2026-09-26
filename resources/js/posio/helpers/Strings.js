
export const Strings = {
    capitalizeFirstLetter(string) { 
        if (string.length === 0) 
            return string; 
        return string.charAt(0).toUpperCase() + string.slice(1);
    },

    randomString: function(length) {
        const chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
        let result = "";
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
}