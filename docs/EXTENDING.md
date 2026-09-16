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
(cabinet root), `after_login`, `after_verify`, `after_logout`. A value
starting with `/` or `http` is used as a plain address instead of a route name.

Point `home` and `after_login` at your own route to open the cabinet on your
own page. Registration has no landing key: a form-based sign-up always goes to
`verification.notice`, and the cabinet stays closed until the email is confirmed.

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

## Giving a page its own actions

Extra actions of a screen — "Export XLS", "Import", "Print" — are not a button
on the page. They go into the user panel (the burger menu in the header), above
Settings, so the same menu serves desktop and mobile and nothing competes with
the filters row for space.

A page passes them to the layout:

```html
<CabinetLayout :page_menu="page_menu">
```

```js
computed: {
    page_menu() {
        return [
            { name: 'Export XLS', icon: 'mdi:file-export', action: () => this.exportXls() },
            { name: 'Import',     icon: 'mdi:upload',      action: () => this.$refs.importCard.open() },
            { name: 'Docs',       icon: 'mdi:help',        href: 'https://example.test/docs' },
        ];
    },
},
```

One descriptor everywhere: `{ name, icon?, action | href, disabled?, in_burger? }`.
`name` is an English `$t` key; `action` is a closure, so `this.$refs` works;
`in_burger: false` keeps an item out of the panel.

A block nested inside the page — a tab, for instance — cannot pass a prop up to
the layout. It registers itself instead:

```js
import pageMenuMixin from '@/_admin/js/mixins/_pageMenuMixins.js';

export default {
    mixins: [pageMenuMixin],
    computed: {
        page_menu() { return [ /* ... */ ]; },
    },
}
```

The mixin needs exactly one thing from the component: a `page_menu` property.
The panel collects registered blocks when it opens, not reactively, and keeps
only the ones actually visible — tabs stay mounted after switching, so several
live `page_menu`s coexist and only the on-screen one is meant. Changing
`page_menu` while the panel is open will not redraw it; reopening re-reads
everything.

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

## Registration approval

On by default: each self-registered user waits until an administrator with the
system permission `sysper-users` follows the link from the approval letter. To
let registrations straight in, set in the host `.env`:

```dotenv
ONBOARDING_REGISTRATION_APPROVAL=false
```

(or `'registration_approval' => false` in a published `config/cabinet_onboarding.php`).
The letters are customized like the auth emails below: texts under
`registration_approval_request` / `registration_approved` in
`lang/vendor/cabinet-kit/{locale}/mail.php`, templates
`resources/views/vendor/cabinet-kit/mail/{registration-approval-request,registration-approved}.blade.php`.
The approval letter view also receives `$registeredUser`; `$user` there is the
administrator it is addressed to.

## Auth emails: texts and templates

The email confirmation and password reset letters are Laravel's stock
`VerifyEmail` / `ResetPassword` notifications, built by the package
(`toMailUsing`) from its own markdown views and `cabinet-kit::mail` texts. They
go out in the visitor's language: every cabinet route, guest auth routes
included, applies the user's `locale` setting, then the session, then the
`locale` cookie. The site name comes from Site settings.

Everything is customized from the host; nothing in `vendor/` is edited:

- **Texts** — create `lang/vendor/cabinet-kit/{locale}/mail.php` and list only
  the keys to change; the rest stay from the package:

  ```php
  // lang/vendor/cabinet-kit/uk/mail.php
  return [
      'verify_email' => [
          'intro' => 'Раді бачити вас у :site! Підтвердіть пошту, щоб почати.',
      ],
  ];
  ```

  A new language is the same file under its own locale (plus the locale in
  `cabinet-kit.translations.locales`).
- **Templates** — a file at
  `resources/views/vendor/cabinet-kit/mail/{layout,verify-email,reset-password}.blade.php`
  replaces the package one automatically. To use a view with another name, set
  `cabinet-kit.auth_mail.views.verify_email` / `.reset_password`. Views receive
  `$user`, `$siteName`, `$siteUrl`, `$actionUrl`, `$actionText`, and
  `$expireMinutes` for the reset letter.
- **Starting point** — `php artisan vendor:publish --tag=cabinet-kit-mail`
  copies both texts and templates into those host paths.
- **Whole letter** — call `VerifyEmail::toMailUsing()` /
  `ResetPassword::toMailUsing()` in the host `AppServiceProvider` (it boots
  after the package and wins), override `sendEmailVerificationNotification()` /
  `sendPasswordResetNotification()` on the `User` model, or set
  `cabinet-kit.auth_mail.enabled` to `false` to get Laravel's stock letters back.

The package also ships posio.cabinet's `lang/` files as a fallback layer: its
JSON strings load globally and its groups as `cabinet-kit::auth`,
`cabinet-kit::passwords` and so on. Password reset statuses fall back to
`cabinet-kit::passwords.*` when the host has no `passwords` translation in the
current language. Host `lang/{locale}.json` keys always win.

## Adding a new permission / role

1. Add the permission/role in your own migration or seeder (don't edit
   `src/Support/CabinetKitRoles.php` — it's vendor code and will be
   overwritten on update) with `Role::firstOrCreate(...)->givePermissionTo(...)`.
   The package's own roles and permissions are created on every
   `php artisan migrate`, and the sync never deletes or revokes anything, so
   host additions survive updates. An account role only needs to be named in
   `cabinet-kit.roles` to be created.
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
- Disable Google sign-in for one installation with
  `GOOGLE_AUTH_ENABLED=false` (or set
  `cabinet-kit.social_auth.google.enabled` directly). The button and its
  divider disappear from login/registration, and both Google endpoints answer
  404. Clear Laravel's config cache after changing the environment value.
- Change wording/layout of a page → override it under `pages/Auth/`.
- Change validation rules or add a field (e.g. a registration survey
  question) → this isn't config-driven; fork the relevant controller method
  into a host controller and repoint the route, or open an issue against
  this package if it's generic enough to belong here.
- Enforce email verification on specific host routes → add
  `implements MustVerifyEmail` to the host `User` model and the `verified`
  middleware to those routes yourself; CabinetKit deliberately doesn't
  assume this is wanted globally.

## The forced password change of seeded accounts

An account seeded by the installer (`cabinet-kit.system_users`) is held on
`pages/SystemPassword` until it replaces the password the config gave it (see
ARCHITECTURE for the mechanism). Three ways to bend it:

- **Hold host routes behind it too** — the package's own middleware covers
  package routes only. Add the `cabinet-kit.system-password` alias to your own
  route groups so a seeded account cannot slip into the host's pages either:

  ```php
  Route::middleware(['auth', 'cabinet-kit.system-password'])->group(...);
  ```

- **Reword or restyle the screen** — override `pages/SystemPassword` (and, if
  you want the form itself, keep the override's own copy of the card it
  imports). The POST target `cabinet-kit.system-password.update` stays the
  same; it only accepts `password` + `password_confirmation`.

- **Turn it off** — `'force_system_password_change' => false` in
  `config/cabinet-kit.php`. Correct when those passwords come from a secret
  manager or a provisioning script; wrong as a way to skip the prompt, since
  the seeded password is public knowledge in every install of this package.

## Wiring site settings and SEO into the host's public pages

Full instructions ship with the consumer project as `docs/cabinet-kit/` (source:
`docs/host/` here). Read those first — this section only lists the extension
points that belong to the package side.

| I want to… | Do this |
|---|---|
| Let a public Blade view follow the operator's brand | Add its view name to `cabinet-kit.site.views` (value `'main'`, `'cabinet'` or `null`) and print `$site_name` / `$site_favicon` / `$site_theme` in it |
| Use the logos in Vue | Read the `site` Inertia prop (`name`, `main.*`, `cabinet.*`); render both theme variants and hide one with CSS |
| Add a page to the SEO section | Give the route a name (no locale suffix) and add a record in the cabinet — no code |
| Change organization data, contacts, social profiles | `config/seo.php` |
| Add the main-navigation JSON-LD nodes | `seo.sitenav_routes` — base route names |
| Emit the product node (`SoftwareApplication`) | `seo.software.enabled` + `software_description` / `software_features` |
| Add breadcrumbs to a page | `app(BreadcrumbService::class)->add('home')->add('pricing')` in the controller |
| Put an AI behind the SEO card's "Generate" button | `cabinet-kit.seo.meta_generator` → class with `generate(array): array` |
| Turn either layer off | `cabinet-kit.site.share_prop` / `cabinet-kit.seo.share_prop` |

Do **not** write to `site_settings` or `seo_meta` from application code: the
first is cached as a whole set and its writes drop that cache, the second is
edited through the section built for it.

## Known gaps (intentionally out of scope)

- Additional social login providers, 2FA and magic links are not bundled;
  Google and Apple are the supported social providers.
- `Table.vue` is a minimal client-side sortable table — no server pagination,
  no row context menu, no soft-delete UI. For anything more, either build on
  top of it or port the fuller `Table.vue` from posio.cabinet's
  `resources/js/Elements/Table.vue` (~much larger, has its own conventions
  documented in that project's `.claude/context/modules/cabinet-reports-tables.md`
  and `project-tables.md`).
