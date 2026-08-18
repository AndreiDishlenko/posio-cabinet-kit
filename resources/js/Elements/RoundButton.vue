<template lang="">
	
	<div class="button-wrapper">
		<!-- <Icon :icon="mobile_icon" class="icon sm:hidden"/> -->
		<!-- <div class="lt-sm:hidden"> -->
			<slot></slot>
		<!-- </div>		 -->
	</div>

</template>

<script>
	// import { Icon } from '@iconify/vue/dist/iconify.js';

	export default {
		
		// components: { Icon },
		props: {
			mobile_icon: {
				type: String,
				default: ''
			},
			size: {
                type: String,
                default: 'base'
            }
		},
		computed: {
            button_width() {
                let result = '40px';

                if (this.size=='lg')
                    result = '48px';

                if (this.size=='xl')
                    result = '64px';

                if (this.size=='xxl')
                    result = '96px';
                
                return result;
            }
        },
	}
</script>

<style lang="scss" scoped>
	.button-wrapper {
        /* position: relative; */
        min-width: v-bind(button_width);
        max-width: v-bind(button_width);
        width: v-bind(button_width);
        // Высота задана явно, а не пропорцией сторон: её Apple держит только с 15.
        height: v-bind(button_width);

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
</style>