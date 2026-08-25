<template lang="">

    <CabinetLayout>

		<!-- <div class="flex"> -->

			<!-- Choose route -->
			<!-- <div class=""> -->
				<Table class="grow table-md"
					storage_key    = "seo"
					:settings  = "table_settings"
					:in_data   = "table_data" 
					:selects	= "dynamic_selects"
					:filters   	= "dynamic_filters"
					@rowSelect = "row => openTableRecord(row)"
					@onAdd     = "row => addTableRecord(row)"
					@onOpen    = "row => openTableRecord(row)"
					@onDelete  = "row => deleteTableRecord(row)"
					@onRestore = "row => restoreTableRecord(row, true)"
					/>
			<!-- </div> -->
			
			<!-- Edit route -->
			<!-- <div class="p-4">
			</div> -->

		<!-- </div> -->


        <ModalForm ref="modalform" :outsideClickClose="false" :escToClose="false">
            <SeoCard 
                ref="seocard" 
                :in_data		 = "currentRow" 
				:route_prefix	 = "route_prefix"
                @close="closeTableModal({})"
                />
        </ModalForm>

    </CabinetLayout>
</template>

<script>
    import sharedMixins     from '@/js/_sharedMixins'
    import indataMixins     from '@/js/_indataMixins';
    import tableformMixins  from '@/js/_tableformMixins.js';

	// import _formMixins 		from '@/js/_formMixins';

    import CabinetLayout    from '@/_admin/js/layouts/CabinetLayout.vue';
    import ModalForm        from '@/js/Elements/ModalForm.vue';
    import SeoCard 			from '@/_admin/js/components/cards/SeoCard.vue';

    export default {
        mixins: [sharedMixins, indataMixins, tableformMixins],
        components: { CabinetLayout, ModalForm, SeoCard },
        data() {
            return {
				route_prefix	: 'cabinet.api.seodata',
				// dictionary_name	: 'poses',

                // module_name: 'Pos',
                table_settings: {
                    columns: [
                        { field: 'id' },
                        { field: 'route_name',	 title: 'Route',      width:'auto',  type:'string',  align:"start" },
                        { field: 'locale',		 title: 'Locale',     width:'auto',  type:'string',  align:"start" },
                        { field: 'page_name',  	 title: 'Page Name',       width:'auto',  type:'string',  align:"start",  show:'sm' },
                        { field: 'index', 		 title: 'Google index',    width:'auto',  type:'checkbox',  align:"start",  show:'md' },
                        { field: 'is_published', title: 'Active',    width:'min',  type:'checkbox' },
                    ],
                    rowbar: [
                        // { event: 'onOpen', icon: 'material-symbols:settings-outline' },
                    ],
                    filters: {
                        deleted: true
                    },
					defaults: {
						changeFrequency: 'weekly',
						priority:        0.9,
						is_published:    1,
					},
					groupactions: { add:true },
					panelitems: [
						{ name:'Create sitemaps.xml', type:'button', text:'Create sitemaps.xml', action: ()=>{ this.createSitemaps() }}
					]
                },
				current_record: {}
            }
        },
		computed: {
			// That's for dinamyc values refresh
			dynamic_selects() {
				return {
					"price_id": this.$dictionaries.prices,
				}
			},
			dynamic_filters() {
				return []
			}
		},
        async mounted() {
        },
        methods: {
			async update() {
                // console.log('[Seo.update]')
                let result = await this.$apiClient.get( route('cabinet.api.seodata'), {});
                if ( result.error )
                    return this.$toast.error(`Data update error: ${result.message}`);               

				this.table_data = result.data; //this.translateData(result.data, ['doctype_name', 'paytype_name']);
            },
            async createSitemaps() {
				console.log('createSitemaps');
				
                let result = await this.$apiClient.post( route('cabinet.api.createsitemaps'), {});
                if ( result.error )
                    return this.$toast.error(`Data update error: ${result.message}`);               

				return true
			}
        }
    }
</script>

<style lang="scss" scoped>    
</style>