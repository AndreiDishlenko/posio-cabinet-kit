# CabinetKit — Extending

This file exists for whoever (human or AI) adds features to a project built
on CabinetKit. Read `ARCHITECTURE.md` first if you haven't.

## Golden rule

**If it can be done through `config/cabinet-kit.php`, do it there — not by
overriding a Vue file.** Config changes survive `composer update` for free.
Overrides only survive because `resolvePage.js` checks them first; they
still require you to notice and manually reconcile if the package's own
version of that page changes shape (new required prop, etc.) in a later
release. Check `docs/CHANGELOG.md` in the new version before assuming an
old override still fits.

## Adding a menu item

Edit `config/cabinet-kit.php` → `menu`. Each group has a `label` and
`children: [{ id, label, icon, route|link, permission }]`. `permission: null`
means always visible; otherwise it's gated through
`$user->can($permission)` (`MenuService::menuFor()`).

To point a menu item at a page that isn't part of CabinetKit at all, just give
it a normal host route name — `SideMenu.vue` doesn't care whose route it is.

## Adding a Settings tab

Edit `config/cabinet-kit.php` → `settings_tabs`, add
`{ id, label, component, permission }`. `component` must match a key
`Settings.vue` knows — either one of the three shipped tabs
(`AccountTab`/`UsersTab`/`ProfileTab`) or a new one you register:

1. Create `resources/_admin/overrides/pages/Settings/YourTab.vue` (Options
   API, no `<script setup>`).
2. Register it in the host's own copy of the tab-component map — since
   `Settings.vue`'s `TAB_COMPONENTS` map is not itself override-resolved
   (only top-level pages are), the cleanest path is to override
   `pages/Settings.vue` wholesale once you need custom tabs: copy the
   package version into `overrides/pages/Settings.vue`, add your import to
   `TAB_COMPONENTS`, keep everything else identical.

Each shipped tab receives only the props it needs (`Settings.vue` →
`tabProps()`); a tab whose `component` isn't one of the three shipped names
gets the generic bag — `account`, `members`, `roles`, `can_manage_account` —
so a host tab written against an earlier version keeps working. The page
itself passes everything the controller shares, so widening that bag is a
one-line change in your override.

This is the same mechanism the bundled `ProfileTab.vue` uses (profile,
password, interface preferences) — it's a real, functional tab, not a stub,
precisely because "a base page with extensible tabs" is what `settings_tabs`
already is. Don't build a second, parallel tabs system for a similar need.

## Overriding a page

```
resources/_admin/overrides/pages/Settings.vue    →  replaces vendor's pages/Settings.vue
resources/_admin/overrides/pages/UsersAdmin.vue  →  replaces vendor's pages/UsersAdmin.vue
resources/_admin/overrides/pages/Auth/Login.vue  →  replaces vendor's pages/Auth/Login.vue
```

`resolveCabinetKitPage()` matches by the Inertia render name's basename
(`pages/Settings` → looks for a file ending in `/Settings.vue` in the
overrides glob first). Copy the package file as your starting point so you
don't have to reverse-engineer its props.

## Overriding a layout piece (SideMenu, CabinetHeader, AuthLayout, ...)

These aren't resolved through `resolvePage.js` — only `pages/*` are. To
customize one:

```html
<script>
import CabinetHeader from '@cabinet-kit/layouts/CabinetHeader.vue';
export default {
  extends: CabinetHeader,
  // override methods/computed, or just replace the template entirely by
  // not extending and writing your own component that mirrors the props
  // CabinetLayout passes to <CabinetHeader>.
};
</script>
```

Then swap the import inside your own overridden `pages/*.vue` (CabinetLayout
itself isn't a page, so to change what layout a page uses, override that
page and import your own layout there instead of `@cabinet-kit/layouts/CabinetLayout.vue`).

## Customizing styles

**Don't edit anything under `vendor/posio/cabinet-kit/resources/scss/`.** Those
files are kept a 1:1 mirror of the upstream posio project on purpose, so
`composer update` stays a clean fast-forward — a local edit there would be
overwritten (and would make every future update a manual merge).

Instead, write your changes in `resources/_admin/scss/cabinet-kit-overrides.scss`
(scaffolded by `cabinet-kit:install`). The cabinet Vite entry imports it
*after* the package stylesheet, so anything in it wins through normal CSS
cascade. This is the styling counterpart to the `overrides/` page folder.

Two levels:

1. **Redefine a `--ck-*` token** — reskins the whole kit at once, including
   scoped components (SideMenu, header, tables), because CSS custom properties
   pierce Vue's scoped styles. The color tokens are in
   `colors_cabinet-kit.scss`, the layout tokens in `cabinet-kit.scss`.

   ```scss
   :root, html.light { --ck-brand-bg: #16a34a; --ck-sidemenu-bg: #fff; }
   html.dark          { --ck-brand-bg: #22c55e; }
   :root              { --ck-expanded-width: 280px; }
   ```

2. **Re-declare an element's class** — for anything not behind a token
   (radius, spacing, shadow, transforms). Same specificity, loaded later, so
   it wins:

   ```scss
   .ck-card { border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
   .button.primary-button { text-transform: uppercase; }
   ```

If a host installed before this file existed, rerun `php artisan
cabinet-kit:install` or create it by hand and import it from
`resources/_admin/js/cabinet.ts` after `createCabinetKitApp`.

## Translating Vue text

CabinetKit registers the same global `$t()` helper its extracted Vue code
expects. Add Laravel JSON translation files in the host app:

```json
{
  "Settings": "Налаштування",
  "Log in": "Увійти"
}
```

The helper reads `lang/{app()->getLocale()}.json` and falls back to
`config('app.fallback_locale')`. Extra JSON directories can be configured in
`config/cabinet-kit.php` under `translations.json_paths`.

## Adding a new permission / role

1. Add the permission/role in your own seeder (don't edit
   `CabinetKitRolesSeeder` — it's vendor code and will be overwritten on
   update). A host seeder like `database/seeders/AppRolesSeeder.php` that
   runs after `CabinetKitRolesSeeder` and calls
   `Role::firstOrCreate(...)->givePermissionTo(...)` is the standard pattern.
2. Add the permission name to `config('cabinet-kit.roles.assignable_roles')`
   if it should be selectable in the Users tab role switcher (once you build
   one — the shipped `UsersTab.vue` only lists/removes members; role
   switching UI is a natural first override to build).

## Business-specific columns on Account

`accounts.settings` (jsonb) is the extension point — don't add migrations
that alter CabinetKit's own `accounts` table's structural columns (`name`,
`owner_id`, `expire`) since a future package version might too and conflict.
Instead:

```php
$account->setCustomField('subscription_plan', 'pro');
$account->getCustomField('subscription_plan');
```

If you need first-class columns (indexed, foreign-keyed), create a
**separate** table (e.g. `account_subscriptions`) with `account_id` FK
rather than altering `accounts` directly.

## Customizing auth beyond config

Login/register/reset/verify are real controllers + Vue pages (not a
third-party package's opaque routes), so the usual override mechanisms
apply directly:
- Change wording/layout of a page → override it under `pages/Auth/`.
- Change validation rules or add a field (e.g. a registration survey
  question) → this isn't config-driven; fork the relevant controller method
  into a host controller and repoint the route, or open an issue against
  this package if it's generic enough to belong here.
- Enforce email verification on specific host routes → add
  `implements MustVerifyEmail` to the host `User` model and the `verified`
  middleware to those routes yourself; CabinetKit deliberately doesn't
  assume this is wanted globally.

## Known gaps (intentionally out of scope)

- Social login providers, 2FA, magic links — bring your own if needed, the
  bundled auth is deliberately the plain email+password baseline.
- `Table.vue` is a minimal client-side sortable table — no server pagination,
  no row context menu, no soft-delete UI. For anything more, either build on
  top of it or port the fuller `Table.vue` from posio.cabinet's
  `resources/js/Elements/Table.vue` (~much larger, has its own conventions
  documented in that project's `.claude/context/modules/cabinet-reports-tables.md`
  and `project-tables.md`).
