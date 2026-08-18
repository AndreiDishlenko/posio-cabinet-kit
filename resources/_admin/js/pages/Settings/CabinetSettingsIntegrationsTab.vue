<template lang="">
    <div class="v-flex items-stretch space-y-6">

        <!-- Common header -->
        <!-- <div class="px-1">
            <h1 class="text-color">{{ $t('Integrations for account') }} {{ account_name }}</h1>
        </div> -->

        <!-- One card per integration -->
        <IntegrationCard
            v-for="integration in integrations"
            :key="integration.key"
            :name="integration.name"
            :integration_key="integration.key"
            :fields="integration.fields"
            :in_data="in_data?.[integration.key]"
            />

    </div>
</template>

<script>
    import IntegrationCard  from './IntegrationCard.vue';

    export default {
        components: { IntegrationCard },
        props: {
            in_data: {
                type: Object,
                default: () => ({})
            },
            account_name: {
                type: String,
                default: ''
            }
        },
        data() {
            return {
                integrations: [
                    {
                        name: 'Monobank',
                        key: 'monobank',
                        fields: [
                            { key: 'access_token',  label: 'Monobank Token' },
                            { key: 'redirect_url',  label: 'Redirect URL' },
                            { key: 'qr_identifier', label: 'QR cash register identifier' }
                        ]
                    },
                    {
                        name: 'Telegram',
                        key: 'telegram',
                        fields: [
                            { key: 'bot_token', label: 'Telegram Bot Token' },
                            { key: 'chat_id',   label: 'Telegram Chat Id' },
                            { key: 'thread_id', label: 'Telegram Thread Id' }
                        ]
                    }
                ]
            }
        }
    }
</script>

<style lang="scss" scoped>

</style>
