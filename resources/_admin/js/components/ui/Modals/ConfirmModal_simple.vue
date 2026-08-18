<template lang="">

    <!-- <VueModal ref="modal" :show="true">

        <div class="card h-full w-full">
            <div class="card-header"></div>
            <div class="card-body">
                <p>Hello from the modal!</p>
                <button @click="isOpen = false">Close</button>
            </div>
            <div class="card-footer"></div>
        </div>

    </VueModal> -->

    <ModalsContainer />

</template>

<script>
    import { ModalsContainer, useModal  } from 'vue-final-modal'

    export default {
        components: { VueModal },
        methods: {
            openModal() {

            },
            confirmAction() {
                console.log(this.$refs.modal);
                this.$refs.modal.open();
            }
        }
    }
</script>

<style lang="scss">
    
</style>
