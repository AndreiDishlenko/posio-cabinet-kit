<template>

	<div class="v-flex items-stretch space-y-6 pb-6">

		<div class="card !space-y-7">
			<div class="card-body">
				<h2 class="text-yellow">{{ $t('Site identity') }}</h2>

				<div class="label-group max-w-md">
					<label class="form-label">{{ $t('Title') }}</label>
					<input type="text" v-model="site_name" class="form-control" :placeholder="name_default"/>
					<!-- <p class="disabled text-sm">{{ $t('sitesettings-name-note') }}</p> -->
				</div>

				<div class="v-center">
					<button class="button primary-button" :disabled="!is_changed" @click="saveName">
						{{ $t('Save') }}
					</button>
				</div>
			</div>
		</div>

		<div class="card">
			<div class="card-body">
				<p class="disabled text-sm">{{ $t(group.note) }}</p>

				<div class="label-group max-w-xs">
					<label class="form-label">{{ $t('Default theme') }}</label>
					<!-- Выбранное значение приходит с сервера: список отражает сохранённое,
					     а не предполагаемое состояние, поэтому не v-model. -->
					<Selectable
						:in_data		= "theme_options"
						:model-value	= "themes[group.theme_key]"
						@onChange		= "value => saveTheme(group.theme_key, value)"
						/>
					<!-- <p class="disabled text-sm">{{ $t(group.theme_note) }}</p> -->
				</div>

				<div class="grid lt-sm:grid-cols-1 sm:grid-cols-3 lt-sm:gap-4 sm:gap-5">
					<ImageUploader
						v-for="item in group.images"
						:key="item.key"
						:label			= "item.label"
						:hint			= "item.hint"
						:accept			= "item.accept"
						:image			= "item.value"
						:is_default		= "item.is_default"
						:min_size		= "item.min_size"
						:max_size		= "item.max_size"
						:preview_width	= "item.preview_width"
						:clearable		= "true"
						@select			= "file => uploadImage(item.key, file)"
						@clear			= "() => deleteImage(item.key)"
						/>
				</div>
			</div>
		</div>

	</div>

</template>

<script>
	import sharedMixins from '@/js/_sharedMixins';

	import ImageUploader from '@/js/Elements/ImageUploader.vue';
	import Selectable    from '@/js/Elements/Forms/Selectable.vue';

	const FAVICON_ACCEPT = '.ico,.png,.svg';
	const LOGO_ACCEPT    = '.png,.svg,.jpg,.jpeg,.webp';

	const THEME_OPTIONS = [
		{ id: 'dark',  name: 'Dark' },
		{ id: 'light', name: 'Light' },
	];

	// Публичная часть и кабинет оформлены по-разному, поэтому их наборы разведены
	// по отдельным разделам; внутри набора начертания идут парами под тёмную и светлую тему.
	const GROUPS = {
		main: {
			note:       'sitesettings-main-note',
			theme_key:  'main_theme',
			theme_note: 'sitesettings-main-theme-note',
			images: [
				{ key: 'main_favicon',    label: 'Favicon',              hint: 'Recommended 32x32 px',        favicon: true },
				{ key: 'main_logo_dark',  label: 'Logo for dark theme',  hint: 'Recommended size 200x100 px' },
				{ key: 'main_logo_light', label: 'Logo for light theme', hint: 'Recommended size 200x100 px' },
			],
		},
		cabinet: {
			note:       'sitesettings-cabinet-note',
			theme_key:  'cabinet_theme',
			theme_note: 'sitesettings-cabinet-theme-note',
			images: [
				{ key: 'cabinet_favicon',      label: 'Favicon',                hint: 'Recommended 32x32 px',        favicon: true },
				{ key: 'cabinet_logo_dark',    label: 'Logo for dark theme',    hint: 'Recommended size 200x100 px' },
				{ key: 'cabinet_logo_light',   label: 'Logo for light theme',   hint: 'Recommended size 200x100 px' },
				{ key: 'cabinet_symbol_dark',  label: 'Symbol for dark theme',  hint: 'sitesettings-symbol-hint', symbol: true },
				{ key: 'cabinet_symbol_light', label: 'Symbol for light theme', hint: 'sitesettings-symbol-hint', symbol: true },
			],
		},
	};

	export default {
		name: 'BrandSettings',
		mixins: [sharedMixins],
		components: { ImageUploader, Selectable },
		props: {
			// Какая часть сервиса настраивается: main | cabinet
			scope: {
				type: String,
				required: true,
			},
		},
		data() {
			return {
				site_name: '',
				saved_name: '',
				name_default: '',
				images: {},
				themes: {},
			}
		},
		computed: {
			theme_options() {
				return THEME_OPTIONS;
			},
			group() {
				const group = GROUPS[this.scope];

				return {
					...group,
					images: this.describe(group.images),
				};
			},
			is_changed() {
				return this.site_name !== this.saved_name;
			},
		},
		mounted() {
			this.loadSettings();
		},
		methods: {
			// Дескриптор загрузчика: тип картинки задаёт допустимые форматы, проверку
			// сторон и ширину превью, остальное подставляется из ответа сервера.
			describe(items) {
				return items.map(item => {
					const state   = this.images[item.key] ?? {};
					const compact = item.favicon || item.symbol;

					return {
						...item,
						value:         state.url ?? '',
						is_default:    state.is_default ?? false,
						accept:        item.favicon ? FAVICON_ACCEPT : LOGO_ACCEPT,
						min_size:      0,
						max_size:      item.favicon ? 0 : 2000,
						preview_width: compact ? 64 : 200,
					};
				});
			},

			applyPayload(data) {
				this.site_name    = data.site_name || '';
				this.saved_name   = this.site_name;
				this.name_default = data.name_default || '';
				this.images       = data.images || {};
				this.themes       = data.themes || {};
			},

			async saveTheme(key, value) {
				const result = await this.$apiClient.post( route('admin.api.sitesettings.theme'), { key, value } );

				if ( result?.error )
					return this.$toast.error(`${this.$t('Data update error')}: ${result.message}`);

				this.applyPayload(result.data);
				this.$toast.success( this.$t('Site settings were updated') );
			},

			async loadSettings() {
				const result = await this.$apiClient.get( route('admin.api.sitesettings.index') );

				if ( result?.error )
					return this.$toast.error(`${this.$t('Data update error')}: ${result.message}`);

				this.applyPayload(result.data);
			},

			async saveName() {
				const result = await this.$apiClient.post( route('admin.api.sitesettings.update'), { site_name: this.site_name } );

				if ( result?.error )
					return this.$toast.error(`${this.$t('Data update error')}: ${result.message}`);

				this.applyPayload(result.data);
				this.$toast.success( this.$t('Site settings were updated') );
			},

			async uploadImage(key, file) {
				const form = new FormData();
				form.append('key', key);
				form.append('file', file);

				const result = await this.$apiClient.post(
					route('admin.api.sitesettings.image'),
					form,
					{ 'Content-Type': 'multipart/form-data' }
				);

				if ( result?.error )
					return this.$toast.error(`${this.$t('Error loading photo')}: ${result.message}`);

				this.applyPayload(result.data);
				this.$toast.success( this.$t('Site settings were updated') );
			},

			async deleteImage(key) {
				if ( !await this.$popup.confirm_yn('', { title: 'Restore standard image?', danger: true }) )
					return;

				const result = await this.$apiClient.post( route('admin.api.sitesettings.image.delete'), { key } );

				if ( result?.error )
					return this.$toast.error(`${this.$t('Data update error')}: ${result.message}`);

				this.applyPayload(result.data);
			},
		},
	}
</script>

<style lang="scss" scoped>
</style>
