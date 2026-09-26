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
(cabinet root), `after_login`, `after_verify`, `after_logout`, plus `profile` —
the page every closed target falls back to. A value starting with `/` or `http`
is used as a plain address instead of a route name.

Point `home` and `after_login` at your own route to open the cabinet on your
own page. Registration has no landing key: a form-based sign-up always goes to
`verification.notice`, and the cabinet stays closed until the email is confirmed.

A value naming a route the application doesn't register is ignored in favour of
the package default, so a page you later remove can't lock anyone out of
signing in. `cabinet-kit:doctor` reports those.

The target of each step — the page the user was heading to, or the one named
here — opens only if the user may open it: every permission gate on its route
(`Support\PermissionGate`, e.g. `CanSystemPermission`) is asked in advance.
A missing or closed target lands on `profile`, and so does any cabinet page
denied for lack of permission (a data request still gets 403). Give your own
permission middleware the same interface so its pages follow the rule:

```php
use Posio\CabinetKit\Support\PermissionGate;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CanCatalog implements PermissionGate
{
    public function handle($request, $next, string $permission)
    {
        if (! static::allows($request->user(), $permission)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }

    public static function allows($user, string $parameters): bool
    {
        return (bool) $user?->canAccount($parameters);
    }
}
```

## Adding a Settings tab

The settings page (`resources/_admin/js/pages/CabinetSettings.vue`) builds its
tab strip in JS, not from config: `pages/Settings/settingsTabs.js` holds the
full catalogue (`{ id, label, label_mobile?, file, account_wide? }`) and keeps
only the tabs whose `file` actually exists next to it — it globs
`./CabinetSettings*Tab.vue` lazily, and the page loads each tab through
`settingsTabLoader(file)` from the same glob, so every tab is its own chunk.
Do not import a tab file statically anywhere else: that merges it back into the
main bundle (Vite warns about it). `account_wide: true` hides the tab from
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

## Registration modes

Self-registration (the sign-up form and a first Google/Apple sign-in of an
unknown person) has three modes, set in the host `.env`:

```dotenv
CABINET_REGISTRATION=closed    # default: no sign-up, administrators create users
CABINET_REGISTRATION=approval  # sign-up, then wait for an administrator's approval
CABINET_REGISTRATION=open      # sign-up straight into the cabinet
```

(or `'registration'` in a published `config/cabinet-kit.php`). An unknown value
counts as `closed`. When closed, the sign-in page shows only e-mail and password:
no "Sign up" link (shared Inertia prop `registration_open`) and no social buttons.
The registration form, Google/Apple sign-in (for existing users too — a first
provider sign-in creates an account) and the approval link answer 404
(`RequireRegistrationNotClosed`); the route names stay registered.

### Registration approval

In `approval` mode each self-registered user waits until an administrator with
the system permission `sysper-users` follows the link from the approval letter.

By default the approval letter goes to every holder of `sysper-users`. To send it
to specific addresses instead, list them comma-separated:

```dotenv
ONBOARDING_REGISTRATION_APPROVAL_EMAILS=owner@example.com,ops@example.com
```

An address that belongs to a cabinet user gets the letter in that user's
language. Following the link still requires signing in with `sysper-users`, so
point it at administrators' own addresses.
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

## Writing a module (catalog, news, requests…)

A module is a separate composer package that adds a section to the cabinet —
its own tables, pages, menu group and permissions — without the host copying
anything. The host installs it with `composer require`; the kit finds it by
itself. Since 0.4.

### 1. Declare it in the module's `composer.json`

```json
"extra": {
    "laravel": { "providers": ["Posio\\CatalogKit\\CatalogKitServiceProvider"] },
    "cabinet-kit": {
        "module": "catalog",
        "admin": "resources/admin",
        "alias": "@catalog-kit",
        "npm": { "swiper": "^11.0.0" }
    }
}
```

| key | meaning |
|---|---|
| `module` | short name; also the folder its cabinet pages live in |
| `admin` | folder holding `pages/<module>/*.vue` — the cabinet pages |
| `alias` | import prefix for the module's `resources/` (default `@` + package basename) |
| `npm` | npm packages its sources import; `sync-config` adds them to the host |

The kit reads `vendor/composer/installed.json` on both sides — the Laravel
provider and the Vite plugin — so a page exists on the server and in the bundle
together, or nowhere. From that declaration alone:

- **Pages.** `Inertia::render('pages/catalog/Products')` resolves: host
  override (`resources/_admin/overrides/pages/catalog/Products.vue`) → module →
  kit. The kit's Vite plugin feeds module pages into the cabinet entry through
  `virtual:cabinet-kit-modules`; the host entry is not edited. Server-side,
  the `admin` folder joins `inertia.pages.paths`. Pages outside
  `pages/<module>/` collide with the kit's and the host's names —
  `cabinet-kit:doctor` fails on them.
- **Imports.** `@catalog-kit/...` points at the module's `resources/`. The
  shared prefixes `@/js`, `@/_admin`, `@cabinet-kit` work as in the host.
- **Tailwind and npm.** `sync-config` adds
  `./vendor/<package>/resources/**/*.{vue,js,ts}` to the host `content` and the
  `npm` packages to `package.json`; `doctor` checks both.

### 2. Plug in from the module's service provider

```php
use Posio\CabinetKit\Facades\CabinetKit;
use Posio\CabinetKit\Http\Middleware\CanSystemPermission;

public function boot(): void
{
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

    CabinetKit::systemPermissions(['sysper-catalog']);
    CabinetKit::translations(__DIR__.'/../lang');

    // Prefix, root view and the whole authenticated cabinet stack
    // (verified email, approval, team, shared data, seeded-password gate).
    CabinetKit::cabinetRoutes(function () {
        Route::middleware(CanSystemPermission::class.':sysper-catalog')
            ->name('catalog.')
            ->group(function () {
                Route::get('catalog/products', [ProductsPageController::class, 'index'])->name('products');
            });
    });

    // Same stack under {prefix}/api, without the page shell — for JSON.
    CabinetKit::apiRoutes(function () { /* ... */ });

    CabinetKit::dictionaries('catalog', fn () => ['catalog_categories' => /* ... */]);
    CabinetKit::sitemap(fn (\Spatie\Sitemap\Sitemap $sitemap, array $locales) => /* $sitemap->add(...) */ null);
    CabinetKit::doctor('catalog', fn () => [[$ok, 'What was checked', 'What to do']]);
    CabinetKit::syncConfig('catalog', fn (\Illuminate\Console\Command $command) => /* ... */ null);
}
```

| call | what the kit does with it |
|---|---|
| `cabinetRoutes` / `apiRoutes` | registers the group under the cabinet prefix and stack; skipped when routes are cached (they are in the cache already). Never copy the middleware list — `CabinetKit::cabinetMiddleware()` returns it when a host needs it |
| `systemPermissions` | creates them in the same post-`migrate` sync as the kit's own, marks them system, and grants them to `SAdmin` and `System administrator` when created. Never deletes |
| `dictionaries` | merged into `cabinet-kit.api.dictionaries`, the endpoint the cabinet's `$dictionaries` already calls. A host that had its own `cabinet.api.dictionaries` keeps it: that answer is merged in and wins on equal names |
| `translations` | a folder of `{locale}.json` for `$t()` in the cabinet; host keys win |
| `sitemap` | called by `sitemap:generate` after the SEO records, with the sitemap object and the site locales |
| `menu` | a group in `cabinet-kit.menu` format — used only by hosts whose menu comes from config, not from `admin_links` |
| `doctor` / `syncConfig` | extra checks and repair steps, run under the module's heading |

### 3. Menu: a migration of the module

```php
use Posio\CabinetKit\Support\ModuleMenu;

public function up(): void
{
    ModuleMenu::install('Catalog', [
        ['name' => 'Products', 'icon' => 'mdi:package-variant', 'route' => 'catalog.products'],
    ], 'sysper-catalog');
}

public function down(): void
{
    ModuleMenu::uninstall('Catalog', ['catalog.products']);
}
```

The group lands after everything already in the menu; rerunning adds nothing
twice. After `composer remove` its items disappear by themselves — an item
whose route is not registered is hidden — but `down()` is what removes the
rows.

### 4. SEO of a module's public pages

A product page has one route for all products, so a record in the SEO section
cannot describe each of them. The controller sets the meta for the request:

```php
app(\Posio\CabinetKit\Services\SeoService::class)
    ->override([
        'meta_title' => $product->name,
        'meta_description' => $product->summary,
        'og_image' => $product->cover,
        'index' => true,
    ])
    ->addJsonLd(['@type' => 'Product', 'name' => $product->name /* ... */]);
```

Keys are the SEO record's own; they win over the record for that request only.
Extra nodes are appended to the page `@graph`.

### 5. Installing into a host

A module needs `posio/cabinet-kit ^0.4`. Its own install command raises a lower
`^0.x` constraint with `HostComposerJson::raiseCaretConstraint('posio/cabinet-kit', '^0.4')`.
Updates go through `updcab`, which updates every `posio/*` package at once.

## Adopting the package in a project with its own cabinet

A project whose cabinet predates the package (the package was extracted from
it) already has the schema, roles, routes, error pages and console commands.
It uses the package as a library of shared code and turns off everything the
provider would otherwise do to the whole application:

```php
// config/cabinet-kit.php of such a host
'account_model' => \App\Models\Account::class,
'host_integration' => [
    'load_migrations'     => false, // its schema is already there
    'load_routes'         => false, // its routes keep their URLs and names
    'sync_roles'          => false, // its roles are its own data
    'exception_redirects' => false, // it registers the same handlers itself
    'share_auth_props'    => false, // its Inertia middleware shares them
    'json_translations'   => false, // or true, with app_translations below
    'site_commands'       => false, // it has its own sitemap:generate
],
// Its lang/ files equal the package's: take them from the package instead.
'app_translations' => true,
// Behavior of the cabinet the package was extracted from, where it differs:
'registration_approval' => [
    'notifications' => [
        'request'  => \App\Notifications\RegistrationApprovalRequest::class,
        'approved' => \App\Notifications\RegistrationApproved::class,
    ],
    'log_channel'   => 'cabinet',
    'json_redirect' => true,   // its API client follows `redirect` on 401
],
'password_reset' => [
    'report_unknown_email'    => true,
    'check_token_before_form' => true,
],
'frontend' => [
    'routes' => [
        'cabinet-kit.settings'                => 'cabinet.settings',
        'cabinet-kit.account.set'             => 'cabinet.account.set',
        'cabinet-kit.permissions.toggle'      => 'admin.role.togglepermission',
        'cabinet-kit.permissions.store'       => 'admin.permission.store',
        'cabinet-kit.permissions.rename.post' => 'admin.permission.update',
    ],
    'logout_method' => 'get',
    'switch_account_method' => 'get',  // its route answers GET
    'home_route'   => 'home',          // auth pages' logo links to the site
    'auth_logo'    => '/images/logo.png',
    'tab_bar_sets' => [                // configuration → role → route names
        'default' => ['default' => ['cabinet.dashboard', 'cabinet.settings']],
    ],
],
// Sign-out lands on the public site's login — another app, full page load.
'logout' => ['full_reload' => true],
// Users page: its own row fields, a right for its own card sections, the
// built-in super administrator locked for everyone.
'users_admin' => [
    'list'             => \App\Services\Admin\PlatformUsersList::class,
    'permission_flags' => ['accounts' => 'sysper-accounts'],
    'root_immutable'   => true,
],
'auth_mail'  => ['enabled' => false],
'site'       => ['views' => [], 'share_prop' => false],
'seo'        => ['share_prop' => false],
```

With routes off, the host's routes point at package controllers only where the
two behave the same; the rest keeps its own controllers. Package pages rendered
from the host's routes are found by the resolver as usual.

If the host's root view preloads the current page (`@vite([..., "resources/…/{$page['component']}.vue"])`),
that path breaks on package pages: the built manifest has no such file, and
every package page answers 500 in production (the dev server does not check the
manifest, so it only shows up after a build). List the page folders in the
order the client resolver checks them instead:

```blade
@vite(array_filter([
    'resources/_main/js/main.js',
    \Posio\CabinetKit\Support\InertiaPageEntry::path($page['component'], [
        'resources/_main/js/Pages',
        'vendor/posio/cabinet-kit/resources/js/pages',
        'vendor/posio/cabinet-kit/resources/js',
    ]),
]))
```

A page missing from the build is not preloaded; the browser still loads it.

Frontend extension points for such a host — call them in the entry point before
the app is created:

```js
import { extendI18n }           from '@cabinet-kit/i18n.config.js';
import { registerDeviceLog }    from '@cabinet-kit/DeviceLog.js';
import { registerUserCardTabs } from '@/_admin/js/userCardTabs.js';
import { registerSettingsTabs } from '@/_admin/js/pages/Settings/settingsTabs.js';

// Its own dictionaries: under the cabinet's (the cabinet wins on a shared key)
// or over them; its languages; module-level $t translates.
extendI18n({
    baseMessages: { uk: siteUk, en: siteEn },
    messages:     { uk: chatUk },
    supportedLocales: ['uk', 'en', 'ru'],
    browserLocaleAliases: { ru: 'uk' },
    translateModuleT: true,
});

// Shared code (API client, dictionaries) logs into the host's device log.
registerDeviceLog('sync', myLogger); // msg / warn / error / debug

// Extra sections of the user card. The component gets user, perms,
// active (the section is open) and shared (per-user store of all sections).
registerUserCardTabs([
    { id: 'licenses', label: 'Licenses', component: LicensesTab, permission: 'accounts' },
]);

// Settings tabs of the host live in the host. A file named like a package tab
// (CabinetSettingsUserProfileTab.vue) replaces it; a tab missing from the
// catalog needs its entry: { id, label, file, account_wide? }.
registerSettingsTabs(
    import.meta.glob('./pages/Settings/CabinetSettings*Tab.vue'),
    [{ id: 'billing', label: 'Billing', file: 'CabinetSettingsBillingTab.vue', account_wide: true }],
);
```

## Known gaps (intentionally out of scope)

- Additional social login providers, 2FA and magic links are not bundled;
  Google and Apple are the supported social providers.
- `Table.vue` is a minimal client-side sortable table — no server pagination,
  no row context menu, no soft-delete UI. For anything more, either build on
  top of it or port the fuller `Table.vue` from posio.cabinet's
  `resources/js/Elements/Table.vue` (~much larger, has its own conventions
  documented in that project's `.claude/context/modules/cabinet-reports-tables.md`
  and `project-tables.md`).
