import { reactive } from 'vue'

export class PropObjectClass {
    propKeys

    constructor(initialProps={}) {
        this.propKeys = []

        if ( Object.keys(initialProps).length )
            this.setProperties(initialProps)        
    }

    getProperty(name, def=null) {       
        return this.propKeys.includes(name) ? this[name] : def;
    }
    
    getProperties() { 
        let result = {};

        this.propKeys.forEach((propKey) => {
            result[propKey] = this[propKey];
        })
        
        return result;
    }
    
    setProperty(field, value) {
        // console.log('[PropObjectClass.setProp]', field, value);
        if ( !field )
            return false;

        if ( !this.propKeys.includes(field) )
            this.propKeys.push(field);

        // Write through the reactive proxy (if present) so Vue is notified.
        const target = this._proxy || this
        target[field] = value;
        return true;
    }

    setProperties(newData={}) {       
        // console.log('[PropObjectClass.setProps]', newData);
		const target = this._proxy || this  // если proxy есть — пишем через него

		for (let key in newData) {
			if (!this.propKeys.includes(key))
				this.propKeys.push(key);

			target[key] = newData[key];
		}

		return true;
    }

    removeProperty(key) {
        if ( !key )
            return false;

        if ( !this.propKeys.includes(key) )
            return true;

        delete this[key];        
        // delete this.propKeys[key]
        this.propKeys = this.propKeys.filter(k => k !== key)

        return true;
    }

    // newKey(key) {
    //     if ( !this.propKeys.includes(key) )
    //         this.propKeys.push(key);

    //     return true;
    // }
}