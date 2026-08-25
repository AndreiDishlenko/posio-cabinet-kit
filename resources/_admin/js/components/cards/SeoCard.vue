<template lang="">

    <CardTemplate class="!min-w-[64rem]"
        title		= "Seo Card"
        :form_data  = "form_data"
        :is_changed = "is_changed"
		@save       = "saveRecordAndClose(form_data)"
        @cancel     = "$emit('close')"
        >

		<template #headerActions>
			<div class="flex space-x-6">
				<!-- Google index -->
				<div class="label-group v-center">
					<Checkbox ref="index" v-model="form_data.index" :text="'Place in Google index'" class="text-md whitespace-nowrap"/>
					<p class="form-error " v-if="form_data_errors.index">{{ form_data_errors.index }}</p>
				</div>

				<Checkbox v-model="form_data.is_published" :text="$t('Active')" class="text-md"/>
			</div>
		</template>

		<div class="grid grid-cols-3 gap-3">

			<!-- ---------------------------- -->
			<!-- Route -->
			<div class="label-group col-span-1">
				<label class="form-label">{{ $t('Route')}}</label>
				<input ref="route_name" type="text" v-model="form_data.route_name" class="form-control"/>
				<p v-if="form_data_errors.route_name" class="form-error" >{{ form_data_errors.route_name }}</p>
			</div>

			<!-- Locale -->
			<div class="label-group">
				<label class="form-label">{{ $t('Locale')}}</label>
				<Selectable ref="locale" v-model="form_data.locale" :in_data="locales"/>
				<p class="form-error" v-if="form_data_errors.locale">{{ form_data_errors.locale }}</p>
			</div>

			<!-- Canonical url -->
			<div class="label-group">
				<label class="form-label">{{ $t('Canonical URL')}}</label>
				<input ref="canonical_url" type="text" v-model="form_data.canonical_url" class="form-control"/>
				<p v-if="form_data_errors.canonical_url" class="form-error" >{{ form_data_errors.canonical_url }}</p>
			</div>

			<!-- ---------------------------- -->
			<!-- Page name -->
			<div class="label-group col-span-2">
				<label class="form-label">{{ $t('Page Name')}} ({{ (form_data.page_name || '').trim().split(/\s+/).filter(Boolean).length }} / 3-6 words )</label>
				<input ref="page_name" type="text" v-model="form_data.page_name" class="form-control"/>
				<p v-if="form_data_errors.page_name" class="form-error" >{{ form_data_errors.page_name }}</p>
			</div>

			<!-- Change frequency -->
			<div class="label-group">
				<label class="form-label">{{ $t('Change Frequency')}}</label>
				<Selectable ref="changeFrequency" v-model="form_data.changeFrequency" :in_data="frequencies"/>
				<p class="form-error" v-if="form_data_errors.changeFrequency">{{ form_data_errors.changeFrequency }}</p>
			</div>

			<!-- ---------------------------- -->
			<!-- Page title -->
			<div class="label-group col-span-2">
				<label class="form-label">{{ $t('Page Title')}} ({{ (form_data.meta_title || '').length }} / 50-60)</label>
				<input ref="meta_title" type="text" v-model="form_data.meta_title" class="form-control"/>
				<p v-if="form_data_errors.meta_title" class="form-error" >{{ form_data_errors.meta_title }}</p>
			</div>

			<!-- Priority -->
			<div class="label-group">
				<label class="form-label">{{ $t('Priority')}}</label>
				<input ref="priority" type="text" v-model="form_data.priority" class="form-control"/>
				<p class="form-error" v-if="form_data_errors.priority">{{ form_data_errors.priority }}</p>
			</div>

			<!-- ---------------------------- -->
			<!-- Page keywords -->
			<!-- <div class="label-group col-span-2 disabled">
				<label class="form-label">{{ $t('Page Keywords')}} ({{ (form_data.meta_keywords || '').length }})</label>
				<input ref="meta_keywords" type="text" v-model="form_data.meta_keywords" class="form-control"/>
				<p v-if="form_data_errors.meta_keywords" class="form-error" >{{ form_data_errors.meta_keywords }}</p>
			</div> -->

			<!-- ---------------------------- -->
			<!-- Page description -->
			<div class="label-group col-span-3">
				<label class="form-label">{{ $t('Page Description')}} ({{ (form_data.meta_description || '').length }} / 120-160 )</label>
				<input ref="meta_description" type="text" v-model="form_data.meta_description" class="form-control"/>
				<p v-if="form_data_errors.meta_description" class="form-error" >{{ form_data_errors.meta_description }}</p>
			</div>

			<!-- ============================================================ -->
			<!-- Open Graph / Social -->
			<div class="col-span-3  mt-1 pt-3">
				<span class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Open Graph / Social</span>
			</div>

			<!-- OG Image -->
			<div class="label-group col-span-2">
				<label class="form-label">{{ $t('OG Image URL') }}</label>
				<input type="text" v-model="form_data.og_image" class="form-control"
					:placeholder="$t('Global fallback used if empty')"/>
			</div>

			<!-- Twitter Image (separate override) -->
			<div class="label-group">
				<label class="form-label">{{ $t('Twitter Image URL') }} <span class="text-gray-400 text-xs">({{ $t('uses OG if empty') }})</span></label>
				<input type="text" v-model="form_data.twitter_image" class="form-control"/>
			</div>

			<!-- OG Title override -->
			<div class="label-group col-span-2">
				<label class="form-label">{{ $t('OG Title') }} <span class="text-gray-400 text-xs">({{ $t('uses Page Title if empty') }})</span></label>
				<input type="text" v-model="form_data.og_title" class="form-control"/>
			</div>

			<!-- JSON-LD nodes -->
			<div class="label-group v-center gap-3">
				<Checkbox v-model="form_data.jsonld_add_organization" :text="'JSON-LD: Organization'" />
				<Checkbox v-model="form_data.jsonld_add_software"     :text="'JSON-LD: SoftwareApp'" />
			</div>

			<!-- OG Description override -->
			<div class="label-group col-span-3">
				<label class="form-label">{{ $t('OG Description') }} <span class="text-gray-400 text-xs">({{ $t('uses Page Description if empty') }})</span></label>
				<input type="text" v-model="form_data.og_description" class="form-control"/>
			</div>

			<!-- ============================================================ -->
			<!-- AI generation block -->
			<div class="col-span-3 flex items-center">
				<div class="label-group w-4/5">
					<label class="form-label">{{ $t('AI wishes') }}</label>
					<textarea
						v-model="ai_wishes"
						class="form-control"
						rows="2"
						:placeholder="$t('Extra instructions for AI (tone, audience, keywords to emphasise, etc.)')"/>
				</div>
				<div class="flex justify-end mt-2 w-1/5">
					<button type="button"
						class="button outline-button button-sm flex items-center gap-2"
						:disabled="ai_loading"
						@click="generateMeta">
						<Icon icon="mdi:auto-fix" class="icon icon-sm"/>
						{{ ai_loading ? $t('Generating…') : $t('Generate with AI') }}
					</button>
				</div>
			</div>

		</div>

    </CardTemplate>

</template>

<script>
    import formMixins       from '@/js/_formMixins';
	import modalcardMixins  from '@/js/_modalcardMixins.js'

    import CardTemplate     from '@/js/Elements/CardComponent.vue'
	import Checkbox     from '@/js/Elements/Forms/Checkbox.vue'
	import { Icon } from '@iconify/vue'

    export default {
        components: { CardTemplate, Checkbox, Icon },
        mixins: [modalcardMixins, formMixins],
        data() {
            return {
				ai_loading: false,
				ai_wishes:  '',
                table_settings: {
                    columns: [
                        { field: 'id' },
                        { field: 'type',         title: 'Type',      width:'min-content',          type:'string' },
                        { field: 'timestamp',    title: 'Timestamp', width:'min-content',          type:'string' },
                        { field: 'message',      title: 'Message',   width:'2fr',        type:'string' },
                    ],
                },
				locales: [
					{id:'uk', name:'Ukrainian'},
					{id:'en', name:'English'},
					{id:'ru', name:'Russian'},
				],
				frequencies: [
					{id:'always', name:'always'},
					{id:'hourly', name:'hourly'},
					{id:'daily', name:'daily'},
					{id:'weekly', name:'weekly'},
					{id:'monthly', name:'monthly'},
					{id:'yearly', name:'yearly'},
					{id:'never', name:'never'},
				],
            }
        },
        mounted() {

        },
        methods: {
            async update() {
                // console.log('update');
                let result = await this.$apiClient.post( route('admin.get.logs'), {cashbox_id: this.form_data.id} )
                if ( result.error )
                    return this.$toast.error( result.message )

                this.table_data = result.data;
            },
			async generateMeta() {
				// console.log('[SeoCard.generateMeta]')
				if ( !this.form_data.route_name )
					return this.$toast.error( this.$t('Route is required') )
				if ( !this.form_data.locale )
					return this.$toast.error( this.$t('Locale is required') )

				this.ai_loading = true
				try {
					let result = await this.$apiClient.post( route('cabinet.api.seodata.generatemeta'), {
						route_name: this.form_data.route_name,
						locale:     this.form_data.locale,
						page_name:  this.form_data.page_name,
						context:    this.ai_wishes,
					})
					if ( result.error )
						return this.$toast.error( result.message || this.$t('AI generation failed') )

					let d = result.data || {}
					if ( d.page_name )        this.form_data.page_name        = d.page_name
					if ( d.meta_title )       this.form_data.meta_title       = d.meta_title
					if ( d.meta_keywords )    this.form_data.meta_keywords    = d.meta_keywords
					if ( d.meta_description ) this.form_data.meta_description = d.meta_description

					this.$toast.success( this.$t('AI metadata generated') )
				} finally {
					this.ai_loading = false
				}
			},
        }
    }
</script>

<style lang="scss" scoped>

</style>
