<template>
	
    <div class="card min-h-0 w-[95vw] max-w-full lg:w-[36rem] lg:min-w-[36rem] "
		>
        
		<!-- Card Header -->
        <div class="card-header !items-start !flex-row sm:!items-center min-h-0">

			<!-- Main label -->
			<div class="w-full flex flex-col justify-start">				
				<div class="grow flex w-full space-x-3">

					<!-- Заголовок можна зібрати самій картці (напр. підставити назву запису
					     й підсвітити ще не збережений) — інакше це просто перекладений title. -->
					<slot name="title">
						<h1 v-if="tab_name" class="text-[20px] font-bold lt-sm:hidden">{{ $t(title) }}</h1>
						<h1 v-else class="text-[20px] font-bold">{{ $t(title) }}</h1>
					</slot>

					<div v-if="tab_name" class="flex items-center lt-sm:hidden">
						<Icon icon="ri:arrow-right-s-line" class="icon" />
					</div>
					<div v-if="tab_name" class="h1 text-[24px] flex items-center lt-sm:!ml-0">					
						{{ $t(tab_name) }}
					</div>
				</div>

				<span class="text-md text-secondary">{{ is_changed ? $t('Changed') : $t('Loaded') }}..</span>
			</div>            

			<!-- Additional actions -->
			<div class="">
				<slot name="headerActions"/>
			</div>

        </div>

		<div class="card-body overflow-x-hidden grow min-h-0"  :class="[scrolled ? 'scrolled-wrapper' : '!overflow-y-hidden']">
			<!-- Card Body -->
			<slot />

		</div>

		<!-- Card Footer -->
        <div class="card-footer min-h-0">

			<div v-if="back_button" class="">
				<button class="button primary-button" @click="$emit('back')">
					<Icon icon="ic:twotone-arrow-back-ios-new" class="icon cursor-pointer"/>
				</button>
			</div>

			<div v-if="back_button" class="grow"></div>

			<slot name="footerActions" />

            <button class="button primary-button"
                :class="[
                    $inprogress.value && 'spinner',
                    !this.is_changed && 'disabled'
                ]" 
                @click.stop.prevent="$emit('save')" 
                >{{ $t('Save')}}
            </button>
            <button class="button" 
                @click.stop.prevent="$emit('cancel')" 
                >{{ this.is_changed ? $t('Cancel') : $t('Close') }}
            </button>
        </div>

    </div>

</template>

<script>
	import { Icon } 	from '@iconify/vue';
    import Checkbox     from '@/js/Elements/Forms/Checkbox.vue'
    import cancelShortcutsMixin from '@/js/_cancelShortcutsMixin.js'

    export default {
        components: { Checkbox, Icon },
        mixins: [cancelShortcutsMixin],
        props: {
            title: {
                type: String,
                default: ''
            },
            form_data: {
                type: Object,
                default: {}
            },
            is_changed: {
                type: Boolean,
                default: false
            },
			back_button: {
				type: Boolean,
				default: false
			},
			tab_name: {
				type: String,
				default: ''
			},
			scrolled: {
				type: Boolean,
				default: true
			}
		},
        data() {
            return {
                // changed: false
            }
        },
        computed: {
            // changed: {
            //     get() {
            //         return this.is_changed
            //     },
            //     set(new_val) {
            //         this.$emit('update:modelValue', new_val)
            //     }
            // }
        }
    }
</script>

<style lang="scss" scoped>
</style>