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

Two more import prefixes, `@/js` and `@/_admin`, are shared with the host: the
same folder names exist on both sides, so the plugin resolves them by the side
of the importing file — a file of the package gets the package's copy, a file of
the host gets the host's, and what is missing on one side is looked up on the
other. A host therefore keeps its own `resources/js/components` without
redeclaring the prefix, and the package never has to spell its internal imports
differently from the upstream cabinet they are ported from.

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
config/cabinet_onboarding.php     post-registration step switches (same keys as posio.cabinet), all off
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

Bundled (since v0.2.0): login, registration (creates the `User` only — see below), logout, password reset (Laravel's core
`Password` broker + the host's own mail config), and email verification
(routes exist when `cabinet-kit.auth_routes` is true; nothing actually *enforces* verification unless the
host's `User` implements `MustVerifyEmail` and adds the `verified`
middleware itself — that's a deliberate opt-in, not assumed).

Registration ends with the user. Every step after it is switched by
`config/cabinet_onboarding.php` — the same file name and keys as in
posio.cabinet, merged under the same config name so ported code reads it as is,
and shared to pages as the `onboarding` prop. The package ships with all steps off:

| key | step | in the package |
|---|---|---|
| `account_setup` | company setup (`/cabinet/init`: company, first point of sale, currency, welcome letter) | not shipped — POS logic |
| `product_tour` | tour over the cabinet menu | shipped (`ProductTour.vue`), gated in `CabinetLayout` |
| `spotlight_hints` | one-off highlights | shipped (`SpotlightHints.vue`), gated in `CabinetLayout` |
| `first_steps_checklist` | first-steps checklist dock | not shipped — POS steps |
| `first_receipt_congrats` | congratulation on the first receipt | shipped (`FirstReceiptCongrats.vue`); needs a backend that shares `first_receipt_congrats` |
| `team_notification` | new-user message to the service Telegram | not shipped |
| `user_milestones` | funnel milestones for user analytics | not shipped |

A host turns a shipped step on by publishing the file (`--tag=cabinet-kit-onboarding`)
or declaring the key in its own `config/cabinet_onboarding.php`. The tour's
built-in scenario points at posio.cabinet menu items, so a host passes its own.
A registered user has no account until invited; the settings page and the shared
page data already allow for that.

Social sign-in (Google, Apple) rides in the same guest group via
`SocialAuthController` + `UserRepository`. Availability is controlled by both
the `cabinet-kit.social_auth.<provider>.enabled` flag and credentials. The
service provider reads them from env and
bridges them into `config('services.*')` unless the host already declares them
there — `config/services.php` belongs to the host and this package publishes
nothing into it. Routes stay registered so Ziggy can resolve their names;
disabled or unconfigured providers disappear from the bundled forms and their
endpoints answer 404. Needs `laravel/socialite`, plus
`socialiteproviders/apple` for Apple; `cabinet-kit:doctor` flags credentials
without a driver. A person arriving through a provider is matched on
`google_id`/`apple_id` (added by the package's migration), falls back to
linking an existing row with the same email, and otherwise gets a new user
— with no account, as in form registration.

The provider fills missing nested `enabled` keys after Laravel's shallow
package-config merge. Therefore an already-installed project can set
`GOOGLE_AUTH_ENABLED=false` immediately after updating and clearing its config
cache; republishing or rewriting its owned `config/cabinet-kit.php` is not
required.

Route **names** for the auth group are Laravel's own unprefixed convention
(`login`, `register`, `logout`, `password.*`, `verification.*`) rather than
`cabinet-kit.*` — that's not a style choice, framework internals (the `auth`
middleware's redirect-to-login, `EmailVerificationRequest`) look those exact
names up. Only the *authenticated* users/settings/account route group
uses the `cabinet-kit.` name prefix.

## First sign-in of a seeded account

`cabinet-kit:install` seeds the accounts listed under
`cabinet-kit.system_users` with the password written next to them in the config
— the same one in every project built on this package. While an account still
holds that password, `RequireSystemPasswordChange` (last in the authenticated
cabinet group, after `ShareCabinetKitData`) answers every route of that group
with a redirect to `cabinet-kit.system-password`, which renders
`pages/SystemPassword`: `CabinetLayout` with the menu disabled and a modal
that cannot be closed or clicked away — the same shape posio.cabinet uses to
hold a user without an account on its initialization screen.

Nothing records the state: `SystemPasswordPolicy` compares the stored hash
against the configured password, so the gate lifts as soon as the password
differs and comes back if the configured one is set again — no flag, no column,
no migration, and nothing to reset when the config changes. Accounts absent
from `system_users` are never affected, and `force_system_password_change`
turns the whole thing off.

Like posio.cabinet's own gate, this one speaks only for the group it sits in —
package routes. A host that wants its own pages held the same way applies the
`cabinet-kit.system-password` middleware alias to them.

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
- **Inertia server-side page paths** — the service provider appends the host
  `resources/_admin/overrides` and **both** package page roots — `resources/js`
  (auth pages) and `resources/_admin/js` (settings, users, permissions, the
  system-password screen) — to `inertia.pages.paths` (v3) /
  `inertia.testing.page_paths` (v1/v2), so `ensure_pages_exist => true` and
  `assertInertia` both see package pages. A root registered on the client
  (the resolver globs both) but not here renders fine in the browser and 500s
  server-side with `ComponentNotFoundException`.
- **Client-side resolver** — `resolveCabinetKitPage()` in the host's
  cabinet entry: overrides glob first, package glob second.

## Site settings and SEO (the two layers that reach outside the cabinet)

Everything else in this package stops at the cabinet's own route group. These
two do not — they exist to change how the **host's public pages** look to a
visitor and to a crawler — so both are split into "what the package does by
itself" and "what the host has to print".

- **Storage.** `site_settings` (key → value, one row per setting, whole set
  cached forever and dropped on write) and `seo_meta` (one row per route name +
  optional locale, soft-deleted). Both are global, not per account: a site has
  one identity regardless of who is signed in.
- **Reading.** `SiteSettingsService` is the only door to the first table;
  `SeoService` + `JsonLdObject` + `BreadcrumbService` build the payload from the
  second. `imageUrl()` never answers empty — a missing upload falls back to a
  neutral placeholder served from the package (`/brand-assets/...`), which is
  why no consumer carries a fallback path of its own.
- **Delivery.** Two Inertia props shared globally, not only on cabinet routes:
  `site` (name + logo set) and `seo` (meta + JSON-LD). Both are lazy closures,
  both are wrapped so an unmigrated or unreachable database degrades to
  placeholders instead of a 500. Either can be switched off in config on a
  project that has no public site.
- **Auth layout.** The bundled authentication screens read the dark-theme main
  logo from the same `site` prop, so an uploaded public-site logo also replaces
  the package placeholder above login, registration and password forms.
- **Blade.** A view composer feeds `$site_name` / `$site_favicon` /
  `$site_theme` to the views listed in `cabinet-kit.site.views`. The cabinet's
  own root view is in that list by default and prints them; a host view has to
  be added there **and** print them — the package cannot write into templates it
  does not own.
- **Precedence.** Per-page SEO record → site settings → `config/seo.php`. The
  short brand (`WebSite.alternateName`, `og:site_name`) deliberately stays on
  the config value: it is an identity, not an editable label.
- **Nothing product-specific is hardcoded.** The main-navigation JSON-LD nodes
  come from `seo.sitenav_routes`; the `SoftwareApplication` node only exists
  when `seo.software.enabled` is on. The upstream project this was extracted
  from had both wired to its own routes and its own product.

Host-facing instructions for both live in `docs/host/` and are copied into the
consumer project as `docs/cabinet-kit/` during installation.

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
