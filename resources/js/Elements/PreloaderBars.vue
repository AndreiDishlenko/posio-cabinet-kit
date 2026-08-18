<template lang="">

    <div class="bar-preloader v-center">
        <div class="container">
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
        </div>
    </div>

</template>

<script>
    export default {
        
    }
</script>

<style lang="scss" scoped>

    .bar-preloader {
        top: 0px;
        left: 0px;
        width: 100%;
        height: 100%;
        // border: 1px solid green;
        position: absolute;
        z-index: 1000;
        background-color: var(--background-color);
        opacity: 0.7;
    }

    .container {
        display: flex;
        justify-content: center;
        align-items: flex-end; 
        width: 60px;
        height: 40px; 
    }

    .bar {
        width: 4px;
        height: 7px;
        border-radius: 1px;
        // background-color: white;
        background-color: rgba(255, 255, 255, 1);
        transform-origin: bottom;
        position: relative;
        margin:2px;
        opacity: 1
    }

    // .cap {
    //     position: absolute;
    //     top: 0;
    //     left: 0;
    //     width: 100%;
    //     height: 10px; /* Фиксированная высота "колпачка" */
    //     background-color: red;
    //     border-radius: 10px 10px 0 0; /* Закругление только на "колпачке" */
    // }

        /* Анимация для каждого столбика с разной задержкой */
    .bar:nth-child(1) {
        animation: grow 1.5s infinite ease-in-out;
    }
    .bar:nth-child(2) {
        animation: grow 1.5s infinite ease-in-out 0.1s; /* Задержка 0.2s */
    }
    .bar:nth-child(3) {
        animation: grow 1.5s infinite ease-in-out 0.2s; /* Задержка 0.4s */
    }
    .bar:nth-child(4) {
        animation: grow 1.5s infinite ease-in-out 0.3s; /* Задержка 0.6s */
    }
    .bar:nth-child(5) {
        animation: grow 1.5s infinite ease-in-out 0.4s; /* Задержка 0.8s */
    }

    @keyframes grow {
        0% {
            transform: scaleY(1); /* Начальная высота */
        }
        20% {
            transform: scaleY(3); /* Вырастает в 3 раза */
        }
        40% {
            transform: scaleY(1); /* Уменьшается обратно */
        }
        100% {
            transform: scaleY(1); /* Пауза до конца цикла */
        }
    }

    /* Общая настройка длительности и паузы */
    .bar {
        animation-duration: 2s; /* Длительность одного цикла анимации */
        animation-iteration-count: infinite; /* Бесконечные повторы */
        animation-timing-function: ease-in-out; /* Плавность */
        animation-delay: 0s; /* Базовая задержка, переопределяется для каждого */
        animation-fill-mode: forwards; /* Сохраняет состояние */
    }
</style>