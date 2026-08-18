<template lang="">
    <div class="transparent-overlay" :class="!isVisible && 'hidden'">        
    </div>
</template>

<script>
    export default {
        props: {
            isVisible: {
                type: Boolean,
                default: false
            }
        }
    }
</script>

<style lang="scss" scoped>
	.transparent-overlay {
		position: absolute;
		width: 100%;
		height: 100%;
		left:0px;
		top:0px;
		z-index: 1000;
		background: rgb(0,0,0,0.4);
	}
</style>