# Changelog

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
