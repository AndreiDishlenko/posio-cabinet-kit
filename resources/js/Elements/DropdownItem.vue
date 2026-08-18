<template lang="">

    <MenuItem as="div">
		
        <div v-if="!href" class="menu-item border-red">
            <slot />
        </div>

        <a v-else class="menu-item menu-href border-yellow" :href="href">
            <slot/>
        </a>
		
    </MenuItem>

</template>

<script>
    import { MenuItem } from '@headlessui/vue'
    
    export default {
        components: { MenuItem },
        props: {
            href: {
                type: String,
            }
        },
    }
</script>

<style lang="scss" scoped>

	.menu-item {
		@apply
			cursor-pointer
			text-nowrap
			rounded-md
			!px-2
			!py-2.5;
	}

	.menu-item:not(.disabled):hover {
		background-color: var(--dropdown-items-color)!important;
	}

    .menu-item {
        /* color: var(--text-color); */
        font-size: var(--text-sm);
        display: flex;
        align-items: start;
        background-color: var(--card-background);
        @apply 
            px-6 
            py-3;
    }
    .menu-href {
    }
    .menu-href:hover {
        background-color: var(--selection-color);
        @apply outline-none;
    }
</style>