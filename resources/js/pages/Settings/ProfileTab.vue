<template>

	<!-- Шапка профиля: подложка, аватар с выносом за её край и дата регистрации. -->
	<div class="v-flex relative">
		<div class="card min-h-[85px]"></div>

		<div class="user-avatar flex items-start z-[2]">
			<Avatar
				:src="form_data.avatar"
				:user_name="form_data.name"
				class="avatar-image"
				size="xxl"
				/>
		</div>

		<div v-if="!disabled" class="photo-button icon-label relative">
			<input
				type="file"
				ref="avatarInput"
				@change="handleFileChange"
				accept="image/*"
				class="hidden"
				/>
			<Icon icon="cuida:edit-outline" @click="$refs.avatarInput.click()"/>
		</div>

		<span class="profile-since text-sm text-secondary">{{ $t('User since') }} {{ form_data.registered }}</span>
	</div>

	<div ref="form" class="card" :class="{ disabled: disabled }">

		<div class="card-body !mt-0">

			<!-- Отступ сеткой, а не соседям: полей нечётное число, и в новом ряду
			     боковой отступ соседям сдвинул бы первое поле относительно колонки. -->
			<div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-x-3 gap-y-1">

				<!-- Name -->
				<div class="label-group">
					<label class="form-label">{{ $t('Your Name') }}</label>
					<input ref="name" type="text" v-model="form_data.name" class="form-control md:form-control-lg" maxlength="70"/>
					<p v-if="form_data_errors.name" class="form-error">{{ form_data_errors.name }}</p>
				</div>

				<!-- Phone -->
				<div class="label-group">
					<label class="form-label">{{ $t('Phone') }}</label>
					<input ref="phone" type="text" v-model="form_data.phone" class="form-control md:form-control-lg" maxlength="40"/>
					<p v-if="form_data_errors.phone" class="form-error">{{ form_data_errors.phone }}</p>
				</div>

				<!-- Email -->
				<div class="label-group">
					<label class="form-label">{{ $t('E-mail') }}</label>
					<input ref="email" type="text" v-model="form_data.email" class="form-control md:form-control-lg" maxlength="70"/>
					<p v-if="form_data_errors.email" class="form-error">{{ form_data_errors.email }}</p>
				</div>

			</div>

			<!-- Old password -->
			<div class="label-group">
				<label class="form-label">{{ $t('Old Password') }}</label>
				<input ref="old_password" type="password" v-model="form_data.old_password" class="w-full form-control" maxlength="20"/>
				<p v-if="form_data_errors.old_password" class="form-error">{{ form_data_errors.old_password }}</p>
			</div>

			<!-- New password -->
			<div class="label-group">
				<label class="form-label">{{ $t('New Password') }}</label>
				<input ref="password" type="password" v-model="form_data.password" class="w-full form-control" placeholder="********" maxlength="20"/>
				<p v-if="form_data_errors.password" class="form-error">{{ form_data_errors.password }}</p>
			</div>

			<!-- Password confirmation -->
			<div class="label-group !mt-2">
				<label class="form-label">{{ $t('Password Confirmation') }}</label>
				<input ref="password_confirmation" type="password" v-model="form_data.password_confirmation" class="w-full form-control" placeholder="********" maxlength="20"/>
				<p v-if="form_data_errors.password_confirmation" class="form-error">{{ form_data_errors.password_confirmation }}</p>
			</div>

		</div>

	</div>

	<div class="card !space-y-5">

		<div class="grid grid-cols-1 lg:grid-cols-2 space-y-4 lg:space-y-0 lg:space-x-3">

			<div class="label-group">
				<label class="form-label">{{ $t('Interface language') }}</label>
				<Selectable
					v-model="selected_locale"
					:in_data="interface_locales"
					input_class="form-control md:form-control-lg"
					/>
			</div>

			<!-- Смена темы пока без сохранения — показываем только текущую. -->
			<div class="label-group disabled">
				<label class="form-label">{{ $t('Color theme') }}</label>
				<Selectable
					v-model="selected_theme"
					:in_data="color_themes"
					input_class="form-control md:form-control-lg"
					/>
			</div>

		</div>

		<Checkbox
			v-model="form_data.play_notifications"
			:label="$t('Sound notifications')"
			/>

	</div>

	<div class="card-footer d-center">
		<button
			class="button primary-button"
			:class="[
				$inprogress.value ? 'spinner' : '',
				(is_changed && !disabled) ? '' : 'disabled'
			]"
			@click.stop.prevent="save"
			>{{ $t('Save changes') }}
		</button>
	</div>

</template>

<script>
	import formMixins from '@/js/_formMixins';

	import { router } from '@inertiajs/vue3';
	import { Icon } from '@iconify/vue';

	import Avatar from '@/js/Elements/Avatar.vue';
	import Checkbox from '@/js/Elements/Forms/Checkbox.vue';
	import Selectable from '@/js/Elements/Forms/Selectable.vue';

	import { applyLocale, rememberLocale } from '@/js/localeSync.js';

	export default {
		name: 'ProfileTab',
		mixins: [formMixins],
		components: { Icon, Avatar, Checkbox, Selectable },
		props: {
			disabled: {
				type: Boolean,
				default: false,
			},
		},
		data() {
			return {
				validationRules: {
					name: 'required|min:2',
					email: 'required|email',
					phone: 'phone',
					password: 'password',
					password_confirmation: 'confirmed:password',
				},
				selected_theme: 'dark',
				color_themes: [
					{ id: 'light', name: 'Light' },
					{ id: 'dark', name: 'Dark' },
				],
			}
		},
		computed: {
			// Список локалей берём из i18n, чтобы совпадал с общим переключателем языка.
			interface_locales() {
				return Object.values(this.$i18n.locales).map(l => ({ id: l.code, name: l.name }));
			},
			selected_locale: {
				get() {
					return this.$i18n.locale;
				},
				set(code) {
					this.changeLocale(code);
				},
			},
		},
		mounted() {
			this.$refs.name?.focus();
			this.detectTheme();
		},
		methods: {
			// Тёмная тема — состояние по умолчанию (сохраняется только выбор светлой).
			detectTheme() {
				this.selected_theme = localStorage.getItem('theme') === 'light' ? 'light' : 'dark';
			},
			// Смена локали как в общем переключателе: браузерная часть + закрепление на сервере.
			changeLocale(code) {
				if ( !code || code === this.$i18n.locale )
					return;

				if ( !applyLocale(code) )
					return;

				rememberLocale(code);
			},
			async save() {
				// Учётная запись обслуживания правке не подлежит — сервер отвечает тем же.
				if ( this.disabled )
					return false;

				this.form_data_errors = await this.validateData( this.form_data );
				if ( Object.keys(this.form_data_errors).length )
					return false;

				router.post( route('cabinet-kit.profile.update.post'), this.form_data, {
					onError: (errors) => {
						if ( errors.error )
							this.$toast.error(errors.error);
						this.outputErrors(errors);
					},
					onSuccess: () => {
						delete this.form_data.old_password;
						delete this.form_data.password;
						delete this.form_data.password_confirmation;
						this.updateInData();
						this.$toast.success( this.$t('Profile was updated') );
						this.$nextTick(() => {
							this.is_changed = false;
						});
					},
					except: ['in_data'],
					preserveScroll: true,
					preserveState: true,
				});
			},
			getImageDimensions(file) {
				return new Promise((resolve, reject) => {
					const img = new Image();
					img.src = URL.createObjectURL(file);
					img.onload = () => {
						resolve({ width: img.width, height: img.height });
						URL.revokeObjectURL(img.src);
					};
					img.onerror = () => {
						reject(new Error('Could not read the image'));
					};
				});
			},
			async handleFileChange(event) {
				const file = event.target.files[0];
				this.$refs.avatarInput.value = '';
				if (!file) return;

				if (!file.type.startsWith('image/'))
					return this.$toast.error( this.$t('Please, select image') );

				const maxSizeInBytes = 2 * 1024 * 1024;
				if (file.size > maxSizeInBytes)
					return this.$toast.error( this.$t('File size should not exceed 2 MB') );

				const { width, height } = await this.getImageDimensions(file);
				if (width < 150 || height < 150)
					return this.$toast.error( this.$t('The image must be at least 150x150 pixels') );

				if (width > 1000 || height > 1000)
					return this.$toast.error( this.$t('The image should be no larger than 1000x1000 pixels') );

				const formData = new FormData();
				formData.append('photo', file);

				router.post( route('cabinet-kit.profile.avatar'), formData, {
					onError: () => {
						this.$toast.error( this.$t('Error loading photo') );
					},
					preserveScroll: true,
					preserveState: true,
				});
			},
		},
	}
</script>

<style lang="scss" scoped>

	.user-avatar {
		position: absolute;
		top: 36px;
		left: 30px;

		font-size: var(--text-xl);
	}

	.avatar-image {
		border: 4px solid var(--background-color);
	}

	.photo-button {
		position: absolute;
		top: 15px;
		right: 5px;
		color: var(--text-color-secondary);
		cursor: pointer;
	}

	// Десктоп: дата держится в правом нижнем углу подложки.
	.profile-since {
		position: absolute;
		bottom: 0.5rem;
		right: 1rem;
	}

	// Мобильный: дата уходит в поток под свисающий аватар, иначе они перекрываются,
	// а подложка не вмещает аватар целиком и он налезает на форму ниже.
	@media (max-width: 639px) {
		.profile-since {
			position: static;
			display: block;
			margin-top: 3.25rem;
			padding-right: 0.25rem;
			text-align: right;
		}
	}

</style>
