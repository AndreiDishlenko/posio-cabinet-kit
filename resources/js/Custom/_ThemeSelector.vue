<template lang="">
    
    <Toggler class=""
        v-model     = state
        icon_off    = "tabler:sun"
        icon_on     = "tabler:moon"
        thumb_color = "#000000"
        />

    <!-- <button class="theme-switch" :class="state=='dark' ? 'dark' : '', 'form-control'" type="button" role="switch" :title="$t('Theme')" aria-checked="true" @click="toggle()">
        <span class="check">
            <Icon v-if="state=='light'" icon="tabler:sun" class="icon"/>
            <Icon v-if="state=='dark'" icon="tabler:moon" class="icon"/>
        </span>
    </button> -->

</template>

<script>
    import { Icon } from "@iconify/vue";

    import Toggler  from "@/js/Elements/ThemeToggler.vue";

    export default {
        components: { Icon, Toggler},
        data: function() {
            return {
                state: true
            }
        },
        mounted() {
            let saved_theme = localStorage.getItem('theme');
            if ( !saved_theme || saved_theme=='dark') {
                this.state = true;
                this.setTheme('dark');
            } else {
                this.state = false;
                this.setTheme('light');
            }
        },
        watch: {
            state(newVal) {
                if (newVal)
                    this.setTheme('dark');
                else
                    this.setTheme('light');
            }
        },
        methods: {
            toggle: function() {
                if (this.state=='light')                     
                    return this.setTheme('dark');
                
                if (this.state=='dark')                     
                    return this.setTheme('light');                           
            },
            setTheme: function(theme) {
                // console.log('setTheme', theme);                
                if (theme=='dark') {
                    document.documentElement.classList.remove('light');
                    document.documentElement.classList.add('dark');
                    return localStorage.removeItem('theme');                    
                }
                if (theme=='light') {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                    return localStorage.setItem('theme', 'light');
                }   
            }
        }
    }
</script>

<style lang="scss" scoped>
    .theme-switch {
        position: relative;
        border-radius: 11px;
        display: block;
        width: 40px;
        height: 21px;
        flex-shrink: 0;
        /* border: 1px solid var(--input-border-color); */
        /* background-color: rgba(142, 150, 170, .14); */
        transition: border-color .25s !important;
    }
    .dark .theme-switch .check {
        transform: translate(19px);
    }
    .check {
        position: absolute;
        top: 1px;
        left: 1px;
        width: 17px;
        height: 17px;
        border-radius: 50%;
        background-color: var(--form-control-border-color);
        box-shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 1px 2px rgba(0, 0, 0, .06);
        transition: transform .25s !important;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .dark .check{
        /* background-color: inherit; */
    }

</style>