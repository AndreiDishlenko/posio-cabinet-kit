<template lang="">

    <!-- {{$page.props.cabinetMenu}} -->

    <Sidemenu class=""
        :in_data    = "$page.props.cabinetMenu" 
        :current_id = "$page.props.currentPage?.id" 
        :activeAccountId = "$page.props.account?.id"
        :disabled   = "disabled"
        />

</template>

<script>
    import Sidemenu         from './SideMenu.vue';

    export default {
        components: { Sidemenu },
        props: {
            disabled: {
                type: Boolean,
                default: false
            }
        },
        methods: {
        }
    }
</script>

<style lang="scss" scoped>
</style>
