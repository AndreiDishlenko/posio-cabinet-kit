# AdminKit — Extending

This file exists for whoever (human or AI) adds features to a project built
on AdminKit. Read `ARCHITECTURE.md` first if you haven't.

## Golden rule

**If it can be done through `config/admin-kit.php`, do it there — not by
overriding a Vue file.** Config changes survive `composer update` for free.
Overrides only survive because `resolvePage.js` checks them first; they
still require you to notice and manually reconcile if the package's own
version of that page changes shape (new required prop, etc.) in a later
release. Check `docs/CHANGELOG.md` in the new version before assuming an
old override still fits.

## Adding a menu item

Edit `config/admin-kit.php` → `menu`. Each group has a `label` and
`children: [{ id, label, icon, route|link, permission }]`. `permission: null`
means always visible; otherwise it's gated through
`$user->can($permission)` (`MenuService::menuFor()`).

To point a menu item at a page that isn't part of AdminKit at all, just give
it a normal host route name — `SideMenu.vue` doesn't care whose route it is.

## Adding a Settings tab

Edit `config/admin-kit.php` → `settings_tabs`, add
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

## Overriding a page

```
resources/_admin/overrides/pages/Dashboard.vue   →  replaces vendor's pages/Dashboard.vue
resources/_admin/overrides/pages/Settings.vue    →  replaces vendor's pages/Settings.vue
```

`resolveAdminKitPage()` matches by the Inertia render name's basename
(`pages/Dashboard` → looks for a file ending in `/Dashboard.vue` in the
overrides glob first). Copy the package file as your starting point so you
don't have to reverse-engineer its props.

## Overriding a layout piece (SideMenu, AdminHeader, ...)

These aren't resolved through `resolvePage.js` — only `pages/*` are. To
customize one:

```html
<script>
import AdminHeader from '@admin-kit/layouts/AdminHeader.vue';
export default {
  extends: AdminHeader,
  // override methods/computed, or just replace the template entirely by
  // not extending and writing your own component that mirrors the props
  // AdminLayout passes to <AdminHeader>.
};
</script>
```

Then swap the import inside your own overridden `pages/*.vue` (AdminLayout
itself isn't a page, so to change what layout a page uses, override that
page and import your own layout there instead of `@admin-kit/layouts/AdminLayout.vue`).

## Adding a new permission / role

1. Add the permission/role in your own seeder (don't edit
   `AdminKitRolesSeeder` — it's vendor code and will be overwritten on
   update). A host seeder like `database/seeders/AppRolesSeeder.php` that
   runs after `AdminKitRolesSeeder` and calls
   `Role::firstOrCreate(...)->givePermissionTo(...)` is the standard pattern.
2. Add the permission name to `config('admin-kit.roles.assignable_roles')`
   if it should be selectable in the Users tab role switcher (once you build
   one — the shipped `UsersTab.vue` only lists/removes members; role
   switching UI is a natural first override to build).

## Business-specific columns on Account

`accounts.settings` (jsonb) is the extension point — don't add migrations
that alter AdminKit's own `accounts` table's structural columns (`name`,
`owner_id`, `expire`) since a future package version might too and conflict.
Instead:

```php
$account->setCustomField('subscription_plan', 'pro');
$account->getCustomField('subscription_plan');
```

If you need first-class columns (indexed, foreign-keyed), create a
**separate** table (e.g. `account_subscriptions`) with `account_id` FK
rather than altering `accounts` directly.

## Known gaps (intentionally out of scope)

- No login/registration screens — bring your own auth.
- No account-creation UI — call
  `app(\Posio\AdminKit\Services\AccountService::class)->createAccount($name, $user)`
  yourself (e.g. from your registration flow).
- `Table.vue` is a minimal client-side sortable table — no server pagination,
  no row context menu, no soft-delete UI. For anything more, either build on
  top of it or port the fuller `Table.vue` from posio.cabinet's
  `resources/js/Elements/Table.vue` (~much larger, has its own conventions
  documented in that project's `.claude/context/modules/cabinet-reports-tables.md`
  and `project-tables.md`).
