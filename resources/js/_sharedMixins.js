export default {
    props: {
        page: {
            type: Object,
            default: {
                name: '',
            }
        },
        user: {
            type: Object,
            default: {}
        },
        accounts: {
            type: Array,
            default: ['aaa']
        },
        account: {
            type: Object,
            default: {}
        },
        status: {
            type: String,
            default: ''
        },
        errors: {
            type: Object,
            default: {}
        },
        error: {
            type: String,
            default: ''
        },
        serverlocale: {
            type: String,
            default: ''
        },
        sideMenu: {
            type: Object,
            default: {}
        },
        breadcrumbs: {
            type: Array,
            default: []
        },
        // messages: {
        //     type: Array,
        //     default: []
        // },
    },
    watch() { 
        return {
            'status': {
                handler(val, oldVal) {
                    if ( !val ) return;
                    this.$toast.success( this.$t(val) )
                }, deep: true, immediate: true
            },
            'error': {
                handler(val, oldVal) {    
                    if ( !val ) return;
                    this.$toast.error( this.$t(val) )
                }, deep: true, immediate: true
            }
        }
    },
    mounted() {
        // console.log('sharedMixins mount', this.status);
        // if (this.status)
        //     this.$toast.success( this.$t(this.status) )
        if (this.error)
            this.$toast.error( this.$t(this.error) )

        if (typeof window !== 'undefined') {
            if (window.innerWidth <= 640) 
                this.$is_mobile.value = true
            else 
                this.$is_mobile.value = false

            if (window.innerWidth <= 1024) 
                this.$is_tablet.value = true
            else 
                this.$is_tablet.value = false
        }

        // console.log('main_mnt', this.$is_mobile, this.$is_mobile.value);
    },

};