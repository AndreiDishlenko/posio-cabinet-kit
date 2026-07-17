# posio/admin-kit

Base admin-panel scaffolding extracted from `posio.cabinet`: multi-tenant
accounts, per-account roles/permissions (Spatie Permission teams), a
Settings shell, a collapsible side-menu layout, and a small Vue 3 UI kit —
for bootstrapping a new Laravel + Inertia + Vue 3 project's admin part.

Not a finished product — a **shell** you extend per project. See
`docs/ARCHITECTURE.md` and `docs/EXTENDING.md`.

## Requirements

- Laravel 11/12, PHP 8.2+
- Inertia.js (Laravel adapter) + Vue 3, Options API
- `spatie/laravel-permission` (pulled in automatically)
- A `User` model — this package does not provide auth

## Install (in a consumer project)

```bash
composer config repositories.admin-kit vcs F:/Packages/posio-admin-kit
composer require posio/admin-kit
php artisan admin-kit:install
```

The install command publishes `config/admin-kit.php`, runs migrations,
optionally seeds base roles, and scaffolds
`resources/_admin/overrides/` (with a README explaining the override
mechanism). It then prints the remaining manual steps: adding the `HasAccount`
/ `HasSettings` / `HasCustomFields` traits to your `User` model, wiring the
Vite alias (`stubs/vite-alias-snippet.js`), and wiring `admin.js`
(`stubs/admin-entry.js.stub`).

## Update

```bash
composer update posio/admin-kit
php artisan admin-kit:sync-config   # reports any new config keys to add by hand
```

Vue/SCSS/routes/migrations are read straight from `vendor/posio/admin-kit/`
— nothing was copied into your project, so there's nothing to merge.
Anything you deliberately overrode in `resources/_admin/overrides/` keeps
working untouched.

## Customizing

- Menu items, Settings tabs, assignable roles → `config/admin-kit.php`
  (survives updates for free).
- Deeper changes → `resources/_admin/overrides/pages/...` (checked before
  the package's own version — see `docs/EXTENDING.md`).

## Developing this package itself

Open this repo directly (`F:\Packages\posio-admin-kit`) and follow its own
`CLAUDE.md`. Tag a new semver version after merging a change; consumer
projects pick it up with `composer update posio/admin-kit`.
