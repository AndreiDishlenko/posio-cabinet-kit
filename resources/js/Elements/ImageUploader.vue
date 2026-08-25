<template>

	<div class="label-group v-center space-y-3" :class="{ disabled: disabled }">

		<label v-if="label" class="form-label self-center">{{ $t(label) }}</label>

		<input
			type="file"
			ref="fileInput"
			:accept="accept"
			class="hidden"
			@change="onSelect"
			/>

		<img v-if="image" :src="image" :alt="$t(label)" class="preview" :style="preview_style"/>
		<div v-else class="preview placeholder d-center text-sm disabled" :style="preview_style">
			{{ $t('No image') }}
		</div>

		<div class="v-center space-y-1">
			<div class="flex flex-wrap justify-center wrap-gap-2">
				<button type="button" class="button button-sm primary-button" @click="$refs.fileInput.click()">
					{{ $t(button_label) }}
				</button>
				<button v-if="image && clearable && !is_default" type="button" class="button button-sm outline-button" @click="$emit('clear')">
					{{ $t('Remove') }}
				</button>
			</div>
			<span v-if="is_default" class="disabled text-sm text-center">{{ $t('Standard image') }}</span>
			<span v-if="hint" class="disabled text-sm text-center">{{ $t(hint) }}</span>
		</div>

	</div>

</template>

<script>
	export default {
		name: 'ImageUploader',
		props: {
			label: {
				type: String,
				default: '',
			},
			// Готовый URL текущей картинки; пусто — показывается заглушка.
			image: {
				type: String,
				default: '',
			},
			hint: {
				type: String,
				default: '',
			},
			button_label: {
				type: String,
				default: 'Upload image',
			},
			accept: {
				type: String,
				default: 'image/*',
			},
			max_size_mb: {
				type: Number,
				default: 2,
			},
			min_size: {
				type: Number,
				default: 0,
			},
			max_size: {
				type: Number,
				default: 0,
			},
			preview_width: {
				type: Number,
				default: 200,
			},
			clearable: {
				type: Boolean,
				default: false,
			},
			// Показывается штатный файл, а не загруженный: удалять нечего.
			is_default: {
				type: Boolean,
				default: false,
			},
			disabled: {
				type: Boolean,
				default: false,
			},
		},
		emits: ['select', 'clear'],
		computed: {
			preview_style() {
				return { maxWidth: `${this.preview_width}px` };
			},
			// Векторные файлы и значок вкладки не раскладываются в растр предсказуемо,
			// поэтому проверку сторон к ним не применяем.
			checks_dimensions() {
				return this.min_size > 0 || this.max_size > 0;
			},
		},
		methods: {
			async onSelect(event) {
				const file = event.target.files[0];
				this.$refs.fileInput.value = '';

				if ( !file )
					return;

				if ( file.size > this.max_size_mb * 1024 * 1024 )
					return this.$toast.error( this.$t('File is too large') );

				if ( this.checks_dimensions && !await this.dimensionsAreValid(file) )
					return;

				this.$emit('select', file);
			},

			async dimensionsAreValid(file) {
				let size = null;

				try {
					size = await this.imageDimensions(file);
				} catch (e) {
					// Формат, который браузер не раскладывает в растр (вектор, значок вкладки),
					// пропускаем: формат уже отфильтрован списком допустимых расширений.
					return true;
				}

				if ( this.min_size && (size.width < this.min_size || size.height < this.min_size) ) {
					this.$toast.error( `${this.$t('Image is too small')} (${this.min_size}x${this.min_size})` );
					return false;
				}

				if ( this.max_size && (size.width > this.max_size || size.height > this.max_size) ) {
					this.$toast.error( `${this.$t('Image is too large')} (${this.max_size}x${this.max_size})` );
					return false;
				}

				return true;
			},

			imageDimensions(file) {
				return new Promise((resolve, reject) => {
					const img = new Image();
					const url = URL.createObjectURL(file);

					img.onload = () => {
						resolve({ width: img.width, height: img.height });
						URL.revokeObjectURL(url);
					};
					img.onerror = () => {
						URL.revokeObjectURL(url);
						reject(new Error('unreadable image'));
					};

					img.src = url;
				});
			},
		},
	}
</script>

<style lang="scss" scoped>

	.preview {
		width: 100%;
		height: auto;
		object-fit: contain;
	}

	.placeholder {
		min-height: 80px;
		border: 1px dashed var(--form-control-border-color);
		border-radius: 8px;
	}

</style>
