<template lang="">
    <div ref="form" class="card !space-y-5">

        <div class="card-body">
            <!-- Toggle row -->
            <div class="flex items-center space-x-2">
                <input type="checkbox" class="form-control" v-model="enabled">
                <h2 class="text-yellow">{{ name }}</h2>
            </div>

            <!-- Fields -->
            <div v-if="enabled" class="v-flex space-y-3 mt-4">
                <div v-for="field in fields" :key="field.key" class="label-group">
                    <label class="form-label">{{ $t(field.label) }}:</label>
                    <input
                        type="text"
                        v-model="form_data[field.key]"
                        class="form-control"
                        />
                </div>
            </div>
        </div>

        <div class="card-footer d-center">
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
</template>

<script>
    import formMixins       from '@/js/_formMixins'

    import { router }       from '@inertiajs/vue3';

    export default {
        mixins: [formMixins],
        props: {
            name: {
                type: String,
                required: true
            },
            integration_key: {
                type: String,
                required: true
            },
            fields: {
                type: Array,
                default: () => []
            }
        },
        computed: {
            enabled: {
                get() {
                    return this.form_data?.enabled || false;
                },
                set(value) {
                    this.form_data.enabled = value;
                }
            }
        },
        methods: {
            save() {
                // Only this integration's slice is sent; the backend merges it
                // with the existing integrations so the others are preserved.
                router.post(
                    route('cabinet.account.integrations.update'),
                    { [this.integration_key]: this.form_data },
                    {
                        onError: (errors) => {
                            if ( errors.error )
                                this.$toast.error(errors.error)
                        },
                        onSuccess: () => {
                            this.updateInData();
                            this.$toast.success( this.$t('Integrations was updated') );
                            this.$nextTick(() => {
                                this.is_changed = false;
                            });
                        },
                        preserveScroll: true,
                        preserveState: true,
                    }
                );
            }
        }
    }
</script>

<style lang="scss" scoped>

</style>
