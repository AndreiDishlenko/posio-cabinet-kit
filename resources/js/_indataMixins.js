export default {
    props: {
        in_data: {
            type: Object,
            default: {}
        }
    },
    data() {
        return {
            form_data: {},
        };
    },
    watch: {
        in_data: {
            handler(val, oldVal) {
                // console.log('watch.in_data', val);
                // if (!this.is_changed) 
                // this.form_data = Object.assign({}, val);
                this.form_data = JSON.parse(JSON.stringify(val));
            }, 
            deep: true, 
            immediate: true
        },
        form_data: {
            handler(val, oldVal) {
                // console.log('[indataMixins] watch.form_data', this.form_name, val);  
				// console.log('1', JSON.stringify(val));
				// console.log('2', JSON.stringify(this.in_data));
				              
                if ( JSON.stringify(val) != JSON.stringify(this.in_data) ) {
                    this.is_changed = true;
                } else {
                    this.is_changed = false;
                }
                this.$emit('changed', this.is_changed);
                this.$emit('update:form_data', val);
            }, 
            deep: true, 
            immediate: true
        }
    },
    methods: {
        updateInData() {
            // console.log('updateInData', this.form_data);

            Object.keys(this.form_data).forEach(key => {
				// console.log('kk kye', key);
				this.in_data[key] = this.form_data[key] ? JSON.parse(JSON.stringify(this.form_data[key])) : this.form_data[key]
            })

            // this.form_data = JSON.parse(JSON.stringify(val));
        },

        updateInDataFields(fields) {
            // console.log('updateInDataFields', fields);

            Object.keys(fields).forEach(key => {
                this.form_data[key] = fields[key]
                this.in_data[key]   = fields[key] !== null && fields[key] !== undefined
                    ? JSON.parse(JSON.stringify(fields[key]))
                    : fields[key]
            })
        },

        deleteInDataFields(keys) {
            // console.log('deleteInDataFields', keys);

            keys.forEach(key => {
                delete this.form_data[key]
                delete this.in_data[key]
            })
        },
    }
};