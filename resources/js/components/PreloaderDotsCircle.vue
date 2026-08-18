<template lang="">
    <div class="circle-preloader">
		<div class="loader">
			<div class="dot"></div>
		</div>
		<div class="loader">
			<div class="dot"></div>
		</div>
		<div class="loader">
			<div class="dot"></div>
		</div>
		<div class="loader">
			<div class="dot"></div>
		</div>
		<div class="loader">
			<div class="dot"></div>
		</div>
		<div class="loader">
			<div class="dot"></div>
		</div>
    </div>
</template>

<script>
    export default {
		props: ['wait_text']
    }
</script>

<style lang="scss">
    /* @import url('https://fonts.googleapis.com/css?family=Poppins:200,300,400,500,600,700&display=swap');
    *{
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    } */
	.circle-preloader {
		height: 0px;
	}
    .circle-preloader .loader {
        height: 40px;
        /* width: 1px; */
        position: absolute;
        animation: rotate 3.5s linear infinite;
    }
    .circle-preloader .loader .dot {
        top: 30px;
        height: 4px;
        width: 4px;
        background: #fff;
        border-radius: 50%;
        position: relative;
    }
    .circle-preloader .text {
        position: absolute;
        bottom: -85px;

        left: 0;
        right: 0;
        margin-left: auto;
        margin-right: auto;

        font-size: 20px;
        font-weight: 200;
        font-family: 'Poppins',sans-serif;
        color: #fff;
    }
    @keyframes rotate {
        30%{
            transform: rotate(220deg);
        }
        40%{
        	transform: rotate(450deg);
            opacity: 1;
        }
        75%{
			transform: rotate(720deg);
			opacity: 1;
        }
        76%{
        	opacity: 0;
        }
        100%{
			opacity: 0;
			transform: rotate(0deg);
        }
    }
    .circle-preloader .loader:nth-child(1 ){
        animation-delay: 0.15s;
    }
    .circle-preloader .loader:nth-child(2) {
        animation-delay: 0.3s;
    }
    .circle-preloader .loader:nth-child(3) {
        animation-delay: 0.45s;
    }
    .circle-preloader .loader:nth-child(4) {
        animation-delay: 0.6s;
    }
    .circle-preloader .loader:nth-child(5) {
        animation-delay: 0.75s;
    }
    .circle-preloader .loader:nth-child(6) {
        animation-delay: 0.9s;
    }
</style>
