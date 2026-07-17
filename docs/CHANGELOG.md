# Changelog

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
