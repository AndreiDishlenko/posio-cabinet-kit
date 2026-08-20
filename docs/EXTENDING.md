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

An item whose route name isn't registered is hidden instead of rendered — the
template resolves every item's address, and one unknown name would otherwise
take the whole page down. `cabinet-kit:doctor` lists what got hidden.

## Where the auth flow lands

`config/cabinet-kit-redirects.php` holds one route name per step: `home`
(cabinet root), `after_login`, `after_register`, `after_verify`,
`after_logout`. A value starting with `/` or `http` is used as a plain address
instead of a route name.

Point `home` and `after_login` at your own route to open the cabinet on your
own page. Point `after_register` at `verification.notice` to require email
confirmation before the cabinet opens.

A value naming a route the application doesn't register is ignored in favour of
the package default, so a page you later remove can't lock anyone out of
signing in. `cabinet-kit:doctor` reports those.

## Adding a Settings tab

The settings page (`resources/_admin/js/pages/CabinetSettings.vue`) builds its
tab strip in JS, not from config: `pages/Settings/settingsTabs.js` holds the
full catalogue (`{ id, label, label_mobile?, file, account_wide? }`) and keeps
only the tabs whose `file` actually exists next to it — the page globs
`./Settings/CabinetSettings*Tab.vue`. `account_wide: true` hides the tab from
anyone without `manage-members`. The same helper feeds the settings dropdown
in `SideMenu.vue`, so a tab added there shows up in both places.

The package ships one tab, `CabinetSettingsUserProfileTab.vue` (profile,
password, interface language, sound notifications). To add your own:

1. Copy the package's `CabinetSettings.vue` into
   `resources/_admin/overrides/pages/CabinetSettings.vue`; the glob and the
   `settingsTabs.js` import then resolve against *your* folder, so put your
   tab files and your own copy of `settingsTabs.js` beside it under
   `overrides/pages/Settings/`. Only top-level pages go through
   `resolvePage.js` — tab components are ordinary imports and are not
   override-resolved on their own.
2. Add the entry to your `settingsTabs.js` and hand the tab its props in the
   page's `tab_props()` computed, keyed by tab id.

Everything the settings controller shares (`profile`, `own_account`,
`account_users`, `assignable_roles`, `can_manage_members`,
`can_manage_account_users`, `is_owner`, `is_system_user`) is already declared
as a prop on the page, so widening `tab_props()` is a one-line change in your
override — no backend edit needed.

## Overriding a page

```
resources/_admin/overrides/pages/CabinetSettings.vue  →  replaces vendor's pages/CabinetSettings.vue
resources/_admin/overrides/pages/UsersAdmin.vue       →  replaces vendor's pages/UsersAdmin.vue
resources/_admin/overrides/pages/Auth/Login.vue       →  replaces vendor's pages/Auth/Login.vue
```

`resolveCabinetKitPage()` matches by the Inertia render name's basename
(`pages/CabinetSettings` → looks for a file ending in `/CabinetSettings.vue`
in the overrides glob first). Copy the package file as your starting point so
you don't have to reverse-engineer its props.

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
