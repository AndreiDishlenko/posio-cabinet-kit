# Site settings (brand): making host pages honour them

> Generated from `posio/cabinet-kit`. Do not edit. See [README.md](README.md).

## What this is

Two cabinet sections — **Site settings** (`cabinet-kit.sitesettings`) and
**Cabinet settings** (`cabinet-kit.cabinetsettings`) — let an operator set,
without touching code or static files:

- the **site name** (browser tab title, brand name in structured data);
- the **default theme** (`dark` / `light`) the public part and the cabinet open
  with, until a visitor picks their own;
- the **favicon** and the **logos** — separately for the public site and for the
  cabinet, each in a dark-theme and a light-theme variant, plus a square
  **symbol** for the collapsed side menu of the cabinet.

Values live in the `site_settings` table (key → value), uploads in
`storage/app/public/site/`. Every image has a neutral placeholder shipped by the
package, so nothing is ever empty. Both sections are gated by the `sysper-site`
system permission.

**Priority:** per-page SEO meta (SEO section) → site settings → `config/seo.php`
defaults. Site settings are the *default*, not the last word.

## Requirements in the host project

1. `php artisan storage:link` — uploads are served through `/storage/...`.
2. The `site_settings` table exists (`php artisan migrate`).
3. Nothing else. The cabinet's own pages, favicon, theme and side-menu logos
   work as soon as the package is installed.

## Checklist: make the public site follow the settings

### 1. Register the public Blade view

`config/cabinet-kit.php`:

```php
'site' => [
    'views' => [
        'cabinet-kit::app' => 'cabinet',   // the cabinet's own root view — keep
        'main'             => 'main',      // the host's public root view
        // 'app'           => null,        // name only: this view keeps its own icon/theme
    ],
    'share_prop' => true,
],
```

The key is a **Blade view name** (`resources/views/main.blade.php` → `'main'`).
The value picks which settings that view receives:

| value | favicon key | theme key |
|---|---|---|
| `'main'` | `main_favicon` | `main_theme` |
| `'cabinet'` | `cabinet_favicon` | `cabinet_theme` |
| `null` | — (view keeps its own) | — |

### 2. Print the variables in that Blade view

A view listed above receives `$site_name`, `$site_favicon`, `$site_theme`:

```blade
<title inertia>{{ $site_name }}</title>

@isset($site_favicon)
    <link rel="icon" href="{{ $site_favicon }}">
@endisset

{{-- The theme class must be set before the first paint, otherwise the page
     flashes the wrong theme until Vue mounts. The visitor's own choice wins. --}}
<script>
(function () {
    var html  = document.documentElement;
    var saved = localStorage.getItem('theme');

    html.classList.add(saved === 'light' || saved === 'dark' ? saved : '{{ $site_theme ?? 'dark' }}');
})();
</script>
```

Use `?? ` fallbacks only for the theme; the name and the favicon URL are never
empty when settings are reachable.

### 3. Use the logos in Vue

The `site` Inertia prop is shared on **every** Inertia response:

```js
{
    name: 'Acme',
    main:    { logo_dark, logo_light },
    cabinet: { logo_dark, logo_light, symbol_dark, symbol_light },
}
```

Render both variants and let the theme choose which is visible — swapping `src`
in JS makes the wrong logo flash on first paint:

```html
<img :src="brand.logo_dark"  class="brand-img brand-theme-dark"  :alt="brand_name"/>
<img :src="brand.logo_light" class="brand-img brand-theme-light" :alt="brand_name"/>
```

```js
computed: {
    brand()      { return this.$page.props.site?.main ?? {}; },
    brand_name() { return this.$page.props.site?.name || ''; },
},
```

```scss
.brand-theme-light { display: none; }

html.light {
    .brand-theme-dark  { display: none; }
    .brand-theme-light { display: block; }
}
```

**Do not** hardcode a logo path in a template and **do not** add your own
fallback path: `imageUrl()` always returns something.

## Putting this installation's own brand in

Two ways, both idempotent:

- **Upload** the files in the cabinet section (recommended).
- **Bulk import:** drop files into `public/temp/` named exactly after the
  setting keys (`main_favicon.ico`, `main_logo_dark.svg`,
  `cabinet_symbol_dark.svg`, …) and run `php artisan site:import-brand`. Keys
  that already have a value are left alone, so it doubles as a restore path:
  clear an image in the cabinet, run the command again.

`public/temp/` is not a temporary folder despite the name — it is the only place
brand files can travel in with the code (`storage/` is not in the repository).

## Reading the values from PHP

```php
use Posio\CabinetKit\Services\SiteSettingsService;

$settings = app(SiteSettingsService::class);

$settings->siteName();                       // name, with config/app fallbacks
$settings->imageUrl('main_logo_dark');       // never empty
$settings->hasCustomImage('main_logo_dark'); // false while the placeholder is in use
$settings->theme('main_theme');              // 'dark' | 'light'
```

The whole set is cached (`Cache::rememberForever('site_settings')`) and the
cache is dropped on every write — it is read on every page render, so do not
bypass the service with direct queries.

## Common mistakes

- Adding a Blade view to `site.views` but never printing the variables — the
  operator changes the favicon and nothing happens.
- Setting the theme class from Vue instead of the inline script — the page
  flashes the other theme on every load.
- Uploading a horizontal logo where a square one is expected (structured data,
  collapsed side menu). The symbol slots exist for that reason.
- Expecting `.webp`/`.avif` in the favicon slot: the accepted list is
  `png, jpg, jpeg, webp, svg, ico`, max 2 MB, and old Safari does not decode an
  SVG tab icon — ship `.png` or `.ico` for the favicon.
