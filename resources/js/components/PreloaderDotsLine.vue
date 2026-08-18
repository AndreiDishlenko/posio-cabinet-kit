<template lang="">
    <div class="line-preloader d-flex flex-column align-items-center">
        <div class="loader">
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
        </div>

    </div>
</template>

<script>
    export default {
		props: ['wait_text']

    }
</script>

<style lang="scss">
    // Внешний шрифт отсюда убран: директива импорта попадает в собранные стили и
    // выполняется при их разборе — прелоадер получал ожидание сети в тот момент,
    // когда со связью и так плохо.

    .container .line-preloader  {
        position: absolute;
        top: 75%;
        /* height:100%; */
        left: 0;
        right: 0;
        margin-left: auto;
        margin-right: auto;
        /* border: 1px solid red; */
    }
    .line-preloader .loader {
        overflow: hidden;
        /* position: fixed; */
        margin-top: 1rem;
        width: 100%;
        height: 25px;
        display: flex;
        align-items:center;
        align-content: center;
        justify-content: center;
        top: 60%;
        z-index:10000;
        /* border:1px solid green; */
    }
    .line-preloader .text {
        /* position: absolute; */
        bottom: -85px;

        left: 0;
        right: 0;
        margin-left: auto;
        margin-right: auto;

        font-size: 20px;
        font-weight: 200;
        font-family: var(--font-ui, sans-serif);
        color: #fff;
    }
    .line-preloader .dot {
        border-radius: 50%;
        height:5px;
        width:5px;
        background-color:#fffcf4;
        margin: 0px calc(5px*2);
    }

    .line-preloader .dot:nth-child(1) {
        animation: line-preloader .7s ease-in-out alternate infinite;
    }

    .line-preloader .dot:nth-child(2) {
        animation: line-preloader .7s ease-in-out alternate .3s infinite;
    }

    .line-preloader .dot:nth-child(3) {
        animation: line-preloader .7s ease-in-out alternate .5s infinite;
    }

    .line-preloader .dot:nth-child(4) {
        animation: line-preloader .7s ease-in-out alternate .8s infinite;
    }

    .line-preloader .dot:nth-child(5) {
        animation: line-preloader .7s ease-in-out alternate 1.2s infinite;
    }

    @keyframes line-preloader {
        100% {
            transform: scale(3);
            box-shadow: 10px 10px 22px -2px rgba(0,0,0,0.25)
        }
    }

</style>
