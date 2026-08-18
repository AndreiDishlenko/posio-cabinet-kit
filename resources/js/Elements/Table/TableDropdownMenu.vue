<template lang="">

    <Dropdown ref="dropdown" class="context_menu" :style="floating_dd_style"
        :align="'left'" 
        :downOnHover="false"
        :downOnClick="false"
        :transition="'dropdown'"
        :buttonclass="'dropdown-button grow flex items-center p-0'"  
        @cancel="() => {$emit('cancel')}"
        >

        <template #button> 
            <div class=""></div>
        </template>

        <template #dropdownitems="{ direction }">

            <SelectableItems class=""
                ref="sel_items"
                :in_data="menu_items"
                :items_class="''"
                :direction="direction"
                @selectItem = "(e, item) => selectOption(item.id)"
                />

                <!-- :selected_index = "selectedIndex"
                @selectItem="(e, item)=>{ submitItem(item) }" -->


        </template>

    </Dropdown>

</template>

<script>
    import Dropdown         from '@/js/Elements/Dropdown.vue';
    import SelectableItems  from '@/js/Elements/Forms/SelectableItems.vue'

    export default {
        components: { Dropdown, SelectableItems },
        data() {
            return {
                menu_items: [
                    { id:1, name: this.$t('Delete Row') }
                ],
                coordinates: {
                    x: 0,
                    y: 0
                }
            }
        },
        computed: {
            floating_dd_style() {
                return {
                    position: 'fixed!important',
                    top: this.coordinates.y+'px!important', //this.area_top + '10px',
                    left: this.coordinates.x+'px!important', //adjustedLeft + 'px',
                    // minWidth: this.area_width + 'px',
                    // maxHeight: dropdownHeight + 'px'                    
                }
            }
        },
        methods: {
            open(x,y) {
                this.coordinates = {x,y}
                this.$nextTick(() => {
                    this.$refs.dropdown.open();
                })
                
            },
            selectOption(id) {
                switch (id) {
                    case (1):
                        this.$emit('deleteRow')                        
                        break;
                    default:
                        break;
                }

                this.$refs.dropdown.close()
                return true;
            }
        }
    }
</script>

<style lang="scss" scoped>
    
</style>