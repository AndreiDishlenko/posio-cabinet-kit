<template lang="">

    <Dropdown ref="dropdown" class="selectable-dropdown" 
        :align="'left'" 
        :downOnHover="false"
        :downOnClick="false"
        :transition="'dropdown'"
        :buttonclass="'dropdown-button grow flex items-center p-0'"
        :bg_color="'var(--selectable-background-color)'"
        :dropareaclass="dropareaclass+' rounded-t-none'"
        @cancel= "() => {$emit('cancel')}"		
        >

        <template #button> 

			<!-- console.log('i.input'); -->
			 <!-- console.log('i.change'); -->
			  <!-- console.log('si.enter'); -->
            <Input
                ref="input"
                v-model = "searchString"
                :input_class  = "input_class"
                :class="isError ? 'error' : null"
                :clearButton	= "clear_button"
				:placeholder 	= "placeholder"

                @input          		= "(e) => { inputText() }"  
				@change.stop.prevent	= "(e) => {}"
                @clearInput     		= "clearInput()"				

                @keydown.enter.stop.prevent = "(e) => { enterAction(e)}"
                @keydown.esc                = "(e) => escAction(e)"
                @keydown.tab.stop.prevent   = "$emit('onTabKey')"

                @keydown.up.stop.prevent    = "(e) => selectPrev(e)"
                @keydown.down.stop.prevent  = "(e) => selectNext(e)"
                @keydown.left.stop  = "(e) => leftPress(e)"
                @keydown.right.stop = "(e) => rightPress(e)"

				@blur.stop = "handleBlur"
                />
                
        </template>

        <template #dropdownitems="{ direction }">

			<div ref="dropdownitems_wrapper">
				<SelectableItems
					ref="sel_items"
					:in_data="search_data"
					:text_field="'name'"
					:items_class="''"
					:selected_index = "selectedIndex"
					:custom_view = "true"
					:direction = "direction"
					:reverse_when_up = "true"
					@selectItem.stop.prevent  = "(e, item)=>{ onItemSelect(e, item) }"
					/>
			</div>

        </template>

    </Dropdown>

</template>

<script>
    import Dropdown from '@/js/Elements/Dropdown.vue';
    import Input    from './Input.vue';
    import SelectableItems  from './SelectableItems.vue'

    import { Icon } from '@iconify/vue';

    export default {
        components: { Dropdown, Input, SelectableItems, Icon },
        props: {
            'in_data': {
				type: Object,
                default: []
			},
			'modelValue': {
                type: [String, Number, null],
                default: ''
			},
            input_class: {
                type: String,
                default: ''
            },
            dropareaclass: {
                type: String,
                default: ''
            },
            name_field: {
                type: String,
                default: 'name'
            },
			zero_name: {
				type: String,
				default: ''
			},
			is_openable: {
				type: Boolean,
				default: true
			},
			clear_button: {
				type: Boolean,
				default: false
			},
			placeholder: {
				type: String,
				default: ''
			}
        },
        data() {
            return {
                // search_data: [],
                searchString  : '',
                selectedIndex : null, 
                // selected_id   : null,
                isError       : null,
				isCommited	  : true
                // debounceTimer: null,
                // isWatchDisabled: true,
            }
        },
        watch: {
            'modelValue': {
                handler(new_val, old_val) {
                    this.$nextTick(() => {
                        this.restoreInputValue();
                    })
                },
                immediate: true,
                deep: true
            },
			'is_openable': {
				handler(new_val, old_val) {
					// console.log('watch is_openable');				
					if (new_val != old_val && !new_val)
						this.close()

                },
                immediate: true,
                deep: true
			}
        },
        computed: {
            search_data() {
                // console.log('ff', this.in_data);
                // console.log('aa', this.searchString);
				let selectable_data = [
					...this.in_data
				]

				if ( this.zero_name )				
					selectable_data.unshift({ id:0, name:this.zero_name})

                return selectable_data.filter(t => (this.searchString && String(t.name).toLowerCase().includes(String(this.searchString).toLowerCase())) )
            }
        },
        methods: {
            open() {
				// console.log('SelectableInput.open');
				if ( !this.is_openable )
					return false;

				this.$refs.dropdown.open()
                this.$refs.input.focus();
            },
            close() {
				// console.log('SelectableInput.close');				
                this.$refs.dropdown.close()
            },
            focus() {
				// console.log('SelectableInput.focus');
                this.$refs.input.focus();
            },
            select() {
                this.$refs.input.select();
            },

            async inputText() {
                // console.log('inputText', this.searchString);
                this.selectedIndex = null
				this.isCommited = false

                if (this.search_data.length)
                    this.open()
                else
                    this.close()

				// this.$emit('input', this.search_data)	// 26
            },
            // handleInputChange(e) {      
			// 	// console.log('SelectableInput.handleInputChange');				          
            //     setTimeout(() => {
            //         // console.log('SelectableInput.handleInputChange');
            //         let search_data = this.in_data.filter(t => (this.searchString && String(t.name)==String(this.searchString)))
            //         // console.log('g1', search_data.length, search_data[0].id, this.modelValue);
                    
            //         if (!search_data.length || search_data[0].id!=this.selectedIndex) {
            //             this.restoreInputValue()
            //             // this.$refs.input.select()
            //         }
            //     }, 100)
            // },
			handleBlur(e) {
				// console.log('SelectableInput.handleBlur');
				if (!e.relatedTarget) 
					return false;

				const items_wrapper= this.$refs.dropdownitems_wrapper?.$el || this.$refs.dropdownitems_wrapper;
				if (items_wrapper?.contains(e.relatedTarget)) 
					return false

				if (!this.isCommited)
					this.restoreInputValue()

				this.$emit('blur', e);
				this.close();					
				return true
			},

            // Keys stay natural: ArrowUp always moves the highlight visually up,
            // ArrowDown visually down. When the list opens up it is rendered
            // bottom-to-top (index 0 nearest the input at the bottom), so visual
            // "up" means a higher index — hence the direction check only decides
            // which way the DOM index steps, not which physical key does what.
            selectPrev(e) {
                // ArrowUp
                if ( !this.$refs.dropdown.isOpened() ) {
                    this.$emit('keydown-up', e)
                    return true
                }

                if ( this.$refs.dropdown.computedDirection === 'up' )
                    this.stepIntoList();   // reversed list — up = away from input = index++
                else
                    this.stepTowardInput();
            },
            selectNext(e) {
                // ArrowDown
                if ( !this.$refs.dropdown.isOpened() ) {
                    this.$emit('keydown-down', e)
                    return true
                }

                if ( this.$refs.dropdown.computedDirection === 'up' )
                    this.stepTowardInput();
                else
                    this.stepIntoList();   // normal list — down = away from input = index++
            },
            // Enter the list at the first result (index 0, nearest the input) and
            // then advance away from it. Used by the key pointing into the list.
            stepIntoList() {
                if ( this.selectedIndex == null )
                    this.selectedIndex = 0
                else if ( this.selectedIndex < this.search_data.length - 1 )
                    this.selectedIndex++
                else
                    return

                this.$refs.sel_items?.scrollToSelected()
            },
            // Move back toward the input (index 0). Used by the key pointing out of
            // the list.
            stepTowardInput() {
                if ( this.selectedIndex == null || this.selectedIndex <= 0 )
                    return

                this.selectedIndex--
                this.$refs.sel_items?.scrollToSelected()
            },

            leftPress(e) {
                // dropdown opened — let native caret behaviour stay (up/down navigate list)
                if ( this.$refs.dropdown.isOpened() )
                    return;

                // navigate to neighbour cell only when caret is at the very start
                const el = this.$refs.input?.$refs?.input;
                if ( el && (el.selectionStart > 0 || el.selectionStart != el.selectionEnd) )
                    return; // caret moves natively inside the text

                e.preventDefault();
                this.$emit('keydown-left', e)
            },
            rightPress(e) {
                if ( this.$refs.dropdown.isOpened() )
                    return;

                // navigate to neighbour cell only when caret is at the very end
                const el = this.$refs.input?.$refs?.input;
                if ( el && (el.selectionEnd < el.value.length || el.selectionStart != el.selectionEnd) )
                    return; // caret moves natively inside the text

                e.preventDefault();
                this.$emit('keydown-right', e)
            },

            escAction(e) {
                // console.log('SelectableInput.escAction');
                const wasOpen  = this.$refs.dropdown.isOpened();
                const wasDirty = !this.isCommited;

                // Первый Esc (открыт список или ввод не закоммичен) — откатываем
                // редактирование поля и гасим событие, чтобы форма не закрылась.
                if ( wasOpen || wasDirty ) {
                    e.stopPropagation();
                    e.preventDefault();
                    this.restoreInputValue();
                    this.isCommited = true;
                    this.$refs.input.select();
                    this.close();
                    this.$emit('keydown-esc');
                    return;
                }

                // Откатывать нечего — отдаём Esc форме (DocCardTemplate закроет карточку).
            },
			
            enterAction(e) {
				// console.log('SelectableInput.enterAction', this.$refs.dropdown.isOpened(), this.search_data.length, this.selectedIndex);
                // console.log('SelectableInput.enterAction1', this.isError, this.isCommited);

				if ( this.isCommited)
					return this.$emit('onEnterKey', e)

				// Elements not found
				if ( !this.search_data.length && !this.isCommited ) {
					// console.log('0');				
					this.unsetItem()
					this.$emit('onChange', e)
                    return true
				}

				// Elements found and not selected - select first
				if ( this.search_data.length && this.selectedIndex==null ) {
					// console.log('1');
					return this.stepIntoList()
				}

				// Elements found and selected
				if ( this.search_data.length && this.selectedIndex!=null ) {		
					// console.log('2');			
					let selectedItem = this.search_data[this.selectedIndex];
					this.setItem(selectedItem)
					this.$emit('onChange', e)
					return true
				}

                // Select case
                if ( this.$refs.dropdown.isOpened() ) {
                    // console.log('selectedIndex', this.selectedIndex);
					// console.log('2');
					// if ( this.search_data.length>1 && this.selectedIndex==null) {
                    //     return this.selectNext()
                    // }  

					// // console.log('3');
					// if ( this.selectedIndex==null || !this.search_data[this.selectedIndex] )
                    //     return this.isError = 'Select an item';

					// // console.log('4');
					// let selectedItem = this.search_data[this.selectedIndex];
                    // this.setItem(selectedItem)
					// // this.$emit('onEnterKey', e)
					// this.$emit('onChange', e)
                    // this.isError = null
                    // return true
                } 

				this.$emit('onEnterKey', e)

                return true
            },

			onItemSelect(e, item) {
				// console.log('SelectableInput.onItemSelect', item);
				let old_val = this.modelValue?.id ?? null

				this.setItem(item)

				if (item && item.id != old_val)
					this.$emit('onChange', e)				
			},

            setItem(item) {
                // console.log('SelectableInput.setItem', item);
				if ( !item )
					return false
				
				this.isError = null
				this.selectedIndex = null
                this.searchString = item?.id ? item.name : '';
                this.$emit('update:modelValue', item?.id ? item.id : '');
                
                this.$refs.input.select();
				this.isCommited = true;
                this.close();
            },

			unsetItem() {
				// console.log('SelectableInput.unsetItem');
				this.$emit('update:modelValue', null);

				this.$refs.input.select();
				this.isCommited = true;
				this.close();
			},

            restoreInputValue() {
                // console.log('restoreInputValue', this.modelValue);                
                let items = this.in_data.filter( t => t.id == this.modelValue );                
                if ( items[0] )
                    this.searchString = items[0].name;
                else
                    this.searchString = '';
				// this.searchString = this.modelValue;
            },
            clearInput(event) {
                // console.log('clearInput');

				this.searchString = "";
				this.selectedIndex = null;
				this.$emit('update:modelValue', null);

				this.$emit('onChange', this.searchString)	// 26

                // this.search_data=[];
                // this.$emit('update:modelValue', '')
                // this.$emit('clearInput')

                // this.$nextTick(() => {
                //     this.isWatchDisabled = false;
                //     this.$refs.input.focus();
                // });

            },
        }
    }
</script>

<style scoped>
    .selectable-dropdown {
        .dropdown-button.opened .form-control {
            border-bottom-left-radius: 0px!important;
            border-bottom-right-radius: 0px!important;
        }

        .dropdown-area {
            /* border-radius: var(--form-control-border-radius);
            border-top-left-radius: 0px;
            border-top-right-radius: 0px; */
            background-color: var(--selectable-background-color);
            border-width: 0px 1px 1px 1px;
            /* border-color: var(--form-control-border-color); */
            width: 100%;
        }
    }

    .searchResults {
        position: absolute;
        top: 100%;
        width: 100%;
        z-index: 1000;
        background-color: var(--dropdown-background-color);
    }

    .tags-container {
        display: flex; /* Используем flexbox */
        flex-wrap: wrap; /* Разрешаем перенос элементов */
        gap: 12px; /* Отступы между тегами */
        padding: 20px; /* Внутренние отступы контейнера */
        border-radius: 0px 0px 5px 5px; /* Скругленные углы */
    }

    /* Ряд с переносом: отступ раздаётся всем тегам, лишняя рамка компенсируется
       уменьшенным полем контейнера — отрицательное поле здесь вылезло бы наружу. */
    html.no-flex-gap .tags-container {
        padding: 14px;
    }

    html.no-flex-gap .tags-container > * {
        margin: 6px;
    }

    .tag {
        display: inline-block; /* Для корректного отображения текста */
        padding: 8px 12px; /* Внутренние отступы кнопки */
        border-radius: 4px; /* Скругленные углы */
        font-size: 14px; /* Размер шрифта */
        cursor: pointer; /* Курсор как при наведении на кнопку */
        transition: background-color 0.3s; /* Плавное изменение цвета */
        background-color: var(--light-background);
    }

    .input-icon {
        color: var(--icon-color, var(--text-color));
        @apply
            absolute
            right-3
            top-1/2
            transform
            -translate-y-1/2
            cursor-pointer
            pointer-events-auto;
    }

    .error {
        border: 1px solid var(--error-color);
    }
</style>