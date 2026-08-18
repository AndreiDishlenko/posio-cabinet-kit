<template lang="">
	

    <div ref="form" class="card" :class="{ disabled: disabled }">

        <div class="card-body !mt-0">

            <!-- Personal Info -->
            <div class="w-full grid grid-cols-1 lg:grid-cols-2 gap-x-3 gap-y-1">

                <!-- Name -->
                <div class="label-group">
                    <label class="form-label">{{ $t('Your Name')}}</label>
                    <input ref="name" type="text" v-model="form_data.name" class="form-control md:form-control-lg" maxlength="70"/>
                    <p v-if="form_data_errors.name" class="form-error" >{{ form_data_errors.name }}</p>
                </div>

                <!-- Phone -->
                <div class="label-group disabled">
                    <label class="form-label">{{ $t('Phone')}}</label>
                    <input ref="phone" type="text" v-model="form_data.phone" class="form-control md:form-control-lg"/>
                    <p v-if="form_data_errors.phone" class="form-error" >{{ form_data_errors.phone }}</p>
                </div>

                <!-- Email -->
                <div class="label-group disabled">
                    <label class="form-label">{{ $t('E-mail')}}</label>
                    <input ref="email" type="text" v-model="form_data.email" class="form-control md:form-control-lg"/>
                    <p v-if="form_data_errors.email" class="form-error" >{{ form_data_errors.email }}</p>
                </div>

                <!-- Country -->
                <!-- <div class="label-group disabled">
                    <label class="form-label">{{ $t('Country')}}</label>
                    <input ref="country" type="text" v-model="form_data.country1" class="form-control md:form-control-lg"/>
                    <p v-if="form_data_errors.country" class="form-error" >{{ form_data_errors.country }}</p>
                </div> -->

            </div>

            <!-- Old password -->
            <div class="label-group">
                <label class="form-label">{{ $t('Old Password')}}</label>
                <input ref="old_password" type="password" v-model="form_data.old_password" class="w-full form-control" maxlength="20"/>
                <p v-if="form_data_errors.old_password" class="form-error" >{{ form_data_errors.old_password }}</p>
            </div>

            <!-- New password -->
            <div class="label-group">
                <label class="form-label">{{ $t('New Password')}}</label>
                <input ref="password" type="password" v-model="form_data.password" class="w-full form-control" placeholder="********" maxlength="20"/>
                <p v-if="form_data_errors.password" class="form-error" >{{ form_data_errors.password }}</p>
            </div>

            <!-- Password confirmation -->
            <div class="label-group !mt-2">
                <label class="form-label">{{ $t('Password Confirmation')}}</label>
                <input ref="password_confirmation" type="password" v-model="form_data.password_confirmation" class="w-full form-control" placeholder="********" maxlength="20"/>
                <p v-if="form_data_errors.password_confirmation" class="form-error" >{{ form_data_errors.password_confirmation }}</p>
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
			:label=" $t('Sound notifications') "
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
			>{{ $t('Save changes')}}
		</button>
	</div> 

</template>

<script>
    import formMixins       from '@/js/_formMixins'

    import { router }       from '@inertiajs/vue3';  

	import Checkbox 	from '@/js/Elements/Forms/Checkbox.vue';
	import Selectable	from '@/js/Elements/Forms/Selectable.vue';

	import { applyLocale, rememberLocale } from '@/js/localeSync.js';

    export default {
        mixins: [formMixins],
		components: { Checkbox, Selectable },
        props: {
            disabled: {
                type: Boolean,
                default: false,
            },
        },
        data() {
            return {
                validationRules: {
                    name: 'required|min:6',
                    email: 'required|email',
                      password: 'password',
                    password_confirmation: 'confirmed:password'
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
            this.$refs.name.focus();
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
                // console.log('Profile.save');
                // System (root) account is read-only — backend rejects any change too.
                if ( this.disabled )
                    return false;

                this.form_data_errors = await this.validateData( this.form_data );
                if ( Object.keys(this.form_data_errors).length )
                    return false;

                router.post( route('cabinet.settings.update'), this.form_data, {
                    onError: async (errors) => {
                        // console.warn('errors', errors); 
                        if ( errors.error )
                            this.$toast.error(errors.error)
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
                    // only: ['errors', 'in_data'],
                    except: ['in_data'],
                    preserveScroll: true,
                    preserveState: true,
                });
            }
        }
    }
</script>

<style lang="scss" scoped>
    
</style>