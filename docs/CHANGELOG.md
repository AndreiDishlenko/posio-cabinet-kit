# Changelog

## Unreleased — Cabinet pages are found server-side, not just in the browser

**Fixed**
- `Inertia page component [pages/SystemPassword] not found` (500) right after
  signing in on a host with `inertia.pages.ensure_pages_exist => true`. The
  service provider registered only `resources/js` with Inertia's view finder,
  while the cabinet's own pages live in `resources/_admin/js` — so
  `pages/CabinetSettings`, `pages/UsersAdmin`, `pages/Permissions`,
  `pages/PermissionsAccount` and the new password screen were invisible to it,
  even though the client-side resolver globs both roots and renders them fine.
  Auth pages resolved, which is why sign-in itself worked and only the page it
  led to failed. Both roots are registered now.

## Unreleased — Seeded accounts must replace their installation password

**Added**
- A fresh install seeds `sa` / `admin` with a password that is written in
  `config/cabinet-kit.php` and therefore identical in every project built on
  this package. Such an account now signs in onto a single screen — the
  cabinet page `pages/SystemPassword`, a modal that cannot be closed, dismissed
  or navigated away from (`RequireSystemPasswordChange` sends every other route
  of the cabinet group back to it) — and reaches the rest of the cabinet only
  after setting a password of its own.
- The condition is read from the password hash itself (`Hash::check` against
  the configured one), so there is no flag, no column and no migration: the
  gate lifts the moment the password differs and returns if the configured one
  is set again. Accounts outside `cabinet-kit.system_users` never see it.
- `'force_system_password_change' => true` in `config/cabinet-kit.php` turns
  the gate off for hosts that manage those passwords elsewhere.
- The gate is also aliased as middleware `cabinet-kit.system-password`, so a
  host can hold its own route groups behind it — the package's own middleware
  only covers package routes, exactly like the account-initialization gate it
  is modelled on.

## Unreleased — Spatie tables already present in the host are adopted, not ignored

**Fixed**
- Installing into a project that already used Spatie Permission left it unable
  to sign in: `SQLSTATE[42S02] ... Table 'user_has_roles' doesn't exist`. The
  installer patches `config/permission.php` to the kit's pivot names, but the
  host's copy of Spatie's table migration had already run under the original
  ones, so `migrate` had nothing left to do and the tables kept the names
  nothing reads any more. A migration now renames `model_has_roles` /
  `model_has_permissions` and their `model_id` column to whatever the config
  asks for, when — and only when — the target tables are not there yet.
- The same ordering flaw silently skipped the `is_system` columns on `roles`
  and `permissions` in the opposite case: a project **without** Spatie got its
  permission tables created by the freshly published migration, which is dated
  the day of the install and therefore ran *after* the kit's own migrations.
  Both concerns now live in one migration dated far ahead, so it always runs
  once those tables exist. Projects that already applied
  `2024_01_01_000005_prepare_cabinet_kit_permissions` keep that row in their
  migrations table; the replacement is idempotent and simply finds nothing to
  do.
- `cabinet-kit:install` also checks Spatie's own table names when it refuses to
  install over permission tables built without teams — under the kit's names
  those tables do not exist yet, so a teamless schema used to pass the check
  and fail later, at the first role query.
- `cabinet-kit:doctor` names this case instead of asking for a migration run
  that had nothing to do: the tables are reported as still carrying their
  original names while the config points elsewhere.

**Note for affected projects**
- Seeding runs after migrations in the installer, so an install that hit this
  bug also has its system users without roles. Run `php artisan migrate` (which
  now renames the tables) and then re-run `php artisan cabinet-kit:install` —
  answering *no* to the user purge — to seed the roles that were lost.

## Unreleased — Host version constraint repaired instead of freezing the install

**Fixed**
- A host requiring `"posio/cabinet-kit": "0.3"` got 0.3.0 and never anything
  newer: a two-segment version is an exact version to composer (it normalizes
  to `0.3.0`), so every later tag of the line was invisible and
  `composer update` reported the project as current. `cabinet-kit:install`
  now widens such a constraint to `^0.3` while patching `composer.json`
  (together with the post-update hook, in one write), `cabinet-kit:sync-config`
  repairs it in projects installed before this release, and
  `cabinet-kit:doctor` fails on it with the fix in the hint. A full
  three-segment pin (`0.3.31`) is read as deliberate and left alone.
- README documents the `0.3` vs `^0.3` distinction in both the install notes
  and "Which version you get".

## Unreleased — One-step update launcher in the host project root

**Added**
- `cabinet-kit:install` now scaffolds `updcab.bat` in the host project root
  (from `stubs/updcab.bat.stub`). Running it performs the whole README
  "Update" procedure in order — `composer update posio/cabinet-kit`,
  `cabinet-kit:sync-config`, `migrate`, `optimize:clear`, `npm install`,
  `npm run build`, `cabinet-kit:doctor` — and aborts at the first failing
  step instead of running the rest over a broken state. An existing
  `updcab.bat` is never overwritten, so a host may adapt it. Windows only;
  other platforms follow the README steps by hand.

## Unreleased — Installer can wipe pre-existing users

**Added**
- `cabinet-kit:install` now offers to delete the users already in the database
  (with their accounts, account memberships and role assignments) right before
  seeding the system users. The prompt appears only when the users table is
  non-empty, and its default is **no** — the rows may belong to the host
  application and the deletion is irreversible. In production a second
  confirmation is required. `--purge-users` answers the prompt up front for
  scripted installs; under `--no-interaction` nothing is deleted unless that
  flag is passed.

## Unreleased — Old-browser flex-gap fallback actually wired up

**Fixed**
- `app.blade.php` never shipped the inline script that measures `row-gap`
  support in flex and sets `no-flex-gap` on `<html>` — every rule guarded by
  `html.no-flex-gap` (`_flexgap_shared.scss`, `buttons_shared.scss`,
  `uisizes.scss`, `SideMenu.vue`, ...) was unreachable dead code on every
  install. The script (and the matching Ziggy `window` duplication for
  WebKit < 14, and the pre-mount theme restore from `localStorage`) is now
  ported into `app.blade.php`, same as the source `cabinet.blade.php`.
- Restoring the saved theme was silently broken as a result: `applyDefaultTheme()`
  in `createApp.js` only ever saw a `<html>` with no theme class yet (nothing
  upstream had set one) and always fell back to `dark`, ignoring a `light`
  choice saved by `_ThemeSelector.vue` on the previous visit.
- `tailwind-preset.cjs` gained the `wrap-gap`/`wrap-gap-x`/`wrap-gap-y`
  utilities (margin fallback under `html.no-flex-gap`) — the plugin that
  generates them existed only in `posio.cabinet`'s own `tailwind.config.js`
  and was never carried into the package, so those classes compiled to
  nothing in every host. The `gap`/`gap-x`/`gap-y` core-utility override from
  the source plugin was deliberately left out: it needs `corePlugins.gap: false`
  on the host, which nothing in `cabinet-kit:install`/`:doctor` sets or checks,
  and enabling it silently would risk a duplicate-utility warning (or dropped
  arbitrary-value class) for any host already using plain `gap-*`.

## Unreleased — Auth flow landing pages moved to their own config file

**Breaking**
- `cabinet-kit.home_route` and `cabinet-kit.login_redirect_route` are gone.
  Landing pages now live in `config/cabinet-kit-redirects.php`, which
  `cabinet-kit:sync-config` creates in an already installed project, carrying
  the old values over. Until that file exists the old keys keep working, so
  updating alone changes nothing; once it exists the old keys are ignored and
  should be deleted from `config/cabinet-kit.php`.

**Added**
- `config/cabinet-kit-redirects.php` with one key per step of the auth flow:
  `home`, `after_login`, `after_register`, `after_verify`, `after_logout`.
  A value starting with `/` or `http` is used as an address instead of a
  route name, which is how signing out can leave the cabinet entirely.
- `Posio\CabinetKit\Support\CabinetRedirects` resolves those keys. A target
  naming a route the application does not register is ignored in favour of the
  package default: a stale name can no longer take the whole sign-in flow
  down, which is what a leftover `cabinet-kit.dashboard` used to do.
- `cabinet-kit:doctor` reports a missing redirects file and any landing page
  that resolves to nothing.

**Fixed**
- Signing in on a project installed before the dashboard page was removed no
  longer fails: `cabinet-kit.dashboard` left in the published config is
  detected and replaced with the package default.
- A menu item naming a route the application does not register is hidden
  instead of rendered — `SideMenu.vue` resolves every item's address inline, so
  one unknown name used to take the whole cabinet page down. `cabinet-kit:doctor`
  lists the hidden items.

## Unreleased — Real cabinet services instead of the placeholder ones

**Breaking**
- `$apiClient`, `$toast`, `$popup` and `$dictionaries` are now the services the
  source cabinet ships, not the placeholders of earlier versions. Host code
  that relied on placeholder behaviour must be checked: requests resolve with
  a `{statusCode, error, message, errors, data}` envelope instead of throwing
  on 4xx, `$popup.confirm_yn()` resolves `1`/`0` from a styled dialog instead
  of a boolean from `window.confirm()`, and `$dictionaries` no longer carries
  a hard-coded currency list.
- New npm dependencies: `sweetalert2`, `vue3-toastify`, `date-fns`. Run
  `php artisan cabinet-kit:sync-config` (or `cabinet-kit:install`) and
  `npm install` after updating.

**Added**
- `resources/js/posio/system/`: `AxiosApiClientClass.js`, `DictionariesClass.js`,
  `ToastMessages.js`, `Popup.js`, `ConsoleService.js`, `Emitter.js`, plus
  `classes/PropObjectClass.js` and the `posio/index.js` barrel. Validation
  errors now reach the form (`errors` on the response envelope), saving a
  dictionary row works (`$dictionaries.save()` / `.update()`), messages are
  visible toasts, and confirmations are the cabinet's own dialog.
- `resources/_admin/js/services/CabinetApiClient.js` — configures the api
  client for the cabinet (`/api/v1/`, cookie auth).
- `createCabinetKitApp()` options `dictionariesRoute` (route name or list of
  names for the host's dictionaries endpoint, default
  `cabinet-kit.api.dictionaries` then `cabinet.api.dictionaries`) and
  `dictionariesStorage` (local storage key, default `dict_cabinet`). The
  package owns no such endpoint: when no name resolves, dictionaries stay
  empty and the console says so instead of the app failing to start.
- `$settings` gained `getGlobalState` / `setGlobalState` / `mergeGlobalState` —
  the per-account slice shared across pages that the filter panel remembers
  the point of sale in.
- Components: `components/patterns/BlockList.vue` (responsive card grid with
  an add tile), `components/ui/Filters.vue` (filter panel above a list) and
  `components/ui/CategoryIconPicker.vue` (icon grid for editable categories).

## Unreleased — Package templates are actually scanned by Tailwind

**Fixed**
- Tailwind v3 does not merge `content` from presets: the resolved config keeps
  the first `content` it finds, which is always the host's own, so the globs in
  `tailwind-preset.cjs` never applied to a real host while the theme from the
  same preset did. Every class only the package templates use — `space-x-*` in
  `CabinetHeader.vue` being the visible one — compiled to nothing, with no
  error anywhere. The glob now belongs to the host config and is written there
  by the package.
- `cabinet-kit:install` adds `./vendor/posio/cabinet-kit/resources/**/*.{vue,js,ts}`
  to the host's `content` array (replacing narrower package globs a previous
  version told hosts to add), and no longer skips the whole Tailwind patch when
  the preset import is already present.
- One glob for all package resources instead of a per-folder list: moving
  templates between `resources/js` and `resources/_admin/js` can no longer
  leave a folder unscanned.

**Added**
- `cabinet-kit:sync-config` repairs the Tailwind content glob in place (with a
  `.bak` copy) next to the npm dependencies it already synced, and never fails
  the process it runs in.
- `cabinet-kit:install` registers `@php artisan cabinet-kit:sync-config --ansi`
  in the host's `composer.json` `post-update-cmd`, so wiring that lives inside
  host files is re-applied by `composer update` itself.
- `cabinet-kit:doctor` checks that the host `content` array covers the package,
  separately from the preset check — the preset alone never proved it.

**Upgrading**
- Run `php artisan cabinet-kit:sync-config` once after updating (it is
  automatic from the next update onwards), then rebuild assets. Drop the old
  `vendor/posio/cabinet-kit/resources/js/**/*.vue` glob if the command did not
  already replace it.

## Unreleased — Logs section taken over from the source cabinet

**Breaking**
- The `cabinet-kit.logs` route, `LogsController` and the `pages/Logs.vue`
  placeholder are gone. The Logs menu item now points straight at the
  bundled log viewer with a plain href (`/admin/log-viewer`), exactly as in
  `posio.cabinet`. A host that linked to `route('cabinet-kit.logs')` or
  overrode `pages/Logs.vue` has to drop it.

**Added**
- `opcodesio/log-viewer` is a hard dependency (`^3.22` — the source project
  constrains `^3.19` but runs 3.22, and the auth wiring here uses APIs only
  verified there) and is mounted by the package itself: the service provider
  writes `log-viewer.route_path` from the new
  `cabinet-kit.log_viewer.route_path` key (default `admin/log-viewer`) before
  the viewer's provider reads it, so nothing has to be published into
  `config/log-viewer.php`. A host that does publish that file owns the
  setting and CabinetKit keeps its hands off. Version 3.19+ serves its assets
  from the vendor directory, so there is no asset-publishing step either.
- Log-viewer access is gated by the `sysper-log-view` system permission —
  the same one the menu item is gated by — through `LogViewer::auth()`. A
  host that registers its own callback or a `viewLogViewer` gate wins.
- Migration repointing an existing `Logs` row in `admin_links` from the
  removed route to the viewer's href; the links seeder does the same before
  re-seeding so the item is not duplicated.
- `cabinet-kit:doctor` fails when the viewer is disabled or mounted at a path
  the Logs menu item does not point at — otherwise the only symptom is a
  404 behind the menu item.

## Unreleased — Settings page taken over from the source cabinet

**Breaking**
- The settings page is now `resources/_admin/js/pages/CabinetSettings.vue` —
  a verbatim copy of the `posio.cabinet` page — and the controller renders
  `pages/CabinetSettings` instead of `pages/Settings`. The generalized
  `pages/Settings.vue` and its `Settings/{Account,Users,Profile}Tab.vue` are
  gone. A host that overrode `pages/Settings.vue` must rename its override to
  `pages/CabinetSettings.vue` and re-check it against the new page.
- `config('cabinet-kit.settings_tabs')` and `MenuService::settingsTabsFor()`
  are removed. The tab strip is built in JS by
  `pages/Settings/settingsTabs.js`, which keeps only the tabs whose component
  file actually sits next to it — a config entry could never have added a tab
  on its own, and the two lists could drift apart silently.
- The active tab now travels in the `?settings=` query parameter (the tab
  group's storage key), not `?tab=`. `SideMenu.vue` and `AccountSwitcher.vue`
  link accordingly.
- `SettingsController` no longer passes `tabs`, `account`, `members`, `roles`,
  `can_manage_account`; the page reads `own_account`, `account_users`,
  `assignable_roles` and `can_manage_account_users` instead. The member list
  is now only built for users with `manage-members`, and the system (root)
  user is left out of it — as in the source.

**Note**
- The package currently ships one settings tab,
  `CabinetSettingsUserProfileTab.vue` (profile, password, interface language,
  sound notifications). Account settings and member management have no UI
  until their tabs are transferred; the routes behind them
  (`account.update`, `account.member.*`) stay registered.

## Unreleased — Google/Apple sign-in

**Added**
- `SocialAuthController` (ported from `posio.cabinet`) backs the
  `auth.google` / `auth.apple` routes already present in `routes/cabinet.php`:
  redirect, callback, provider-state mismatch handling and a
  `social-auth-failed` status back on the sign-in page. Logging goes through
  the framework logger instead of the host's app log.
- `UserRepository` with `findOrCreateGoogleUser()` / `findOrCreateAppleUser()`:
  match by provider id, link the provider onto an existing row with the same
  email (marking it verified), otherwise create the user with a random
  password hash and the visitor's current language. Provider ids are written
  past mass-assignment because the host owns the user model. A user created
  this way gets a first account named after them, as the form-based
  registration does with the company name.
- Migration adding nullable unique `google_id` / `apple_id` to the users
  table, skipped per column if the host already has it.
- `cabinet-kit.social_auth` config: Google/Apple credentials read from env and
  bridged into `config('services.*')` at boot unless the host declares them
  there, so nothing has to be published into `config/services.php`. The Apple
  Socialite provider is registered when `socialiteproviders/apple` is
  installed. A provider without a client id answers 404 — its routes stay
  registered so the sign-in page can resolve their URLs.
- `cabinet-kit:doctor` fails when a configured provider has no driver
  installed. `laravel/socialite` and `socialiteproviders/apple` are listed
  under composer `suggest`.

**Fixed**
- The sign-in, forgotten-password and verify-email pages read a `status` prop
  their controllers never passed, so no flash outcome (verification result,
  reset confirmation, failed social sign-in) ever reached them.

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
