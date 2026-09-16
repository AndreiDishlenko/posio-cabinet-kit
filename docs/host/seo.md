# SEO: writing public pages so the cabinet can drive their meta

> Generated from `posio/cabinet-kit`. Do not edit. See [README.md](README.md).

## What this is

The cabinet's **SEO** section (`cabinet-kit.seo`, permission `sysper-site`) is a
table of per-page records in `seo_meta`. One record = one **route name** (+ an
optional locale). For the current route the package builds, on every full
render, one `seo` Inertia prop containing:

- `<title>`, `description`, `robots` (`index`/`noindex`);
- Open Graph and Twitter Card tags, with a global image fallback;
- `canonical` and `hreflang` alternates;
- a Schema.org **JSON-LD `@graph`**: `WebSite`, `Organization`, `WebPage`,
  optional `BreadcrumbList`, `ItemList` of the main navigation and an optional
  `SoftwareApplication`.

`sitemap.xml` is generated from the same records.

Nothing in this pipeline knows about your pages — it is driven entirely by
**route names**. Everything below follows from that.

## Checklist: a new public page that is SEO-ready

### 1. Give the route a name, without a locale suffix

```php
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
```

A multi-locale site registers one route per locale and names them
`{base}.{locale}`:

```php
Route::get('/pricing',    ...)->name('pricing.en');
Route::get('/uk/ciny',    ...)->name('pricing.uk');
```

The package strips the locale suffix and looks the record up by the **base**
name (`pricing`). Locales come from `config('general.locales')`.

> A record whose `route_name` is `pricing.uk` will never be found. Store
> `pricing` and set the record's `locale` field instead.

### 2. Render the meta component once, in the public layout

```html
<template>
    <SeoMeta/>
    <slot/>
</template>

<script>
    import SeoMeta from '@cabinet-kit/сomponents/SeoMeta.vue';

    export default {
        components: { SeoMeta },
    }
</script>
```

Rules:

- Mount it in the **layout**, not in each page — one instance per rendered page.
- It reads `$page.props.seo` itself; it takes no props.
- Keep a plain fallback `<title>` in the Blade view, but **remove any static
  og:/twitter:/description tags from Blade** — otherwise every tag is emitted
  twice and crawlers see conflicting values.
- With SSR enabled the tags land in the server-rendered `<head>`, which is the
  point: crawlers see them without running JS.

### 3. Add the record in the cabinet

Cabinet → **SEO** → add a row:

| field | meaning |
|---|---|
| `Route` | base route name (`pricing`) — must match exactly |
| `Locale` | empty = for every locale; otherwise this locale only |
| `Page Name` | short human name; used in breadcrumbs and in navigation JSON-LD |
| `Page Title` | `<title>`; the site name is appended as a suffix |
| `Page Description` | `description` and, unless overridden, OG/Twitter description |
| `Google index` | off ⇒ `noindex, nofollow` **and** the page is left out of the sitemap |
| `Active` | the row is in use |
| `Change Frequency`, `Priority` | sitemap hints |
| OG / Twitter fields | per-page overrides; empty ⇒ the page title/description and the global image |
| `JSON-LD: SoftwareApp` | force the product node on a page other than the home page |

A record for `home` is created during installation — edit it, do not add a
second one.

### 4. Breadcrumbs (optional, for nested pages)

In the controller, before rendering:

```php
use Posio\CabinetKit\Services\BreadcrumbService;

app(BreadcrumbService::class)
    ->add('home')
    ->add('pricing');
```

Names and URLs are taken from the SEO records of those routes, so a crumb needs
a record too. The home page gets no crumbs.

### 5. Main navigation nodes

List the base route names of the site's main menu in `config/seo.php`:

```php
'sitenav_routes' => ['home', 'pricing', 'about', 'contacts'],
```

They become an `ItemList` of `SiteNavigationElement` — a hint Google uses for
sitelinks. A route that is not registered is skipped silently.

### 6. Regenerate the sitemap

```bash
php artisan sitemap:generate      # writes public/sitemap.xml
```

Also available from the SEO page ("Create sitemaps.xml" button). The generator,
`spatie/laravel-sitemap`, is installed with the package. Treat `public/sitemap.xml` as a build artifact: regenerate it on
deploy rather than trusting the committed copy.

## Site-wide values: `config/seo.php`

Published by the installer. Everything here is what the operator does *not*
edit from the cabinet:

- `brand_name` — short brand: `og:site_name`, `WebSite.alternateName`. The site
  name from the cabinet overrides the visible name everywhere else.
- `org_logo` — **square** logo for `Organization.logo`. The uploaded site logo
  is used instead only when it has actually been replaced (it is horizontal, and
  Google wants a square one).
- `org_email`, `org_sameas`, `org_languages`, `org_description` — the
  `Organization` node.
- `software.enabled` + `software_description` + `software_features` — the
  `SoftwareApplication` node. Leave `enabled => false` on a site that is not a
  product; then the node never appears, not even on the home page.
- `sitenav_routes` — see above.

`config/general.php` holds `locales` (drives hreflang, sitemap and the locale
suffix stripping) and `default_og_image` (+ width/height).

## `robots.txt` and `llms.txt`

The package ships neither — they are host files. A workable `public/robots.txt`:

```
User-agent: *
Allow: /
Disallow: /cabinet
Disallow: /api/
Disallow: /login
Disallow: /register

Sitemap: https://example.com/sitemap.xml
```

Close the application zone (cabinet, API, auth), keep the public zone open, and
name the sitemap. If AI crawlers are welcome, allow them explicitly in their own
group (`GPTBot`, `ClaudeBot`, `OAI-SearchBot`, `PerplexityBot`,
`Google-Extended`, `CCBot`) with the same disallow list.

## Reading the data from PHP

```php
use Posio\CabinetKit\Services\SeoService;

$seo = app(SeoService::class);
$seo->baseRouteName();                  // current route without locale suffix
$seo->getRouteSeoData('pricing');       // the record for a route
$seo->getInfo();                        // the whole payload the prop carries
```

## "Generate with AI" button

The SEO card has a button that drafts `page_name` / `meta_title` /
`meta_description`. The package ships no language model: point
`cabinet-kit.seo.meta_generator` at a class of yours with

```php
public function generate(array $input): array  // ['route_name', 'locale', 'page_name', 'context']
// returns ['page_name' => ..., 'meta_title' => ..., 'meta_description' => ...]
```

Until then the button answers "AI meta generation is not configured".

## Turning the layer off

On a project with no public site set `cabinet-kit.seo.share_prop => false` —
nothing reads the prop there and it stops being computed per request.

## Common mistakes

- `route_name` stored with the locale suffix (`home.uk`) — the record is never
  found and the page silently gets empty meta.
- The page renders fine but the meta is missing: `SeoMeta` was not mounted in
  the layout, or the route has no name at all.
- Static `og:` tags left in the Blade view next to the component — duplicated
  tags.
- `Google index` left off on a page that should rank — it also drops the page
  from the sitemap.
- A new page added to `sitenav_routes` but without an SEO record — it appears in
  the navigation graph under a name derived from the route, not a human one.
- Editing meta by writing to `seo_meta` directly from application code: the
  section in the cabinet is the intended editor, and the service caches the set
  per request.
