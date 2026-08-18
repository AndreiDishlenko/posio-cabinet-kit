import Table            from '@/js/Elements/Table.vue';
import reorderMixins    from '@/js/_reorderMixins.js';

export default {
    mixins: [reorderMixins],
    components: { Table },
    props: {
        in_table_data: {
            type: Object,
            default: []
        }
    },
    data() {
        return {
            table_settings: {},
            table_data: [],
            currentRow: {},
            // Затвор первинного завантаження: панель фільтрів, якщо вона є на сторінці,
            // реєструється тут і сама каже, коли значення фільтрів підставлені.
            filters_gate: { registered: false, ready: false },
            initial_load_started: false,
        };
    },
    provide() {
        return { filters_gate: this.filters_gate };
    },
    watch: {
        in_table_data: {
            handler(val, oldVal) {
                this.table_data = val;
            },
            immediate: true
            // deep: true,
        },
        'filters_gate.ready'(is_ready) {
            if ( is_ready )
                this.startInitialLoad();
        },
    },
    async mounted() {
        // console.log('tableformMixin.mount', this.table_data, typeof this.table_data);

        // Без панелі фільтрів (або коли вона вже готова) вантажимо одразу; інакше
        // запит пішов би з порожнім обовʼязковим фільтром і повернув ніщо.
        if ( !this.filters_gate.registered || this.filters_gate.ready )
            this.startInitialLoad();

        // this.$emitter.on('global_update', () => {this.update()});
    },
    beforeDestroy() {
        // this.$emitter.off('global_update');
    },
    methods: {
        // Первинне завантаження рівно один раз, з якого б боку не прийшов сигнал.
        startInitialLoad() {
            if ( this.initial_load_started )
                return;

            this.initial_load_started = true;

            this.$nextTick(async () => {
                await this.update();
            });
        },
        update() {
            console.log('Please define update function for tableformMixin');
        },
        async confirmDelete() {
            let result = await this.$popup.confirm_yn( this.$t(`Are you sure you want to delete this entry?`), { danger: true } );

            return result;
        },
        async confirmRestore() {
            let result = await this.$popup.confirm_yn( this.$t(`Are you sure you want to restore the record?`) );

            return result;
        },

        // Tableform functions
        async updateRecord(route_name, row) {
            let result = await this.$apiClient.post( route( route_name ), row)
            if (result.error) {
                // console.log(result.errors);               
                this.outputErrors(result.errors);
                return this.$toast.error( result.error ) 
            }
        },

		getRoutePrefix(route_prefix) {
			// console.log('getRoutePrefix', route_prefix);			
			if (!this.route_prefix && !route_prefix)
				return this.$toast.error('Route prefix is undefined');

			return route_prefix ? route_prefix : this.route_prefix
		},
		getDictionaryName(dictionary_name) {
			if (!this.dictionary_name && !dictionary_name)
				return this.$toast.error('Dictionary is undefined');

			return dictionary_name ? dictionary_name : this.dictionary_name
		},

        // Modalform functions
        async addTableRecord(row, var_currentRow = 'currentRow', var_modalForm = 'modalform') {
            this[var_currentRow] = row;
            this.$refs[var_modalForm].open();
                
            return row;       
        },

        openTableRecord(row, modal_name) {
            // console.log('tableformMixins.openTableRecord', row);
            if (row.is_deleted)
                return this.$toast.error('Record is deleted. Can`t open.')

            this.currentRow = row;
            // this.currentRow.fields = {}
            // console.log('test');
            
            this.$refs[modal_name ?? 'modalform'].open();
            return true;
        },

		async deleteDictionaryRecord(row, options, confirm=true, route_prefix='', dictionary_name='') {
			let result = await this.deleteTableRecord(row, options, confirm, route_prefix)

			dictionary_name = this.getDictionaryName(dictionary_name)
			if ( dictionary_name )
                this.$dictionaries.save(dictionary_name)

			return result;
		},

        async deleteTableRecord(row, options, confirm=true, route_prefix='') {
            // console.log('deleteTableRecord', dictionary_name);            
            if (confirm && !await this.confirmDelete())
                return false;

            let payload = {
                id:       row.id,
                ...options
            }
			let route_name = this.getRoutePrefix(route_prefix)+'.delete'
            // console.log('deletePos', row);

			let result = await this.$apiClient.post( route( route_name ), payload)
            if (result.error) 
                return this.$toast.error( `Server error. Record not deleted. ${result.error.message || result.error}` ) 

            row.is_deleted = true;
            // this.$toast.success( 'Record was deleted' );
        },

		async restoreDictionaryRecord(row, confirm=true, route_prefix='', dictionary_name='') {
			let result = await this.restoreTableRecord(row, confirm, route_prefix)

			dictionary_name = this.getDictionaryName(dictionary_name)
			if ( dictionary_name )
                this.$dictionaries.save(dictionary_name)

			return result;
		},

        async restoreTableRecord(row, confirm=true, route_prefix='', options={}) {
            // console.log('restoreTableRecord', dictionary_name);
            if (confirm && !await this.confirmRestore())
                return false;

            let payload = {
                id:       row.id,
                ...options
            }
			let route_name = this.getRoutePrefix(route_prefix)+'.restore'

			let result = await this.$apiClient.post( route( route_name ), payload)
			// console.log('aa', result);
			
            if (result.error) 
                return this.$toast.error( `Server error. Record not deleted. ${result.error.message || result.error}` ) 

            row.is_deleted = false;
            // this.$toast.success( 'Record was restored' );
        },

        // Row reordering (reorderTableRow / reorderLocalRows) is provided by _reorderMixins.

        // closeModal(table_data = this.table_data, currentRow = this.currentRow, modalform = this.$refs.modalform ) {
		closeTableModal(options={}) {
			options = {
				table_data: options.table_data ?? this.table_data,
				currentRow: options.currentRow ?? this.currentRow,
				modal: options.modal ?? this.$refs.modalform,
			}

			// console.log('closeTableModal', options);

			// console.log('a1', currentRow);			
            // console.log('closeModal table_data', table_data);
            // console.log('closeModal currentRow', currentRow);            
            // console.log('closeModal', modalform, '1', currentRow, '2', table_data);

            if ( !options.currentRow.id ) {
				// console.log('delete row from table')
                const index = options.table_data.indexOf(options.currentRow);
				// console.log('gg', currentRow, table_data.indexOf(currentRow));
                if ( index !== -1 )
                    options.table_data.splice(index, 1)
            }

            options.modal.close()            
            // this.$refs.maintable.selectLast()
        },

        translateData(source_data, translate_items) {
            let result = source_data.map(item => {
                for (let key in item) {
                    // console.log(item[key]);                    
                    if ( translate_items.includes(key) )
                        item[key] = this.$t(item[key] ?? '')
                }
                return item;
            });
            // console.log('res', result);
            
            return result;
        },




        // async requestTableData(api_route, headers) {
        //     let result = await this.$apiClient.get( 
        //         route(api_route), 
        //         headers
        //     );

        //     if (result.error) {
        //         console.error(`[${module_name}.requestTableData] Records can't be updated. ${result.data}`)
        //         this.$toast.error(`Estate can't be saved. ${result.error}`)
        //         return []
        //     }

        //     return result.data;
        // },
    }
};