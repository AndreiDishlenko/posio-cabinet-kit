# AdminKit — Architecture

Extracted from `posio.cabinet`'s `_admin` module: the parts of that admin
panel that are generic enough to reuse in a new Laravel + Inertia + Vue 3
project — multi-tenant accounts, per-account roles/permissions, a Settings
shell, a collapsible side-menu layout, and a small UI kit. Business logic
(documents, reports, POS-specific anything) deliberately stayed behind in
posio.cabinet — this package is a **shell**, not a product.

## Core design decision: no publish/copy for frontend

Most Laravel starter-kit packages (Breeze, Jetstream) work by copying stub
files into the host project at install time. That makes `composer update`
useless for the copied files — they immediately diverge from the package.

AdminKit does the opposite: Vue/SCSS source stays inside
`vendor/posio/admin-kit/resources/` and Vite compiles it **directly from
there** via an alias (`@admin-kit`) and `server.fs.allow`. Nothing gets
copied, so `composer update posio/admin-kit` is enough to pick up frontend
changes — there is nothing to merge.

Backend works the same way: the service provider calls
`loadMigrationsFrom()` / `loadRoutesFrom()` straight from `vendor/`. Only
`config/admin-kit.php` is ever published, because config is meant to be
owned and edited by the host.

## Directory layout

```
src/
  AdminKitServiceProvider.php   registers migrations/routes/config, commands
  Http/Controllers/             AccountController, SettingsController, DashboardController
  Http/Middleware/               SetPermissionTeam, ShareAdminKitData
  Models/Account.php             tenant model (name + settings jsonb, owner_id)
  Traits/                        HasAccount (User), HasSettings, HasCustomFields
  Repositories/AccountRepository.php
  Services/                      AccountService (role writes), MenuService (menu/tabs filtering)
  Console/Commands/              InstallCommand, SyncConfigCommand
database/
  migrations/                    accounts, user_has_accounts, users.settings
  seeders/AdminKitRolesSeeder.php  Account owner / Manager / Administrator / User + manage-account permission
routes/admin.php                 mounted automatically, prefix+name from config
config/admin-kit.php             user_model, menu[], settings_tabs[], roles
resources/js/
  layouts/                       AdminLayout, AdminHeader, SideMenu, AccountSwitcher
  pages/                         Dashboard.vue, Settings.vue + Settings/{Account,Users,Profile}Tab.vue
  components/ui/                 Table.vue, ModalForm.vue, CardTemplate.vue
  resolvePage.js                 override-aware Inertia page resolver
resources/scss/admin-kit.scss    --ak-* design tokens, .button/.card base classes
```

## Multi-tenancy model

One `Account` per tenant. A host `User` can own one account and be a guest
member of others. Membership (`user_has_accounts`) and **role** are
separate concerns: a role is a Spatie Permission assignment scoped by
`team_id = account_id` (global role *definitions*, per-account
*assignments*). `SetPermissionTeam` middleware sets the active team id from
`$user->currentAccount()` before any `can:`/`Gate` check runs — this must
stay early in the route group (see `routes/admin.php`).

Full write-up of the pattern (edge cases: revoked roles, owner protection,
root/superadmin bypass removal): see `docs/knowledge/account-multi-roles.md`
if you copied `.claude/context/` from this package, or the original
`account-multi-roles` module in posio.cabinet's own knowledge base.

## Host integration contract

AdminKit does **not** provide its own auth. It expects:

1. A `User` model (path configurable via `admin-kit.user_model`) with
   `HasAccount` + `HasSettings` + `HasCustomFields` traits added.
2. `spatie/laravel-permission` installed with `'teams' => true` in
   `config/permission.php` *before* its migrations run.
3. Vite alias `@admin-kit` → `vendor/posio/admin-kit/resources/js` (see
   `stubs/vite-alias-snippet.js`).
4. `resources/_admin/js/admin.js` wired through `resolveAdminKitPage()` (see
   `stubs/admin-entry.js.stub`).

`admin-kit:install` scaffolds/checks most of this and prints what's left.

## Extension surface (see EXTENDING.md for detail)

1. **Config-driven** (no file changes survive updates for free): menu
   items, settings tabs, assignable roles — all in `config/admin-kit.php`.
2. **Override folder** (escape hatch): `resources/_admin/overrides/<same
   path as under resources/js/>` — checked first by `resolvePage.js` for
   top-level pages. Deeper component overrides (e.g. just `SideMenu.vue`)
   require importing the package version and wrapping it, since only
   `pages/*` go through the override-aware resolver.
