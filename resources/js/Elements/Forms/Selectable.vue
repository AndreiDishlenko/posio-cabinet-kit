<template>

    <SelectableCustom 
        ref="parentelement" 
        class="selectable"    
        dropareaclass=""              
        :text="text"
        :input_class="input_class"
        :placeholder="placeholder ? placeholder : 'Not selected'"
        :is_mandatory="is_mandatory"
        :isFloating="isFloating"          
        :clearButton = "addall && selected_id ? true : false"
        :size        = "size"
        :bg_color    = "bg_color"
        @clear="clear()"
        >

        <template #menuitems="{ direction }">

            <SelectableItems class="py-1"
                :in_data="source_data"
                :text_field="text_field"
                :items_class="[items_class, sizeItemsClass].filter(Boolean).join(' ')"
                :size="size"
                :bg_color="bg_color"
                :direction="direction"
                @selectItem="(e, item)=>{selected_id = item.id; $refs.parentelement.close();}"
                />

        </template>

    </SelectableCustom>

</template>

<script>
	import { reactive } from 'vue';

    import SelectableCustom from '@/js/Elements/Forms/SelectableCustom.vue';
    import SelectableItems  from './SelectableItems.vue';

    export default {
        components: { SelectableCustom, SelectableItems },
        props: {
			'in_data': {
				type: Object,
                default: []
			},
			'modelValue': {
                type: [String, Number, null],
                default: ''
			},
			'filter': {
				type: Object,
                default: {}
			},
			'addnull': {
				type: Boolean,
				default: false
			},
			'addall': {
				type: Boolean,
				default: false
			},
            'zero_name': {
                type: String,
                default: ''
            },
			// 'disabled': {
			// 	type: Boolean,
			// 	required: false,
			// 	default: false
			// },
			'text_field': {
				type: String,
				required: false,
				default: 'name'
			},
            'input_class': {
                type: String,
                default: ''
            },
            'is_mandatory': {
                type: Boolean,
                default: false
            },
            'items_class': {
                type: String,
                default: ''
            },
            'isFloating': {
                type: Boolean,
                default: false
            },
            'placeholder': {
				type: String,
                default: ''
            },
            'default': {
                type: [String, Number, null],
                default: ''
            },
            'size': {
                type: String,
                default: ''
            },
            // Список телепортируется в body, поэтому переменную с фона поля он не
            // унаследует — цвет области задаётся значением, а не наследованием.
            'bg_color': {
                type: String,
                default: 'var(--selectable-background-color)'
            }
		},
		// emits: [
		// 	'onUpdate',
        //     'onChange',
		// 	'update:modelValue',
		// 	'change',
        //     'input'
		// ],
        data: function() {
            return {
                disabled_by_fieldset: false,
                hasDisabledClass: false
            }
        },
        computed: {
            sizeItemsClass() {
                if (!this.size) return '';
                return `h-${this.size} text-${this.size}`;
            },
			text: function() {
				if ( !this.source_data.length )
					return '';
                
                
				let item = this.source_data.filter(t=>t.id==this.selected_id);			
                
				let result = item.length ? this.$t(item[0][this.text_field]) : '';
				return result;
			},
            selected_id: {
                get() {
                    return this.modelValue;
                },
                set(value) {
                    const old_value = this.modelValue;                   
                    this.$emit('update:modelValue', value);
                    this.$emit('onUpdate', value);
                    this.$emit('onChange', value, old_value);
                    this.$emit('change', window.event);
                    this.$emit('input');
                }
            },
			source_data: function() {
				// console.log('computed_source_data', this.in_data);				
				let result = Object.assign(reactive([]), this.in_data);
                
				// if (!this.filter || !Object.keys(this.filter).length)
				// 	return result;

				// Apply filter
				for (let key in this.filter) {
                    // console.log('key', key, this.filter[key]);
                    
                    // if ( !this.filter.hasProperty(key) )
					// 	continue;
					// if (!result[0])
					// 	continue;
					// if (!result[0][key])
					// 	continue;
					// console.log('key', key, e[key], this.filter[key])
					result = result.filter(e => e[key] == this.filter[key]) 
				}
				// console.log('aa', result);
				
                // Псевдо-пункт «нічого не вибрано» підписуємо тим самим полем, що й решта
                // списку, — інакше при нестандартному полі підпису він виглядав би порожнім.
                if (this.zero_name)
					result.unshift({
						id: 0,
						[this.text_field]: this.zero_name
					})

				// if (this.addnull)
				// 	result.unshift({
				// 		id: '',
				// 		name: ''
				// 	})

				// if (this.addall)
				// 	result.unshift({
				// 		id: '',
				// 		name: this.$t(this.placeholder ? this.placeholder : 'All')
				// 	})

				// Select previous
				// if ( !result.filter(e =>e.id==this.selected_id).length )
				// 	this.selected_id=0;					

				return result;
			}
        },
		mounted() {
            // console.log('mnt');            
            this.checkFieldsetDisabled()
            window.addEventListener('change', this.checkFieldsetDisabled);
		},
        updated() {
            this.$nextTick(() => {
                this.hasDisabledClass=false;
                let element = this.$refs.parentelement?.$el;
                if (!element) return;
                while (element && element !== document.body) {
                    if (element.classList && element.classList.contains('disabled'))
                        return this.hasDisabledClass=true;
                    element = element.parentElement;
                }
            });
        },
        unmounted() {
            window.removeEventListener('change', this.checkFieldsetDisabled);
        },
        methods: {
            focus() {
                this.open()
            },
            select() {
                // this.open()
            },
            checkFieldsetDisabled() {
                if (this.$refs.rootDiv) {
                    const fieldset = this.$refs.rootDiv.closest('fieldset');
                    this.disabled_by_fieldset = fieldset ? fieldset.disabled : false;
                }
            },
            open() {
                if (this.hasDisabledClass)
                    return false;
                
                this.$refs.parentelement.open();
            },
            close() {
                this.$refs.parentelement.close();
            },
            clear() {
                this.selected_id = this.default
                this.close()
            }
        }
    }
</script>

<style lang="scss" scoped>

    .selectable {
        min-width:100px;
    }


</style>