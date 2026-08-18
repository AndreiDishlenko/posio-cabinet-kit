<template>
    <div v-if="isOpen" class="modal-wrapper">
        <div class="modal-card">
            <slot></slot>
        </div>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                isOpen: false
            }
        },
        methods: {
            open() {
                this.isOpen = true;
            }
        }
    }
</script>

<style lang="scss" scoped>
    .modal-wrapper {
        position: absolute;
        z-index: 999;
        left: 0px;
        top: 0px;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);

        display: flex;
        justify-content: center;
        align-items: center;
    }

    .modal-card {
        min-width: 400px;
    }

    /* .modal {
        position: fixed;
        z-index: 999;
        top: 20%;
        left: 50%;
        width: 300px;
        margin-left: -150px;
    } */
</style>