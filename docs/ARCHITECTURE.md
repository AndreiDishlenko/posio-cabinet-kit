# CabinetKit — Architecture

Extracted from `posio.cabinet`'s `_admin` module: the parts of that admin
panel that are generic enough to reuse in a new Laravel + Inertia + Vue 3
project — multi-tenant accounts, per-account roles/permissions, bundled
auth, a Settings shell, a collapsible side-menu layout, and a small UI kit.
Business logic (documents, reports, POS-specific anything) deliberately
stayed behind in posio.cabinet — this package is a **shell**, not a product.

## Core design decision: no publish/copy for frontend

Most Laravel starter-kit packages (Breeze, Jetstream) work by copying stub
files into the host project at install time. That makes `composer update`
useless for the copied files — they immediately diverge from the package.

CabinetKit does the opposite: Vue/SCSS source stays inside
`vendor/posio/cabinet-kit/resources/` and Vite compiles it **directly from
there** via an alias (`@cabinet-kit`) and `server.fs.allow`. Nothing gets
copied, so `composer update posio/cabinet-kit` is enough to pick up frontend
changes — there is nothing to merge.

Backend works the same way: the service provider calls
`loadMigrationsFrom()` / `loadRoutesFrom()` straight from `vendor/`. Only
`config/cabinet-kit.php` is ever published, because config is meant to be
owned and edited by the host.

## Directory layout

```
src/
  CabinetKitServiceProvider.php   registers migrations/routes/config, commands
  Http/Controllers/               AccountController, SettingsController, DashboardController, ProfileController
  Http/Controllers/Auth/          LoginController, RegisterController, PasswordResetController, VerificationController
  Http/Middleware/                SetPermissionTeam, ShareCabinetKitData
  Models/Account.php              tenant model (name + settings jsonb, owner_id)
  Traits/                         HasAccount (User), HasSettings, HasCustomFields
  Repositories/AccountRepository.php
  Services/                       AccountService (role writes + account creation), MenuService (menu/tabs filtering)
  Console/Commands/               InstallCommand, SyncConfigCommand
database/
  migrations/                     accounts, user_has_accounts, users.settings
  seeders/CabinetKitRolesSeeder.php  Account owner / Manager / Administrator / User + manage-account permission
routes/cabinet.php                mounted automatically, prefix+name from config
config/cabinet-kit.php            user_model, menu[], settings_tabs[], roles, login_redirect_route
resources/js/
  layouts/                        CabinetLayout, CabinetHeader, SideMenu, AccountSwitcher, AuthLayout
  pages/                          Dashboard.vue, Settings.vue + Settings/{Account,Users,Profile}Tab.vue
  pages/Auth/                     Login, Register, ForgotPassword, ResetPassword, VerifyEmail
  components/ui/                 Table.vue, ModalForm.vue, CardTemplate.vue
  resolvePage.js                  override-aware Inertia page resolver
resources/scss/cabinet-kit.scss   --ck-* design tokens, .button/.card base classes
```

## Multi-tenancy model

One `Account` per tenant. A host `User` can own one account and be a guest
member of others. Membership (`user_has_accounts`) and **role** are
separate concerns: a role is a Spatie Permission assignment scoped by
`team_id = account_id` (global role *definitions*, per-account
*assignments*). `SetPermissionTeam` middleware sets the active team id from
`$user->currentAccount()` before any `can:`/`Gate` check runs — this must
stay early in the route group (see `routes/cabinet.php`).

Full write-up of the pattern (edge cases: revoked roles, owner protection,
root/superadmin bypass removal): see `docs/knowledge/account-multi-roles.md`
if you copied `.claude/context/` from this package, or the original
`account-multi-roles` module in posio.cabinet's own knowledge base.

## Auth

Bundled (since v0.2.0): login, registration (creates the `User` *and* its
`Account` in one step — a "Company name" field on the register form calls
`AccountService::createAccount()`), logout, password reset (Laravel's core
`Password` broker + the host's own mail config), and email verification
(routes always exist; nothing actually *enforces* verification unless the
host's `User` implements `MustVerifyEmail` and adds the `verified`
middleware itself — that's a deliberate opt-in, not assumed).

Route **names** for the auth group are Laravel's own unprefixed convention
(`login`, `register`, `logout`, `password.*`, `verification.*`) rather than
`cabinet-kit.*` — that's not a style choice, framework internals (the `auth`
middleware's redirect-to-login, `EmailVerificationRequest`) look those exact
names up. Only the *authenticated* dashboard/settings/account route group
uses the `cabinet-kit.` name prefix.

## Rendering pipeline (who owns which layer)

- **Blade root view** — the package's own `cabinet-kit::app`
  (`resources/views/app.blade.php`), applied to every CabinetKit route by
  `UseCabinetKitRootView` middleware. It prints `@routes` (Ziggy),
  `@vite(config('cabinet-kit.vite_entry'))` and `@inertia`. Swap the whole
  view via `config('cabinet-kit.root_view')` if the host needs its own
  HTML shell.
- **Inertia server-side page paths** — the service provider appends the
  package `resources/js` and the host `resources/_admin/overrides` to
  `inertia.pages.paths` (v3) / `inertia.testing.page_paths` (v1/v2), so
  `ensure_pages_exist => true` and `assertInertia` both see package pages.
- **Client-side resolver** — `resolveCabinetKitPage()` in the host's
  cabinet entry: overrides glob first, package glob second.

## Host integration contract

CabinetKit provides its own auth (see above). It still expects:

1. A `User` model (path configurable via `cabinet-kit.user_model`) with
   `HasAccount` + `HasSettings` + `HasCustomFields` traits added, plus the
   standard `password` / `email_verified_at` columns Laravel's own `users`
   migration already creates.
2. `spatie/laravel-permission` installed with `'teams' => true` in
   `config/permission.php` *before* its migrations run.
3. `tightenco/ziggy` installed (composer) + `ziggy-js` (npm) — every URL in
   CabinetKit Vue pages goes through `route()`.
4. Vite: alias `@cabinet-kit` → `vendor/posio/cabinet-kit/resources/js`,
   `server.fs.allow` for `vendor/posio/cabinet-kit`, and the cabinet entry
   (`config('cabinet-kit.vite_entry')`, default
   `resources/_admin/js/admin.js`) added to the laravel-vite-plugin `input`
   array (see `stubs/vite-alias-snippet.js`).
5. `resources/_admin/js/admin.js` wired through `resolveCabinetKitPage()` —
   `cabinet-kit:install` scaffolds it from `stubs/cabinet-entry.js.stub`
   when missing. The stub also registers `ZiggyVue` and a `mitt` bus as
   `$emitter` (mobile burger ⇄ SideMenu events). npm deps: `vue`,
   `@inertiajs/vue3`, `ziggy-js`, `@iconify/vue`, `mitt`.
6. Tailwind `content` glob covering
   `./vendor/posio/cabinet-kit/resources/js/**/*.vue` — package templates
   use utility classes (flex/gap/p-*), which otherwise never get generated.
7. (Optional) `implements MustVerifyEmail` on the host's `User` model if
   email verification should actually be enforced elsewhere — the
   verify/resend routes work regardless, they just don't block anything on
   their own.

`cabinet-kit:install` scaffolds/checks most of this and prints what's left.

## Extension surface (see EXTENDING.md for detail)

1. **Config-driven** (no file changes survive updates for free): menu
   items, settings tabs, assignable roles, login redirect — all in
   `config/cabinet-kit.php`.
2. **Override folder** (escape hatch): `resources/_admin/overrides/<same
   path as under resources/js/>` — checked first by `resolvePage.js` for
   top-level pages. Deeper component overrides (e.g. just `SideMenu.vue`)
   require importing the package version and wrapping it, since only
   `pages/*` go through the override-aware resolver.
