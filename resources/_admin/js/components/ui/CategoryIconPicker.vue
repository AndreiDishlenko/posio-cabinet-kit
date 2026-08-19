<template>

	<div class="icon-picker">

		<div class="icon-picker__grid scrollbar-thin">

			<!-- Пустой выбор: плитка на кассе получит нейтральную иконку по умолчанию -->
			<button
				type="button"
				class="icon-picker__item"
				:class="{ 'is-selected': !modelValue }"
				:title="$t('No icon')"
				@click="select('')"
				>
				<Icon icon="mdi:cancel" class="icon" />
			</button>

			<button
				v-for="name in icons"
				:key="name"
				type="button"
				class="icon-picker__item"
				:class="{ 'is-selected': name === modelValue }"
				@click="select(name)"
				>
				<Icon :icon="name" class="icon" />
			</button>

		</div>

	</div>

</template>

<script>
	import { Icon } from '@iconify/vue'

	export default {
		name: 'CategoryIconPicker',
		components: { Icon },
		props: {
			modelValue: {
				type: String,
				default: '',
			},
		},
		emits: ['update:modelValue'],
		data() {
			return {
				// Подобранный набор под типовые товарные группы торговой точки:
				// сначала еда и напитки, затем непродовольственные группы.
				icons: [
					'mdi:silverware-fork-knife', 'mdi:food', 'mdi:food-variant', 'mdi:food-fork-drink',
					'mdi:coffee', 'mdi:tea', 'mdi:cup', 'mdi:glass-mug-variant',
					'mdi:beer', 'mdi:keg', 'mdi:glass-wine', 'mdi:glass-cocktail',
					'mdi:bottle-soda-classic', 'mdi:bottle-wine', 'mdi:bottle-tonic', 'mdi:water',
					'mdi:hamburger', 'mdi:pizza', 'mdi:food-hot-dog', 'mdi:french-fries',
					'mdi:taco', 'mdi:noodles', 'mdi:pasta',
					'mdi:rice', 'mdi:bowl-mix', 'mdi:food-steak', 'mdi:food-drumstick',
					'mdi:food-turkey', 'mdi:fish', 'mdi:egg', 'mdi:cheese',
					'mdi:baguette', 'mdi:pretzel', 'mdi:bread-slice', 'mdi:food-croissant',
					'mdi:muffin', 'mdi:cupcake', 'mdi:cake-variant', 'mdi:cookie',
					'mdi:ice-cream', 'mdi:candy', 'mdi:popcorn', 'mdi:food-apple',
					'mdi:fruit-grapes', 'mdi:fruit-cherries', 'mdi:fruit-watermelon', 'mdi:carrot',
					'mdi:corn', 'mdi:mushroom', 'mdi:chili-mild', 'mdi:grill', 'mdi:barley',
					'mdi:chef-hat', 'mdi:stove', 'mdi:microwave', 'mdi:fridge',
					'mdi:blender', 'mdi:kettle', 'mdi:silverware', 'mdi:silverware-spoon',

					'mdi:tshirt-crew', 'mdi:shoe-formal', 'mdi:hanger', 'mdi:bag-personal',
					'mdi:sunglasses', 'mdi:watch', 'mdi:ring', 'mdi:necklace',
					'mdi:diamond-stone', 'mdi:crown', 'mdi:cellphone', 'mdi:laptop',
					'mdi:headphones', 'mdi:television', 'mdi:camera', 'mdi:printer',
					'mdi:microphone', 'mdi:piano', 'mdi:gamepad-variant', 'mdi:book-open-page-variant',
					'mdi:book', 'mdi:book-open-variant', 'mdi:notebook', 'mdi:pencil',
					'mdi:pen', 'mdi:marker', 'mdi:eraser', 'mdi:ruler',
					'mdi:calculator', 'mdi:briefcase', 'mdi:palette', 'mdi:newspaper',
					'mdi:ticket',

					'mdi:flower', 'mdi:flower-tulip', 'mdi:sprout', 'mdi:cactus',
					'mdi:tree', 'mdi:watering-can', 'mdi:paw', 'mdi:baby-carriage',
					'mdi:baby-bottle', 'mdi:teddy-bear', 'mdi:puzzle', 'mdi:toy-brick',
					'mdi:dice-multiple', 'mdi:kite', 'mdi:rabbit', 'mdi:dog', 'mdi:cat', 'mdi:bone',

					'mdi:spray-bottle', 'mdi:broom', 'mdi:hand-wash', 'mdi:washing-machine',
					'mdi:vacuum', 'mdi:toothbrush', 'mdi:shower', 'mdi:bed',
					'mdi:sofa', 'mdi:lamp', 'mdi:bathtub', 'mdi:pill',
					'mdi:medical-bag', 'mdi:lipstick', 'mdi:hair-dryer', 'mdi:razor-double-edge', 'mdi:spa',

					'mdi:tools', 'mdi:wrench', 'mdi:hammer', 'mdi:screwdriver',
					'mdi:nail', 'mdi:toolbox', 'mdi:ladder', 'mdi:lightbulb',

					'mdi:car', 'mdi:motorbike', 'mdi:gas-station', 'mdi:bike',
					'mdi:soccer', 'mdi:basketball', 'mdi:football', 'mdi:tennis',
					'mdi:run', 'mdi:weight-lifter', 'mdi:yoga', 'mdi:dumbbell',

					'mdi:gift', 'mdi:party-popper', 'mdi:pine-tree', 'mdi:snowflake',
					'mdi:cigar', 'mdi:smoking', 'mdi:umbrella', 'mdi:home',
					'mdi:tag', 'mdi:sale', 'mdi:package-variant', 'mdi:cart', 'mdi:truck', 'mdi:store',
				],
			}
		},
		methods: {
			select(name) {
				this.$emit('update:modelValue', name);
			},
		},
	}
</script>

<style lang="scss" scoped>

	.icon-picker__grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
		gap: 6px;

		max-height: 220px;
		overflow-y: auto;

		padding: 8px;
		border: 1px solid var(--form-control-border-color);
		border-radius: 8px;
	}

	.icon-picker__item {
		display: flex;
		align-items: center;
		justify-content: center;

		height: 40px;
		border-radius: 6px;
		color: var(--text-color-secondary);
		cursor: pointer;

		&:hover {
			background-color: var(--selectable-hover-items);
			color: var(--text-color);
		}
	}

	.icon-picker__item.is-selected {
		background-color: var(--primary-button-background);
		color: #ffffff;
	}

</style>
