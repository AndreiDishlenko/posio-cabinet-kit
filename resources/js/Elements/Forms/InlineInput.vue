<template lang="">

    <div class="form-group">
        <label class="form-label" :class="label_class">{{ label ? $t(label) : '' }}:</label>

        <!-- <Input v-if="type != 'select'" 
            ref="input" 
            :type="type ? type : 'text'" 
            v-model="data" 
            input_class="form-control-lg md:form-control-lg" 
            :class="input_class"
            /> -->

        <PasswordInput v-if="type == 'password'"
            ref="input"
            v-model="data"
            class="form-control md:form-control-lg"
            :class="input_class"
            :placeholder="placeholder"
            :autocomplete="noautocomplete ? 'new-password' : ''"
            :reveal="reveal"
            :visible="visible"
            @update:visible="(value) => $emit('update:visible', value)"
            />

        <input v-else-if="type != 'select'"
            ref="input" 
            :type="type ? type : 'text'" 
            v-model="data" 
            class="form-control md:form-control-lg" 
            :class="input_class"
            :placeholder="placeholder"
            :autocomplete="noautocomplete ? 'new-password' : ''"
            />

        <Selectable v-else
            ref="select" 
            v-model="data" 
            :in_data="source"
            />

        <p v-if="error" class="grow"></p>
        <p v-if="error" class="form-error" >{{ error }}</p>
    </div>

</template>

<script>
    import Input        from './Input.vue'
    import Selectable   from './Selectable.vue'
    import Checkbox     from './Checkbox.vue'
    import PasswordInput from './PasswordInput.vue'

    export default {
        components: {Input, Selectable, Checkbox, PasswordInput},
        props: {
            type: {
                type: String,
                default: ''
            },
            label: {
                type: String,
                default: ''
            },
            modelValue: {
                type: [String, Number],
                default: ''
            },
            error: {
                type: String,
                default: ''
            },
            label_class: {
                type: String,
                default: ''
            },
            input_class: {
                type: String,
                default: ''
            },
            source: {
                type: Array,
                default: []
            },
            noautocomplete: {
                type: Boolean,
                default: false
            },
            placeholder: {
                type: String,
                default: ''
            },
            // Глазик и общая видимость — только для поля пароля.
            reveal: {
                type: Boolean,
                default: true
            },
            visible: {
                type: Boolean,
                default: null
            }
        },
        data() {
            return {                
            }
        },
        computed: {
            data: {
                get() {
                    return this.modelValue;
                },
                set(new_val) {
                    this.$emit('update:modelValue', new_val);
                }
            }
        },
        methods: {
            focus() {
                this.$refs.input.focus();
            }
        }
    }
</script>

<style lang="scss" scoped>
    
</style>