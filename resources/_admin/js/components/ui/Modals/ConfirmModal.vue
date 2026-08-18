<template>
    <VueFinalModal
        class="confirm-modal"
        content-class="card"
        overlay-transition="vfm-fade"
        content-transition="vfm-fade"
    >
        <div class="card-header font-bold">{{ title }}</div>
        <div class="card-body">
            <slot />
        </div>
        <div class="card-footer">
            <button class="button primary-button" @click="$emit('confirm')">{{ $t('Confirm') }}</button>
            <button class="button" @click="$emit('cancel')">{{ $t('Cancel') }}</button>
        </div>
    </VueFinalModal>
</template>

<script>
    import { VueFinalModal } from 'vue-final-modal'
    export default {
        components: { VueFinalModal },
        emits: ['confirm', 'cancel'],
        props: {
            title: {
                type: String,
                default: ''
            }
        },
    }
</script>

<style lang="scss">
    .confirm-modal {
        display: flex;
        justify-content: center;
        align-items: center;
    }
</style>