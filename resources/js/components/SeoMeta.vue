<template>

    <!-- {{  this.$page.props.seo  }} -->

    <Head>

        <title v-if="title">{{ title }}</title>

        <meta v-if="description"    name="description"	:content="description" />
        <!-- <meta v-if="keywords"       name="keywords"     :content="keywords" /> -->

		<template v-if="!$page.props.seo?.enableindex" >
			<meta name="robots" content="noindex, nofollow" />
		</template>
		<template v-else>
			<meta name="robots" content="index, follow" />
		</template>

        <!-- Open Graph -->
        <meta property="og:title"       :content="ogTitle" />
        <meta property="og:description" :content="ogDescription" />
        <meta property="og:type"        :content="type" />
        <meta property="og:url"         :content="url" />
        <meta property="og:site_name"   :content="siteName" />
        <meta property="og:locale"      :content="ogLocale" />

        <template v-if="ogImage">
            <meta property="og:image"        :content="ogImage" />
            <meta property="og:image:width"  :content="$page.props.seo?.og_image_width  || 1200" />
            <meta property="og:image:height" :content="$page.props.seo?.og_image_height || 628" />
        </template>

        <meta v-for="loc in ogLocaleAlternate" :key="loc"
              property="og:locale:alternate" :content="loc" />

        <!-- Twitter Card -->
        <meta name="twitter:card"        content="summary_large_image" />
        <meta name="twitter:title"       :content="twitterTitle" />
        <meta name="twitter:description" :content="twitterDescription" />
        <meta v-if="twitterImage" name="twitter:image" :content="twitterImage" />

        <link rel="canonical" :href="canonical || url" />

		<link v-for="(key, value) in ($page.props.seo?.alternate || {})" rel="alternate" :hreflang="value"	:href="key">

        <component v-if="jsonLdRaw" is="script" type="application/ld+json">
            {{jsonLdRaw}}
        </component>

    </Head>

</template>

<script>
    // import JsonLd from './JsonLd.vue'
    // import { useHead } from '@vueuse/head'
    import { Head } from '@inertiajs/vue3';

    export default {
        name: 'SeoMeta',
        components: { Head },
        props: {
            page_name: {
                type: String,
                required: true
            },
            url: {
                type: String,
                default: () => (
                    typeof window !== 'undefined' ? window.location.href : ''
                )
            },
            image: {
                type: String,
                default: ''
            },
            type: {
                type: String,
                default: 'website'
            },
            jsonLd: {
                type: Object,
                default: null
            },
            twitterHandle: {
                type: String,
                default: '@yourbrand'
            },
            canonical: {
                type: String,
                default: null
            }
        },
        computed: {
            title() {
                return this.$t(
                    this.$page.props.seo?.meta_data?.title ? this.$page.props.seo.meta_data.title : this.page_name
                ) + ' | Posio';
            },
            description() {
                return this.$page.props.seo?.meta_data?.description ? this.$page.props.seo.meta_data.description : ''
            },
            keywords() {
                return this.$page.props.seo?.meta_data?.keywords ? this.$page.props.seo.meta_data.keywords : ''
            },
            siteName() {
                return this.$page.props.seo?.site_name || 'POSIO';
            },
            ogTitle() {
                return this.$page.props.seo?.og_title || this.title;
            },
            ogDescription() {
                return this.$page.props.seo?.og_description || this.description;
            },
            ogImage() {
                return this.$page.props.seo?.og_image || '';
            },
            ogLocale() {
                return this.$page.props.seo?.og_locale || 'en_US';
            },
            ogLocaleAlternate() {
                return this.$page.props.seo?.og_locale_alternate || [];
            },
            twitterTitle() {
                return this.$page.props.seo?.twitter_title || this.ogTitle;
            },
            twitterDescription() {
                return this.$page.props.seo?.twitter_description || this.ogDescription;
            },
            twitterImage() {
                return this.$page.props.seo?.twitter_image || this.ogImage;
            },
            jsonLdRaw() {
				// console.log('aaa', this.$page?.props?.seo?.jsonld);
                if (this.$page?.props?.seo?.jsonld)
                    return JSON.stringify( this.$page.props.seo.jsonld, null, 2)

                return null
            }
        },
		mounted() {

		}
    }
</script>
