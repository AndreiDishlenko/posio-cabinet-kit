import Checkbox     from '@/js/Elements/Forms/Checkbox.vue'
import Selectable   from '@/js/Elements/Forms/Selectable.vue'

import InlineInput  from '@/js/Elements/Forms/InlineInput.vue';

export default {
    components: { Checkbox, Selectable, InlineInput},
    props: {
        in_data: {
            type: Object,
            default: {}
        },
        is_editable: {
            type: Boolean,
            default: true
        },
		route_prefix: {
			type: String,
			default: ''
		},
		dictionary_name: {
			type: String,
			default: ''
		}
    },
    data() {
        return {
            // form_data: {},
        };
    },
    watch: {
    },
    methods: {
		getRoutePrefix() {
			if (!this.route_prefix)
				return this.$toast.error('Route prefix is undefined');

			return this.route_prefix
		},
		getDictionaryName() {
			if (!this.dictionary_name)
				return this.$toast.error('Dictionary is undefined');

			return this.dictionary_name
		},

		async saveDictionaryAndClose(row) {
			console.log('saveDictionaryAndClose', this.getDictionaryName());
			
			await this.saveRecordAndClose(row)

			let dictionary_name = this.getDictionaryName()
                
            if (dictionary_name) {
				this.$dictionaries.save(dictionary_name)
                this.$dictionaries.update(dictionary_name)
			}
		},

        async saveRecordAndClose(row) {
			// console.log('modalcardMixins.saveRecordAndClose');

			let result = await this.saveRecord(row)
			if ( result.error)
				return false

			this.$emit('close')
            return true;
        },

		async saveRecord(row) {
            console.log('modalcardMixins.saveRecord', row);
            if ( !await this.validateForm() )
                return {error: `Can't validate form`};

			let route_name = this.getRoutePrefix()+'.update'			

			// console.log('modalcardMixins.saveRecord', JSON.stringify(row));
            let result = await this.$apiClient.post( route( route_name ), row)
            if (result.error) {
                // console.log(result.errors);               
                this.outputErrors(result.errors);
                this.$toast.error( this.$t(result.error) )
                return {error: result.error};
            }

			let response = result.data

			// if ( !row.id )
                row = Object.assign(row, response);

            this.updateInData()

			if (typeof this.onSaveSuccess === 'function') 
				this.onSaveSuccess(response)

            return response;
        },
    }
};