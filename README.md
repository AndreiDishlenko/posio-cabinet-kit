# posio/cabinet-kit

Base admin-panel scaffolding extracted from `posio.cabinet`: multi-tenant
accounts, per-account roles/permissions (Spatie Permission teams), bundled
auth (login, register, logout, password reset, email verification), a
Settings shell, a collapsible side-menu layout, and a small Vue 3 UI kit —
for bootstrapping a new Laravel + Inertia + Vue 3 project's admin part.

Not a finished product — a **shell** you extend per project. See
`docs/ARCHITECTURE.md` and `docs/EXTENDING.md`.

## Requirements

- Laravel 11/12, PHP 8.2+
- Inertia.js (Laravel adapter) + Vue 3, Options API
- `spatie/laravel-permission` (pulled in automatically)
- `tightenco/ziggy` (composer) + npm: `ziggy-js`, `@iconify/vue`, `mitt` —
  package pages resolve URLs through `route()` and use Iconify icons
- A `User` model with `password`/`email_verified_at` columns (Laravel's
  default `users` migration already has both)

## Install (in a consumer project)

```bash
composer config repositories.cabinet-kit vcs F:/Packages/posio-cabinet-kit
composer require posio/cabinet-kit
php artisan cabinet-kit:install
```

The install command publishes `config/cabinet-kit.php`, runs migrations,
optionally seeds base roles, scaffolds `resources/_admin/overrides/` (with a
README explaining the override mechanism) and the cabinet Vite entry
`resources/_admin/js/admin.js` (from `stubs/cabinet-entry.js.stub`). It then
prints the remaining manual steps: adding the `HasAccount` / `HasSettings` /
`HasCustomFields` traits to your `User` model, and wiring vite.config.js
(alias + `fs.allow` + `input` entry) and the Tailwind `content` glob per
`stubs/vite-alias-snippet.js`.

Every CabinetKit page renders into the package's own Blade root view
(`cabinet-kit::app`) with its own Vite entry — the host's main app view and
entry are untouched.

Visit `/cabinet/register` afterwards to create your first user + account —
registration bundles account creation (a "Company name" field), so there's
no separate account-creation step for a brand-new install.

## Update

```bash
composer update posio/cabinet-kit
php artisan cabinet-kit:sync-config   # reports any new config keys to add by hand
```

Vue/SCSS/routes/migrations are read straight from `vendor/posio/cabinet-kit/`
— nothing was copied into your project, so there's nothing to merge.
Anything you deliberately overrode in `resources/_admin/overrides/` keeps
working untouched.

## Customizing

- Menu items, Settings tabs, assignable roles → `config/cabinet-kit.php`
  (survives updates for free).
- Deeper changes → `resources/_admin/overrides/pages/...` (checked before
  the package's own version — see `docs/EXTENDING.md`).

## Developing this package itself

Open this repo directly (`F:\Packages\posio-cabinet-kit`) and follow its own
`CLAUDE.md`. Tag a new semver version after merging a change; consumer
projects pick it up with `composer update posio/cabinet-kit`.
