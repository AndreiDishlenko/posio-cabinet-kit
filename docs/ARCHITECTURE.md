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
  Http/Controllers/               AccountController, SettingsController, ProfileController
  Http/Controllers/Auth/          LoginController, RegisterController, PasswordResetController, VerificationController
  Http/Middleware/                SetPermissionTeam, ShareCabinetKitData
  Models/Account.php              tenant model (name + settings jsonb, owner_id)
  Traits/                         IsCabinetKitUser (User), HasAccount, HasSettings, HasCustomFields
  Repositories/AccountRepository.php
  Services/                       AccountService (role writes + account creation), MenuService (menu filtering)
  Support/CabinetRedirects.php    auth flow landing pages, with a fallback for targets that resolve to nothing
  Console/Commands/               InstallCommand, DoctorCommand, SyncConfigCommand
database/
  migrations/                     accounts, user_has_accounts, users.settings
  seeders/CabinetKitRolesSeeder.php  Account owner / Manager / Administrator / User + manage-account permission
routes/cabinet.php                mounted automatically, prefix+name from config
config/cabinet-kit.php            user_model, menu[], roles, route prefixes
config/cabinet-kit-redirects.php  home, after_login, after_register, after_verify, after_logout
resources/js/
  layouts/                        CabinetLayout, CabinetHeader, SideMenu, AccountSwitcher, AuthLayout
  pages/Auth/                     Login, Register, ForgotPassword, ResetPassword, VerifyEmail
  components/ui/                 Table.vue, ModalForm.vue, CardTemplate.vue
  resolvePage.js                  override-aware Inertia page resolver
resources/_admin/js/
  layouts/                        CabinetLayout, CabinetHeader, SideMenu (own copy for the _admin pages)
  pages/                          CabinetSettings.vue + Settings/{settingsTabs.js, CabinetSettings*Tab.vue},
                                  UsersAdmin, Permissions, PermissionsAccount
resources/scss/                   cabinet-kit.scss entry (tokens + base + @use) →
                                  colors/buttons/cards/forms/icons_cabinet-kit.scss partials
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
(routes exist when `cabinet-kit.auth_routes` is true; nothing actually *enforces* verification unless the
host's `User` implements `MustVerifyEmail` and adds the `verified`
middleware itself — that's a deliberate opt-in, not assumed).

Social sign-in (Google, Apple) rides in the same guest group via
`SocialAuthController` + `UserRepository`. It is opt-in by credentials, not by
a flag: `cabinet-kit.social_auth` reads them from env and the service provider
bridges them into `config('services.*')` unless the host already declares them
there — `config/services.php` belongs to the host and this package publishes
nothing into it. Routes register regardless of credentials (so the sign-in page
can resolve `route('auth.google')` unconditionally) and an unconfigured
provider answers 404. Needs `laravel/socialite`, plus
`socialiteproviders/apple` for Apple; `cabinet-kit:doctor` flags credentials
without a driver. A person arriving through a provider is matched on
`google_id`/`apple_id` (added by the package's migration), falls back to
linking an existing row with the same email, and otherwise gets a new user
with a first account named after them.

Route **names** for the auth group are Laravel's own unprefixed convention
(`login`, `register`, `logout`, `password.*`, `verification.*`) rather than
`cabinet-kit.*` — that's not a style choice, framework internals (the `auth`
middleware's redirect-to-login, `EmailVerificationRequest`) look those exact
names up. Only the *authenticated* users/settings/account route group
uses the `cabinet-kit.` name prefix.

## Logs

The one section of the cabinet that is **not** an Inertia page: reading the
application log is `opcodesio/log-viewer`, mounted by the package's service
provider at `cabinet-kit.log_viewer.route_path` (default `admin/log-viewer`,
deliberately outside the cabinet's own route prefix — the viewer registers a
catch-all under its path). The path is written into the viewer's runtime
config during `register()`, before its provider reads it, because only
`config/cabinet-kit.php` is ever published; a host that publishes
`config/log-viewer.php` takes the setting over and CabinetKit stands down.

Access is the `sysper-log-view` system permission, checked through
`LogViewer::auth()` — the same permission that gates the menu item, granted
to `SAdmin` and to `System administrator` by the roles seeder. A host that
registers its own callback or a `viewLogViewer` gate keeps it.

Because it is a plain page, the menu item carries `link`, not `route`:
`SideMenu.vue` renders `link` items as a bare `<a>`, since an Inertia visit
would pull the page into the modal frame and the viewer would then resolve
its own API base path against the wrong URL. This is also why the item never
highlights as the current page — current-page matching goes by route name.

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
   the `IsCabinetKitUser` trait added, plus the
   standard `password` / `email_verified_at` columns Laravel's own `users`
   migration already creates.
2. `spatie/laravel-permission` installed with `'teams' => true` in
   `config/permission.php` *before* its migrations run.
3. `tightenco/ziggy` installed (composer) + `ziggy-js` (npm) — every URL in
   CabinetKit Vue pages goes through `route()`.
4. Vite: the cabinet entry (`config('cabinet-kit.vite_entry')`, default
   `resources/_admin/js/cabinet.ts`) must be in the laravel-vite-plugin
   `input` array, and `cabinetKit()` from
   `vendor/posio/cabinet-kit/resources/vite/cabinet-kit.js` must be in
   `plugins`. That plugin provides the alias, `server.fs.allow`, and optional
   HTTPS/HMR config.
5. `resources/_admin/js/cabinet.ts` uses `createCabinetKitApp()` from the
   package. The factory registers `ZiggyVue`, the package page resolver,
   package styles, the built-in `$emitter` bus, and the global `$t()`
   translation helper fed by Laravel JSON translations shared through
   Inertia. It also wires the cabinet services every ported page and mixin
   expects: `$apiClient` (axios client answering with the
   `{statusCode, error, message, errors, data}` envelope), `$toast`,
   `$popup`, `$settings` and `$dictionaries`. The dictionaries endpoint
   itself belongs to the host — name it with the `dictionariesRoute` option
   when it is not `cabinet-kit.api.dictionaries` or
   `cabinet.api.dictionaries`; without it dictionaries simply stay empty.
6. Tailwind config uses `vendor/posio/cabinet-kit/tailwind-preset.cjs`, which
   contributes the theme the package templates are written against: font sizes
   bound to the `--text-*` variables (incl. the non-stock `md`/`xxs`/`xxl`
   steps), the `xs` and `lt-*` breakpoints, class-based dark mode, and the
   `h-dvh-*`/`max-h-dvh-*` utilities. Without the preset those classes compile
   to nothing and the cabinet loses its typography and its height.
   The **content glob** cannot come from the preset: Tailwind v3 keeps the
   first `content` it resolves — the host's own — and silently ignores the
   preset's. So `./vendor/posio/cabinet-kit/resources/**/*.{vue,js,ts}` has to
   sit in the host's `content` array; `cabinet-kit:install` writes it there,
   `cabinet-kit:sync-config` keeps it current, and `cabinet-kit:doctor` checks
   it. Miss it and every class only the package uses compiles to nothing,
   while the preset's theme keeps working — which is why the failure looks
   like a handful of broken layouts rather than a missing preset.
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
