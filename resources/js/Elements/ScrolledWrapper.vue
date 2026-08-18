<template>
	<div class="scrolled-wrapper" :style="content_height ? 'height: '+content_height+'px' : ''">
        <div class="scrolled-content" :class="contentclass">
			<slot></slot>
		</div>
    </div>
</template>

<script>
	export default {
		props: {
			'content_height': {
				type: Number,
				required: false,
				default: 0
			},
			'horizontal_scroll': {
				type: String,
				required: false,
				default: 'hidden'
			},
			'scrollbars': {
				type: String,
				required: false,
				default: 'none'
			},
            'contentclass': {
                type: String,
                required: false,
                default: ''
            }
		}
		
	}
</script>

<style lang="scss" scoped>
	.scrolled-wrapper {
		position: relative;
		height: 100%;
	}
	.scrolled-content {
		position: absolute;
		width: 100%;
		height: 100%;
		overflow-y: auto;
		overflow-x: v-bind(horizontal_scroll);
		border-top-width:0px;
        // border-radius: 0.25rem;
		/* scrollbar-color: green; */
		/* border-radius: var(--bs-card-border-radius); */
        border-radius: var(--table-border-radius);
		-ms-overflow-style: none;  /* IE and Edge */
		scrollbar-width: v-bind(scrollbars);  /* Firefox */
        // border: 1px solid green;
	}
	.scrolled-content::-webkit-scrollbar {
		display: none;
	}

</style>