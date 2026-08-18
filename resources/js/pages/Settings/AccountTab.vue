<template>

	<template v-if="has_account">

		<div ref="form" class="card !space-y-7">

			<div class="card-body" :class="{ disabled: !can_edit }">
				<h2 class="text-yellow">{{ $t('Company settings') }}</h2>

				<div class="grid lt-sm:grid-cols-1 sm:grid-cols-2 lt-sm:gap-2 sm:gap-5">

					<!-- Logo -->
					<div class="label-group row-span-2 v-center space-y-5">
						<input
							type="file"
							ref="logoInput"
							@change="addLogo"
							accept="image/*"
							class="hidden"
							/>
						<img v-if="form_data.logo" :src="`/storage/${form_data.logo}`" width="200px" class="disabled" alt=""/>
						<Icon v-else icon="mdi:office-building-outline" class="icon icon-4xl disabled"/>
						<div class="v-center">
							<button class="button button-sm primary-button" @click="$refs.logoInput.click()">{{ $t('Upload Logo') }}</button>
							<span class="disabled text-sm">( {{ $t('Recommended size 200x100 px') }} )</span>
						</div>
					</div>

					<!-- Name -->
					<div class="label-group">
						<label class="form-label">{{ $t('Company Name') }}</label>
						<input ref="name" type="text" v-model="form_data.name" class="form-control" maxlength="255"/>
						<p v-if="form_data_errors.name" class="form-error">{{ form_data_errors.name }}</p>
					</div>

					<!-- Description -->
					<div class="label-group h-full">
						<label class="form-label">{{ $t('Company Description') }}</label>
						<input ref="description" type="text" v-model="form_data.description" class="form-control" maxlength="255"/>
						<p v-if="form_data_errors.description" class="form-error">{{ form_data_errors.description }}</p>
					</div>

					<!-- Address -->
					<div class="label-group">
						<label class="form-label">{{ $t('Address') }}</label>
						<input ref="address" type="text" v-model="form_data.address" class="form-control" maxlength="255"/>
						<p v-if="form_data_errors.address" class="form-error">{{ form_data_errors.address }}</p>
					</div>

					<!-- Phone -->
					<div class="label-group">
						<label class="form-label">{{ $t('Phone') }}</label>
						<input ref="phone" type="text" v-model="form_data.phone" class="form-control" maxlength="40"/>
						<p v-if="form_data_errors.phone" class="form-error">{{ form_data_errors.phone }}</p>
					</div>

					<!-- Email -->
					<div class="label-group">
						<label class="form-label">{{ $t('E-mail') }}</label>
						<input ref="email" type="text" v-model="form_data.email" class="form-control" maxlength="255"/>
						<p v-if="form_data_errors.email" class="form-error">{{ form_data_errors.email }}</p>
					</div>

					<!-- URL -->
					<div class="label-group">
						<label class="form-label">{{ $t('URL') }}</label>
						<input ref="url" type="text" v-model="form_data.url" class="form-control" maxlength="255"/>
						<p v-if="form_data_errors.url" class="form-error">{{ form_data_errors.url }}</p>
					</div>

					<!-- Measurement Units -->
					<div class="label-group grow">
						<label class="form-label">{{ $t('Measurement Units') }}</label>
						<Selectable ref="unitsystem_id" v-model="form_data.unitsystem_id" :in_data="unit_systems" class="disabled"/>
					</div>

					<!-- Default currency -->
					<div class="label-group grow">
						<label class="form-label">{{ $t('Default currency') }}</label>
						<Selectable ref="currency_id" v-model="form_data.currency_id" :in_data="$dictionaries.currencies" class="disabled"/>
					</div>

				</div>
			</div>

			<div v-if="can_edit" class="card-footer d-center">
				<button
					class="button primary-button"
					:class="[
						$inprogress.value ? 'spinner' : '',
						is_changed ? '' : 'disabled'
					]"
					@click.stop.prevent="save"
					>{{ $t('Save changes') }}
				</button>
			</div>

		</div>

		<!-- Удаление аккаунта — только владельцу. -->
		<div v-if="is_owner" class="card disabled">
			<h2 class="text-yellow">{{ $t('Delete Account') }}</h2>
			<span class="text-sm">* {{ $t('After making deletion request, you will have 6 months to restore this account.') }}</span>
			<span class="text-sm">{{ $t('To permanently erase your account, click the button below. This implies that you will lose access to its data.') }}</span>

			<div class="card-footer d-center text-sm">
				<button class="button">{{ $t('Process') }}</button>
			</div>
		</div>

	</template>

	<div v-else class="d-center py-10 rounded-lg border border-dashed border-stone-700">
		<button class="button primary-button disabled">{{ $t('Create own company') }}</button>
	</div>

</template>

<script>
	import formMixins from '@/js/_formMixins';

	import { router } from '@inertiajs/vue3';
	import { Icon } from '@iconify/vue';

	import Selectable from '@/js/Elements/Forms/Selectable.vue';

	export default {
		name: 'AccountTab',
		mixins: [formMixins],
		components: { Icon, Selectable },
		props: {
			// Правка реквизитов доступна владельцу и управляющему составом;
			// то же право проверяется на сервере.
			can_edit: {
				type: Boolean,
				default: false,
			},
			is_owner: {
				type: Boolean,
				default: false,
			},
		},
		data() {
			return {
				validationRules: {
					name: 'required|min:2',
					email: 'email',
					phone: 'phone',
				},
				unit_systems: [
					{ id: 1, name: this.$t('International (pcs, m, kg, l)') },
					{ id: 2, name: this.$t('United States (pcs, pound, gallon)') },
				],
			}
		},
		computed: {
			has_account() {
				return !!this.in_data?.id;
			},
		},
		methods: {
			async save() {
				if ( !this.can_edit )
					return false;

				this.form_data_errors = await this.validateData( this.form_data );
				if ( Object.keys(this.form_data_errors).length )
					return false;

				router.post( route('cabinet-kit.account.update'), this.form_data, {
					onError: (errors) => {
						if ( errors.error )
							this.$toast.error(errors.error);
						this.outputErrors(errors);
					},
					onSuccess: () => {
						this.updateInData();
						this.$toast.success( this.$t('Account was updated') );
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
			async addLogo(event) {
				const file = event.target.files[0];
				this.$refs.logoInput.value = '';
				if (!file)
					return;

				if (!file.type.startsWith('image/'))
					return this.$toast.error( this.$t('Please, select image') );

				const maxSizeInBytes = 8 * 1024 * 1024;
				if (file.size > maxSizeInBytes)
					return this.$toast.error( this.$t('File size should not exceed 8 MB') );

				const { width, height } = await this.getImageDimensions(file);
				if (width < 100 || height < 100)
					return this.$toast.error( this.$t('The image must be at least 100x100 pixels') );

				if (width > 1000 || height > 1000)
					return this.$toast.error( this.$t('The image should be no larger than 1000x1000 pixels') );

				const formData = new FormData();
				formData.append('photo', file);

				router.post( route('cabinet-kit.account.addlogo'), formData, {
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

</style>
