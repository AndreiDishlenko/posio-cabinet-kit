# Changelog

## v0.3.24 — Modals rendered behind the page

**Fixed**
- Every modal (`ModalForm.vue` and everything built on `vue-final-modal`)
  rendered inline in normal document flow instead of as a fixed overlay, so
  it sat behind the side menu and page content no matter what `z-index` was
  set on it. The original bootstrap installs the library's Vue plugin and
  loads its stylesheet (`vfm--fixed`/`vfm--inset` come from there — that's
  what actually pins the overlay to the viewport); extraction dropped both.
  `createApp.js` now calls `app.use(createVfm())` and imports
  `vue-final-modal/style.css`, matching `admin.js` in the source project.

## v0.3.23 — Missing app globals (`$H`, `$dayjs`, pause state) restored

**Fixed**
- Every page that mounts a modal broke on render: `ModalForm.vue` reads
  `$modal_inprogress`, which no longer existed after extraction. The
  `pauseApplication.js` singleton is back (`$inprogress`,
  `$modal_inprogress`, `$pauseApplication`), driven by the app emitter's
  `pause_application` / `unpause_application` events instead of a
  module-level emitter singleton. `$inprogress` is a real ref now, so the
  layout/card spinners it feeds actually react.
- `Table.vue` threw `ReferenceError: $H is not defined` while building its
  select sources: the helper aggregator wasn't ported. `posio/helpers.js`
  now exists with the namespaces the package uses (`Ar`/`ar` from the
  ported `helpers/Arrays.js`, `Dt`/`dt` from the already-ported
  `helpers/Datetime.js`), registered as `$H`/`$h` and, as in the original,
  published on `window` — without clobbering a host's own `$H`.
- `$dayjs` was never registered although the extracted table cells, table
  sorting and notifications list format dates with it. `dayjs` was already
  in the installer's npm dependency list.
- Ziggy's own Vue plugin is no longer installed: it registers `route` as a
  global mixin, which shadowed the package's resolver — the legacy route
  aliases never applied inside templates — and its `provide('route')`
  produced an "App already provides property with key route" warning on
  every load. The package installs the resolver itself.

## v0.3.22 — Settings tabs back to their original shape + Tailwind theme in the preset

**Fixed**
- `tailwind-preset.cjs` only contributed a content glob, so every non-stock
  utility the package templates use silently produced nothing: `text-md`
  (13 usages, among them the language selector), `text-xxs`/`text-xxl`, the
  `xs:` and `lt-*` breakpoints (35 usages), `grid-rows-*`, and the
  `h-dvh-*`/`max-h-dvh-*` utilities that give `CabinetLayout` its height.
  The preset now carries the theme (font sizes bound to the `--text-*`
  variables, the extra screens, `darkMode: 'class'`) and the dynamic-viewport
  utility plugin, and its content glob also covers `resources/_admin/js`.
- Form validation rules were never registered in the package, so any form
  with `validationRules` would have thrown on submit — `vee-validator.js` is
  now part of the package and loaded by `createCabinetKitApp()`.
- `$popup.confirm_yn()` is provided (browser confirm by default); the
  extracted table/modal mixins already called it.

**Changed**
- `Settings.vue` renders through `Tabs.vue` (overflow menu, tab in the URL,
  remembered per account) instead of an ad-hoc button row. The tab list stays
  config-driven — each tab now receives only its own props.
- `ProfileTab.vue` is the full profile tab again: avatar with upload, name /
  phone / e-mail, old + new password, interface language, colour theme, sound
  notifications, and a save button that activates only on real changes.
- `AccountTab.vue` is a company-settings form (logo, name, description,
  address, phone, e-mail, URL) instead of a two-line read-only card.
- `UsersTab.vue` shows the owner separately, switches member roles through a
  dropdown, and invites by e-mail with client-side validation first.

**Added**
- `POST /account` (`cabinet-kit.account.update`) and `POST /account/logo`
  (`cabinet-kit.account.addlogo`). Company details are stored in
  `accounts.settings` (json) — no new columns on the shipped table.
- `Account::profile()` / `Account::fillProfile()`; `Account::info()` now
  returns those fields alongside `id`/`name`/`expire`.
- The sound-notification preference is persisted per user and shared as
  `user.play_notifications`, which `_Notifications.vue` already read.
- The system (root) user's profile is now read-only on the server too, not
  just in the form.

## v0.3.21 — route names in the shipped Vue layer

**Fixed**
- Ziggy's helper is now installed on the app *and* on the global scope, so the
  extracted mixins/components that call `route(...)` from plain module scope
  (table/modal-card mixins, the permissions matrix) stop failing with
  `route is not defined`.
- The old-name aliases (`cabinet.*`, `admin.*` → `cabinet-kit.*`) are applied
  through that helper instead of patching `window.route`, which was never set
  by Ziggy v2 — the alias table had no effect before. A name the host itself
  registered wins over the alias.
- `CabinetBurgerMenu.vue`, `SideMenu.vue`, `PermissionsMatrixTable.vue` and
  `UsersAdmin.vue` now reference the package's own route names, so the burger
  menu no longer throws `route 'cabinet.settings' is not in the route list`
  and tears down the page with it.
- `_Notifications.vue` resolves the host-only notification routes defensively:
  a missing route now warns instead of breaking the click.

**Removed**
- The placeholder dashboard: `DashboardController`, `pages/Dashboard.vue` and
  the `cabinet-kit.dashboard` route are gone — a landing page belongs to the
  host project, not to the shell. Hosts that linked to `cabinet-kit.dashboard`
  must register their own route (any name) and point `home_route` /
  `login_redirect_route` at it.
- The stale `cabinet-kit.dashboard` fallback in the auth redirects, which now
  falls back to `cabinet-kit.users` like the shipped config does.

## v0.4.0 - one-command host installation

**Added**
- `resources/vite/cabinet-kit.js` Vite plugin for the `@cabinet-kit` alias,
  vendor `fs.allow`, and optional local HTTPS/HMR.
- `tailwind-preset.cjs`, `resources/js/createApp.js`, built-in emitter, and
  `IsCabinetKitUser` so hosts wire one preset, one factory, and one trait.
- `cabinet-kit:doctor` with CI-friendly non-zero exit on failed checks.

**Changed**
- `cabinet-kit:install` now enables Spatie Permission teams before migrations,
  handles auth route conflicts through `auth_routes`, resolves/scaffolds the
  Vite entry, patches Vite/Tailwind/User with `.bak` backups, runs migrations,
  seeds roles, and then runs doctor.
- Default Vite entry is now `resources/_admin/js/cabinet.ts`.
- Password reset submit route name follows Laravel starter-kit convention:
  `password.store`.

**Removed**
- `mitt` is no longer required by host projects.
- The old `stubs/vite-alias-snippet.js` manual-instructions stub.

## v0.3.3 - sync maintenance + account member management polish

**Added**
- Windows-first maintenance workflow for porting generic shell improvements
  from `posio.cabinet`: `tools/sync-manifest.json`,
  `tools/Sync-CabinetKitFromPosio.ps1`, and root
  `CABINET_KIT_MAINTENANCE.md`.
- Package-local knowledge base under `.claude/context/` with the current
  package boundary and transfer decisions, so future AI passes can start from
  compact package facts instead of rereading the full host project context.
- `UsersTab.vue` can now invite an existing user by email, change member roles
  through `cabinet-kit.account.member.role`, and remove members with a native
  confirmation.

**Changed**
- `ShareCabinetKitData` now shares `currentPage.name` and
  `currentPage.section` from the permission-filtered menu, enabling generic
  header breadcrumbs.
- `CabinetHeader.vue` now renders `section / page` or `page / sub-section`
  breadcrumbs, while staying free of host-only widgets.
- `Settings.vue` now supports `?tab=...` deep links and keeps the URL in sync
  when the active tab changes.
- `AccountSwitcher.vue` links directly to the Profile tab.
- `SettingsController` passes member role names and assignable roles to the
  settings page.

**Fixed**
- `AccountController` now accepts invite by either `user_id` or `email`, blocks
  self role/removal operations, and `AccountService` validates invite roles
  against `config('cabinet-kit.roles.assignable_roles')`.

## v0.3.2 — complete the shipped stylesheet (auth forms + cabinet layout)

The single host-imported stylesheet was missing rules that can't live in a
component's scoped block, so a fresh install rendered the cabinet broken and
some form/icon styling never applied.

**Fixed**
- **Cabinet layout collapsed.** `CabinetLayout`'s root is `h-full`
  (height: 100%), but nothing established the `html → body → #app` full-height
  chain, so the layout fell back to content height while `SideMenu`
  (`h-[100dvh]`) stayed full-height — a visibly broken shell. Added the base
  height chain to the entry.
- **`body` never themed.** `--ck-background-color` / `--ck-text-color` were
  defined but never applied to `body`; the cabinet kept the browser's default
  background/text color. Now applied.
- **`.ck-icon` / `.ck-icon-sm` only existed in SideMenu's scoped styles**, so
  `CabinetHeader`, `AccountSwitcher`, `CardTemplate` and `Table` rendered
  icons at the default 1em. Promoted to a global `icons_cabinet-kit.scss`
  (plus `.ck-icon-lg`).
- `.ck-input` had no `:focus` state — added a brand-colored focus border.

**Changed**
- The monolithic `cabinet-kit.scss` was split by responsibility to match the
  host project's own scss layout: `colors_cabinet-kit.scss`,
  `buttons_cabinet-kit.scss`, `cards_cabinet-kit.scss`,
  `forms_cabinet-kit.scss`, `icons_cabinet-kit.scss`, with `cabinet-kit.scss`
  now the entry (layout tokens + base + `@use` of the partials). The import
  path hosts use (`cabinet-kit.scss`) is unchanged.

**Added**
- **Host style-override layer.** `cabinet-kit:install` now scaffolds
  `resources/_admin/scss/cabinet-kit-overrides.scss` (from a stub) and the
  cabinet Vite entry imports it *after* the package stylesheet, so a host can
  re-skin the kit — redefine `--ck-*` tokens or re-declare element classes —
  without touching (and without a merge conflict on `composer update`) any
  file under `vendor/`. The package's own scss stays a 1:1 mirror of upstream
  posio. See `docs/EXTENDING.md` → "Customizing styles".

## v0.3.0 — self-contained host integration (root view, Inertia page paths)

Driven by the first real third-party install (solut_new), where
`/cabinet/login` 500'd with `ComponentNotFoundException` and the frontend
had no way to render package pages at all.

**Fixed**
- `CabinetKitServiceProvider` now registers the package's `resources/js`
  (and the host's `resources/_admin/overrides`) into Inertia's server-side
  page paths — both `inertia.pages.paths` (inertia-laravel v3, used by
  `ensure_pages_exist` at runtime) and `inertia.testing.page_paths`
  (v1/v2 test assertions). Hosts with `ensure_pages_exist => true` no
  longer 500 on every CabinetKit page.
- `AccountController` was calling `$this->authorize()` without the
  `AuthorizesRequests` trait — every invite/setRole/remove request would
  have thrown "undefined method". Trait added.
- `Account::guestUsers()` hardcoded the `users.` table prefix; now respects
  `config('cabinet-kit.users_table')`.
- Light theme tokens in `cabinet-kit.scss` now double as `:root` defaults —
  the kit no longer renders unstyled when the host never sets an
  `html.light`/`html.dark` class.
- `CabinetLayout`'s `space_y` prop built the Tailwind class dynamically
  (`'space-y-'+n`), which Tailwind's scanner can't see; replaced with a
  static literal class map.

**Added**
- Package-owned Blade root view `cabinet-kit::app` (@routes + @vite +
  @inertia) and `UseCabinetKitRootView` middleware applied to the whole
  route group — the cabinet no longer piggybacks on the host's main app
  view. New config keys: `root_view`, `vite_entry`.
- `ShareCabinetKitData` shares a `currentPage` descriptor (matched from the
  current route name against the configured menu) unless the host already
  shares its own — SideMenu highlighting now works out of the box.
- `cabinet-kit:install` scaffolds `resources/_admin/js/admin.js` from the
  entry stub when missing; next-steps output now covers the Vite `input`
  entry, npm deps (ziggy-js, @iconify/vue, mitt) and the Tailwind `content`
  glob for vendor components.
- Entry stub registers `ZiggyVue` and a `mitt` event bus (`$emitter`) —
  both were silently required (route() calls, burger⇄menu events) but never
  wired anywhere.
- composer.json `suggest`: tightenco/ziggy.

## v0.2.0 — renamed to cabinet-kit + bundled auth (breaking)

**Rename** — `posio/admin-kit` → `posio/cabinet-kit` everywhere: composer
package name, PHP namespace (`Posio\AdminKit` → `Posio\CabinetKit`), config
file (`config/admin-kit.php` → `config/cabinet-kit.php`, all keys), route
file (`routes/admin.php` → `routes/cabinet.php`), default route prefix
(`admin` → `cabinet`) and route name prefix (`admin-kit.` → `cabinet-kit.`),
artisan commands (`admin-kit:install`/`admin-kit:sync-config` →
`cabinet-kit:install`/`cabinet-kit:sync-config`), Vite alias (`@admin-kit` →
`@cabinet-kit`), CSS token/class prefix (`--ak-*`/`ak-*` → `--ck-*`/`ck-*`),
renamed Vue layouts (`AdminLayout.vue` → `CabinetLayout.vue`,
`AdminHeader.vue` → `CabinetHeader.vue`), Inertia shared prop (`adminKitMenu`
→ `cabinetKitMenu`). Consumers must update their `composer.json`
(`repositories` URL + `require`), re-run `composer require posio/cabinet-kit`,
and rewire the Vite alias / `admin.js` entry per the updated `README.md`.

**Bundled auth** — login, registration (creates the `User` and its `Account`
together — registration now asks for a company name), logout, password
reset (`forgot-password`/`reset-password`, via Laravel's core `Password`
broker), and email verification routes/pages. `docs/ARCHITECTURE.md`'s
former "no bundled auth" contract is gone; see its "Auth" section for the
new one. New config key: `login_redirect_route`.

**Profile tab** — `Settings/ProfileTab.vue` is no longer a read-only stub;
it's a real profile info + change-password form, backed by the new
`ProfileController`.

## v0.1.0 — initial extraction

Extracted from posio.cabinet's `_admin` module as a standalone,
framework-agnostic (w.r.t. business logic) admin shell.

Included:
- Multi-tenant `Account` model + `user_has_accounts` guest membership
- Per-account roles via Spatie Permission teams (`SetPermissionTeam`,
  `AccountService`, `AdminKitRolesSeeder`)
- `AdminLayout` / `AdminHeader` / `SideMenu` / `AccountSwitcher` (Vue 3,
  Options API)
- `Settings` page shell with config-driven tabs (Account/Users/Profile)
- Minimal UI kit: `Table.vue`, `ModalForm.vue`, `CardTemplate.vue`
- Override-aware Inertia page resolver (`resolvePage.js`)
- `admin-kit:install` / `admin-kit:sync-config` artisan commands

Deliberately excluded (posio.cabinet-specific business logic, not "site
admin" scaffolding): Docs/Reports/Cashflow engine, Telegram bot assistant,
Checkbox/PRRO fiscalization, product tour, AI chat widget.
