<template lang="">

    <div class="cabinet-body">

        <slot/>
		
    </div>  

</template>

<script>
    export default {
        mounted() {
            // console.log('[CabinetBody.mounted]');            
        }
    }
</script>

<style lang="scss">
    .cabinet-body {
        // Единица вьюпорта приходит переменной: пара объявлений её не страхует,
        // потому что значение с var() проверяется после каскада — непонятая
        // единица обнулила бы высоту, а на ней держится всё тело кабинета.
        height: calc( var(--viewport-height) - var(--header-height) );
    }
</style>