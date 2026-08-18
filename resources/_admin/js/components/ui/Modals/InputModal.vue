<template>
    <VueFinalModal
        class="confirm-modal"
        content-class="card"
        overlay-transition="vfm-fade"
        content-transition="vfm-fade"
        @opened="setFocusOnElement"
    >
        <VeeForm  @submit="$emit('confirm', inputfield)">
            <div v-if="title" class="card-header font-bold">{{ title }}</div>

            <div class="card-body ">
                <div class="label-group">
                    <label for="invites" class="form-label mb-1"><slot/></label>
                    <Field ref="name" :type="type" name="name" v-model="inputfield" class="form-control w-full me-3" placeholder="" @change="$refs.name.validate()"/>
                </div>
            </div>

            <div class="card-footer">
                <button class="button primary-button" @click="$emit('confirm', inputfield)">{{ $t('Confirm') }}</button>
                <button class="button" @click="$emit('cancel')">{{ $t('Cancel') }}</button>
            </div>
        </VeeForm>

    </VueFinalModal>
</template>

<script>
    import { Form as VeeForm, Field, ErrorMessage } from 'vee-validate';
    import { VueFinalModal } from 'vue-final-modal'
    export default {
        components: { VueFinalModal, VeeForm, Field },
        emits: ['confirm', 'cancel'],
        props: {
            title: {
                type: String,
                default: ''
            },
            type: {
                type: String,
                default: 'text'
            }
        },
        data() {
            return {
                inputfield: ''
            }
        },
        methods: {
            setFocusOnElement() {
                this.$refs.name.$el.focus();
            }
        }
    }
</script>

<style lang="scss">
    .confirm-modal {
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>