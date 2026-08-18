import { validate }         from 'vee-validate';
import indataMixins        from './_indataMixins';

import Selectable           from '@/js/Elements/Forms/Selectable.vue';
import Input                from '@/js/Elements/Forms/Input.vue';

export default {
    mixins: [indataMixins],
    components: { Selectable, Input },
    data() {
        return {
			form_name: '',
            form_data_errors: {},
            form_data_warnings: {},
            // inprogress: false,
            is_changed: false,
            // Тимчасово вимикає валідацію (напр. при розпроведенні документа,
            // коли не треба підсвічувати поля червоним).
            skip_validation: false,
            eventListeners: [],
        };
    },
    mounted() {
        this.$nextTick(() => {
            this.setupInputListeners();
        });
    },
    beforeUnmount() {
        this.unsetupInputListeners();
    },
    methods: {
        setupInputListeners() {
            const inputs = this.inputRefs();
            inputs.forEach((input, index) => {
                const changeHandler = () => this.validateField(input.refName);
                const keydownHandler = (e) => {
                    if (e.key === 'Enter' || e.key === 'Tab') {
                        // console.log('inputs', inputs);
                        e.preventDefault();
                        // Programm Tab
                        this.validateField(input.refName);
                        for (let i=index+1;i<inputs.length;i++) {
                            const next = inputs[i];
                            if ( next.element.closest('.disabled') )
                                continue;

                            next.element.focus();
                            break;
                        }                   
                    }
                };

                input.element.setAttribute('autocomplete', 'new-password')
                input.element.addEventListener('change', changeHandler)
                input.element.addEventListener('keydown', keydownHandler);

                this.eventListeners.push({
                    element: input.element,
                    changeHandler,
                    keydownHandler,
                });
            });
        },
        unsetupInputListeners() {
            this.eventListeners.forEach(({ element, changeHandler, keydownHandler }) => {
                element.removeEventListener('change', changeHandler);
                element.removeEventListener('keydown', keydownHandler);
            });
            this.eventListeners = [];
        },
        inputRefs() {
            const allRefs = this.$refs
            const formBlock = this.$refs.form
            if ( !formBlock )
                return [];

            const inputRefs = Object.entries(allRefs)
                .filter(([key, el]) => {
                    return el instanceof HTMLElement &&
                        formBlock.contains(el) &&
                        el.tagName === 'INPUT'
                })
               .map(([key, el]) => ({ refName: key, element: el }))

            return inputRefs;
        },
		
		collectAllRefs(refs) {
            if (!refs) return {};

            let allRefs = {};
            for (let key in refs) {
                const ref = refs[key];
                if (ref) {
					allRefs[key] = ref;
                    if (ref.$refs) {
						const nestedRefs = this.collectAllRefs(ref.$refs);
						allRefs = {...allRefs, ...nestedRefs}
					}
            	}
            }
			
            return allRefs;
        },

		focusFirstError(form_errors, refs) {
            // console.log('focusFirstError', form_errors);           
            const dom_el_id = Object.keys(form_errors)[0]

			refs = refs ?? this.collectAllRefs(this.$refs)
			// console.log('refs', refs);
			
			
            const dom_el = refs[dom_el_id];

            if (dom_el && (dom_el.tagName === 'INPUT' || dom_el.tagName === 'TEXTAREA')) {
                dom_el.focus();
                dom_el.select();
            }

            // Selectable opens on focus
            // if ( this.$refs[dom_el_id] && this.$refs[dom_el_id].open && typeof this.$refs[dom_el_id].open == 'function')
            //     this.$refs[dom_el_id].open();

            if ( refs[dom_el_id] && refs[dom_el_id].focus && typeof refs[dom_el_id].focus == 'function')
                refs[dom_el_id].focus();

        },

        async validateField(item_name) {
            // console.log('validateField', item_name, this.form_data[item_name], this.validationRules[item_name])
			await this.$nextTick(async () => {
				let val_result = await validate(this.form_data[item_name], this.validationRules[item_name], { values: this.form_data })
				
				if ( val_result.valid ) {
					if (this.form_data_errors[item_name])
						delete this.form_data_errors[item_name]

					return true
				}

				this.form_data_errors[item_name] = this.$t(val_result.errors[0]);
				return false
			})
        },        

		getValueByPath(obj, path) {
			return path.split('.').reduce((acc, key) => acc?.[key], obj)
		},

        async validateData(form_data) {
            // console.log('validateData', form_data, this.validationRules);
            if ( this.skip_validation )
                return {};

            let validation_errors = {};

            for (let key in this.validationRules) {  
				const value = this.getValueByPath(this.form_data, key)

                // console.log('v', key, form_data[key], this.validationRules[key]);                
                let val_result = await validate(value, this.validationRules[key], { name: key, values: this.form_data })
                if ( !val_result.valid )
                    validation_errors[key] = val_result.errors[0];
            }

            return validation_errors;
        },

		async validateForm() {
            // console.log('validateForm');
            this.form_data_errors = {};

            if ( this.skip_validation )
                return true;

            // Collect Refs Data
            // let ref_form_data = {};
            // for (let key in this.$refs) {  
            //     ref_form_data[key] = this.$refs[key] ? 
            //         this.$refs[key].value ? this.$refs[key].value : 
            //         this.$refs[key].modelValue ? this.$refs[key].modelValue : null : null
            // }

            let validation_errors = await this.validateData(this.form_data)			
            if ( !Object.keys(validation_errors).length )
                return true

			// Translate errors into form_data_errors
            Object.keys(validation_errors).forEach(key => {
                this.form_data_errors[key] = this.$t(validation_errors[key]);
            })
            
            console.log('form_data_errors', this.form_data_errors);
            
            this.focusFirstError(this.form_data_errors);

            return false;
        },

		outputErrors(errors, form_data_errors) {
            // console.log('outputErrors', errors);    
            if ( !errors )
                return false;         
            
            this.form_data_errors = {};

            for (let key in errors) {
                // console.log('1', key, errors[key]);
                let error = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
                this.form_data_errors[key] = '* '+this.$t(error);            
            }

            this.focusFirstError(errors)            
        },

        async nextField(source_field, target_field) {
            // console.log('nextField', source_field, target_field);
            let val_result = await validate(this.form_data[source_field], this.validationRules[source_field])
            if ( !val_result.valid )
                return this.form_data_errors[source_field] = val_result.errors[0];
            else
                this.form_data_errors[source_field] = '';

            // console.log('target_field', target_field);  
            if (this.$refs[target_field])          
                this.$refs[target_field].focus();
        },
    }
};