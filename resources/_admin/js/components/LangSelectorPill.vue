<template>

	<div class="w-min flex justify-center button pill-button">
		<LangSelector mode="inline"/>
	</div>

</template>

<script>
	import LangSelector from '@/js/Custom/_LangSelector.vue';

	export default {
		name: 'LangSelectorPill',
		components: { LangSelector },
	}
</script>
