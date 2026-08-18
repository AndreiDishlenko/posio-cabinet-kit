<template lang="">
    
    <div v-if="src" class="avatar-wrapper">
        <img :src="`/storage/${src}`" class="avatar-image"/>
    </div>
    <div v-else-if="user_name" class="avatar-wrapper background-secondary">
        <span class="unknown-avatar text-sm">{{ makeInitials(user_name) }}</span>
    </div>
    <Icon v-else-if="fallback_icon" :icon="fallback_icon" class="avatar-wrapper"/>

</template>

<script>
    import { Icon } from '@iconify/vue';
    
    export default {
        components: { Icon },
        props: {
            src: {
                type: String,
                default: ''
            },
            user_name: {
                type: String,
                default: ''
            },
            fallback_icon: {
                type: String,
                default: ''
            },
            size: {
                type: String,
                default: 'base'
            }
        },
        computed: {
            icon_width() {
                let result = '36px';

                if (this.size=='md')
                    result = '30px';

                if (this.size=='lg')
                    result = '48px';

                if (this.size=='xl')
                    result = '64px';

                if (this.size=='xxl')
                    result = '96px';
                
                return result;
            }
        },
        methods: {
            makeInitials(string) {
                if (!string || typeof string !== 'string') 
                    return '?';

                const words = string.trim().split(/\s+/).filter(word => word.length > 0);
                let initials = '';

                if (words.length === 1) 
                    initials = words[0].slice(0, 2);
                else 
                    initials = words.map(word => word.slice(0, 1)).join('').slice(0, 2);

                return initials.toUpperCase();
            }
        }
    }
</script>

<style lang="scss" scoped>
    .avatar-wrapper {
        /* position: relative; */
        min-width: v-bind(icon_width);
        max-width: v-bind(icon_width);
        width: v-bind(icon_width);
        // Высота задана явно, а не пропорцией сторон: её Apple держит только с 15.
        height: v-bind(icon_width);

        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 50%;        
        overflow: hidden;

        padding: 0px;
        margin: 0px;

        /* border-color: var(--text-color-secondary); */
        /* border-width: 2px; */
    }

    .background-secondary {
        background: var(--button-background);
    }

    .avatar-image {
        width: 100%;
        /* 
        min-width: 35px;
        min-height: 35px;
        width: 35px;
        height: 35px;
         */
    }

    .unknown-avatar {
        /* padding: 6px; */
        font-size: var(--text-md);
        color: var(--text-secondary);
        font-weight: 600;
        /* fill: var(--text-color-secondary); */
        
        /* background: var(--card-background); */

    }

</style>
