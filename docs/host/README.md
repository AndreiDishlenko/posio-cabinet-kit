# CabinetKit — integration instructions for this project

> **Audience: any AI assistant or developer working in this repository.**
> Generated from the installed `posio/cabinet-kit` version. Do not edit these
> files — they are overwritten on every package update. Put project-specific
> notes in your own file instead.

This project gets its cabinet (admin panel), its site/brand settings and its SEO
layer from the Composer package `posio/cabinet-kit`. The package code lives in
`vendor/posio/cabinet-kit` and is **read directly from there** by Laravel and by
Vite — nothing is copied into the project.

## Read this before you touch…

| If the task is about… | Read |
|---|---|
| Site name, favicon, logos, default light/dark theme | [site-settings.md](site-settings.md) |
| Page titles, descriptions, Open Graph, JSON-LD, sitemap, `robots` | [seo.md](seo.md) |
| Adding a page/menu item to the cabinet, roles, overriding a cabinet page | `vendor/posio/cabinet-kit/docs/EXTENDING.md` |
| How the package is wired into the host at all | `vendor/posio/cabinet-kit/docs/ARCHITECTURE.md` |
| What changed in the package between versions | `vendor/posio/cabinet-kit/docs/CHANGELOG.md` |

## Hard rules

1. **Never edit anything inside `vendor/posio/cabinet-kit`.** The next
   `composer update` throws the edit away. Every supported way to change
   behaviour is listed in `docs/EXTENDING.md` of the package.
2. **Never copy package files into the project** (Vue pages, SCSS, controllers,
   models, migrations, routes). Vite resolves `@/js/...`, `@/_admin/...` and
   `@cabinet-kit/...` to the package; Laravel loads its routes/migrations/views
   from the package too. A copy is a fork that stops receiving fixes.
3. **Configuration first.** `config/cabinet-kit.php`, `config/seo.php`,
   `config/general.php` and the settings the operator edits in the cabinet cover
   most requests. Only when they cannot, use
   `resources/_admin/overrides/pages/...` (Vue) or
   `resources/_admin/scss/cabinet-kit-overrides.scss` (styles).
4. **Update with one command:** `updcab.bat` in the project root, or `./updcab`
   on a Linux/macOS host over ssh (composer update → config/docs sync →
   migrate → clear → build → doctor, plus a re-cache when `APP_ENV=production`).
   Do not invent your own update sequence.
5. **Diagnose with `php artisan cabinet-kit:doctor`** before concluding that
   something is broken in the package.
6. **Check auth after any change to users, auth config or migrations:**
   `php artisan cabinet-kit:test` runs the package's sign-in and registration
   tests against this project on an in-memory database. `./release` runs them
   through `scripts/pre-push-checks.sh` — do not remove that step.

## What the package puts on the host's plate

The package owns the cabinet and its own Blade root view, so brand and SEO work
there out of the box. The **public part of the site belongs to the host**, so
two things must be wired by whoever builds those pages:

- the public Blade layout must print the shared brand variables — see
  [site-settings.md](site-settings.md);
- the public Inertia layout must render the meta component and the pages must
  have SEO records — see [seo.md](seo.md).

Both files below are written as checklists; follow them literally.
