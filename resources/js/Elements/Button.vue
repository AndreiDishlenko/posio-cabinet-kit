<template lang="">
	
	<button>
		<!-- <Icon :icon="mobile_icon" class="icon sm:hidden"/> -->
		<!-- <div class="lt-sm:hidden"> -->
			<slot></slot>
		<!-- </div>		 -->
	</button>

</template>

<script>
	import { Icon } from '@iconify/vue/dist/iconify.js';

	export default {
		
		components: { Icon },
		props: {
			mobile_icon: {
				type: String,
				default: ''
			}
		}
		
	}
</script>

<style lang="scss" scoped>
	
</style>