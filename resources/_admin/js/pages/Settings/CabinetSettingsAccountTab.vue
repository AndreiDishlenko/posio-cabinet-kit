<template lang="">

    <div ref="form" class="card !space-y-7">

        <div class="card-body" :class="{ disabled: !can_edit }">
            <h2 class="text-yellow">{{ $t('Company settings')}}</h2>

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
                    <img v-if="form_data.logo" :src="`/storage/${form_data.logo}`" width="200px" class="disabled"/>
                    <img v-else src="/cabinet-assets/logo.svg" width="150px" class="disabled"/>
                    <div class="v-center">
                        <button class="button button-sm primary-button" @click="$refs.logoInput.click()">{{ $t('Upload Logo') }}</button>
                        <span class="disabled text-sm">( {{ $t('Recommended size 200x100 px')}} )</span>
                    </div>
                </div>

                <!-- Name -->
                <div class="label-group">
                    <label class="form-label">{{ $t('Company Name')}}</label>
                    <input ref="name" type="text" v-model="form_data.name" class="form-control"/>
                    <p v-if="form_data_errors.name" class="form-error" >{{ form_data_errors.name }}</p>
                </div>

                <!-- Description -->
                <div class="label-group h-full">
                    <label class="form-label">{{ $t('Company Description')}}</label>
                    <input ref="description" type="text" v-model="form_data.description" class="form-control"/>
                    <p v-if="form_data_errors.description" class="form-error" >{{ form_data_errors.description }}</p>
                </div>

                <!-- Address -->
                <div class="label-group">
                    <label class="form-label">{{ $t('Address')}}</label>
                    <input ref="address" type="text" v-model="form_data.address" class="form-control"/>
                    <p v-if="form_data_errors.address" class="form-error" >{{ form_data_errors.address }}</p>
                </div>

                <!-- Phone -->
                <div class="label-group">
                    <label class="form-label">{{ $t('Phone')}}</label>
                    <input ref="phone" type="text" v-model="form_data.phone" class="form-control"/>
                    <p v-if="form_data_errors.phone" class="form-error" >{{ form_data_errors.phone }}</p>
                </div>

                <!-- Email -->
                <div class="label-group">
                    <label class="form-label">{{ $t('E-mail')}}</label>
                    <input ref="email" type="text" v-model="form_data.email" class="form-control"/>
                    <p v-if="form_data_errors.email" class="form-error" >{{ form_data_errors.email }}</p>
                </div>

                <!-- URL -->
                <div class="label-group">
                    <label class="form-label">{{ $t('URL')}}</label>
                    <input ref="url" type="text" v-model="form_data.url" class="form-control"/>
                    <p v-if="form_data_errors.url" class="form-error" >{{ form_data_errors.url }}</p>
                </div>

                <!-- Measurement Units -->
                <div class="label-group grow">
                    <label class="form-label">{{ $t('Measurement Units')}}</label>
                    <Selectable ref="unitsystem_id" v-model="form_data.unitsystem_id" :in_data="unitSystems" class="disabled"/>
                    <p class="form-error" v-if="form_data_errors.unitsystem_id">{{ form_data_errors.unitsystem_id }}</p>
                </div>

                <!-- Default currency -->
                <div class="label-group grow">
                    <label class="form-label">{{ $t('Default currency')}}</label>
                    <Selectable ref="currency_id" v-model="form_data.currency_id" :in_data="$dictionaries.currencies" class="disabled"/>
                    <p class="form-error" v-if="form_data_errors.currency_id">{{ form_data_errors.currency_id }}</p>
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
                >{{ $t('Save changes')}}
            </button>
        </div>   

    </div>
</template>

<script>
    import formMixins       from '@/js/_formMixins'

    import { router }       from '@inertiajs/vue3';  

    export default {
        mixins: [formMixins],

        props: {
            // Owner / manager may edit company settings; other roles see them read-only
            can_edit: {
                type: Boolean,
                default: true,
            },
        },

        data() {
            return {
                validationRules: {
                    name:       'required|min:6',
                    email:      'email',
                },
                unitSystems: [
                    { id:1 , name: this.$t('International (pcs, m, kg, l)')},
                    { id:2 , name: this.$t('United States (pcs, pound, gallon)')}
                ],
                user_roles: [
                    { id:1, name:'User'},
                    { id:1, name:'Administrator'},
                ]
            }
        },
        mounted() {
            // this.$refs.name.focus();
        },
        methods: {
            async removeMember(user_index) {
                // console.log('removeMember', user_index);
                
                let result = await this.$popup.confirm_yn( this.$t('Are you sure you want to remove account member?'), { danger: true } );
                if ( !result )
                    return false;

                result = await this.$apiClient.post( route('cabinet.account.member.remove'), { email: this.users[user_index].email });
                if ( result.error )
                    return this.$toast.error(result.error)

                this.users.splice(user_index, 1);

                return this.$toast.success('Member was removed successfully')
            },

            async save() {
                // console.log('Profile.save');
                this.form_data_errors = await this.validateData( this.form_data );
                if ( Object.keys(this.form_data_errors).length )
                    return false;

                router.post( route('cabinet.account.update'), this.form_data, {
                    onError: async (errors) => {
                        // console.warn('errors', errors); 
                        if ( errors.error )
                            this.$toast.error(errors.error)
                        this.outputErrors(errors);
                    },
                    onSuccess: () => { 
                        this.updateInData();
                        this.$toast.success( this.$t('Account was updated') );
                        this.$nextTick(() => {
                            this.is_changed = false;
                        });
                    },
                    // only: ['errors', 'in_data'],
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
                        URL.revokeObjectURL(img.src); // Освобождаем память
                    };
                    img.onerror = () => {
                        reject(new Error('Не удалось загрузить изображение'));
                    };
                });
            },
            async addLogo(event) {
                // console.log('handleFileChange', event.target.files[0])
                const file = event.target.files[0];
                this.$refs.logoInput.value = '';
                if (!file) 
                    return;

                if (!file.type.startsWith('image/')) 
                    return this.$toast.error('Please, select image');

                const maxSizeInBytes = 2 * 2048 * 2048;
                if (file.size > maxSizeInBytes) 
                    return this.$toast.error('File size should not exceed 2 MB');

                const { width, height } = await this.getImageDimensions(file);
                if (width < 100 || height < 100) 
                    return this.$toast.error('The image must be at least 100x100 pixels');

                if (width > 1000 || height > 1000)
                    return this.$toast.error('The image should be no larger than 1000x1000 pixels');

                const formData = new FormData();
                formData.append('photo', file);

                router.post( route('cabinet.account.addlogo'), formData, {
                    onError: (errors) => {
                        this.$toast.error('Error loading photo');
                    },
                    preserveScroll: true,
                    preserveState: true
                });

            }
        }
    }
</script>

<style lang="scss" scoped>
    
</style>