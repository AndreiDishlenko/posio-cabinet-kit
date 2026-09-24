# Changelog

## Unreleased — Page tools in the header, rounded search field

**Added**
- Auth and registration tests that run inside the host project:
  `php artisan cabinet-kit:test` (sign-in, sign-out, password reset,
  registration modes, email confirmation links, registration approval). They
  boot the host's own app — its migrations, user model and config — on an
  in-memory SQLite database by default (`--db-connection` / `--db-database` for
  another one; its name must contain a `test` marker, it is wiped). Refuses to
  run with cached config. PHPUnit comes from the host's dev dependencies.
- Project scripts, created by `cabinet-kit:install` and `cabinet-kit:sync-config`
  only when missing: `deploy` (git pull, migrate, re-cache, queue restart,
  sitemap, `npm run buildssr`/`build`, site check, `--rollback`), `cc` / `cc.bat`
  (cache reset), `release.bat` and `scripts/pre-push-checks.sh` (runs
  `cabinet-kit:test`, `php artisan test` and the build before a release).
  An existing `scripts/pre-push-checks.sh` gets the `cabinet-kit:test` step
  added; an older `release.bat` of the same family that cannot run the checks
  is replaced (the old one stays as `release.bat.bak`). `cabinet-kit:doctor`
  warns when a release would skip the package tests.
- Registration modes: `cabinet-kit.registration` / `CABINET_REGISTRATION` =
  `closed` (default) | `approval` | `open`. Closed: the sign-in page has no
  "Sign up" link (shared prop `registration_open`) and no social buttons; the
  registration form, Google/Apple sign-in and the approval link answer 404
  (`RequireRegistrationNotClosed`). E-mail + password sign-in is unaffected. **Breaking:** `ONBOARDING_REGISTRATION_APPROVAL`
  / `cabinet_onboarding.registration_approval` is gone — a host that wants approval
  sets `CABINET_REGISTRATION=approval`, one that wants open sign-up sets `open`.
- `CabinetLayout` slot `header_tools`: a page puts its own controls (e.g. a
  list view switch) into the cabinet header, right of the title. Pages that
  don't fill it render the header as before.
- `@/js/Elements/Forms/SearchableInputRounded.vue` — filled "pill" search field
  from the source project (same props and events as `SearchableInput`); colour
  token `--search-input-background` in both themes.
- Cards (from the source project): `.full-height-card` keeps a modal card at
  the full height the modal allows; `ModalForm` prop `backClose` closes the
  modal with the browser/gesture "back" (off by default); `.page-filters`
  spacing rule in `cards_admin.scss`.
- `overlayHistory`: `setBackFallback` / `clearBackFallback`; an overlay's
  close handler may return `false` to stay open on "back"; closing an overlay
  also closes the ones opened on top of it.

**Changed**
- A missing or closed target lands on the profile. The target of every auth
  step — the page the user was heading to, or the one named in
  `cabinet-kit-redirects` — opens only if all permission gates on its route let
  the user through (`Support\PageAccess`); otherwise the user lands on the new
  `profile` key (`cabinet-kit.settings`, the one page no permission gates).
  Password sign-in keeps the remembered page when it is open. A cabinet page
  denied by a permission gate now redirects to the profile too, instead of a
  bare 403; data requests keep the 403. With the package defaults a fresh user
  used to land on the users page, which needs `sysper-users`, and saw a 403.
- A signed-in user opening a guest auth page (sign-in, sign-up, password
  reset) lands on the cabinet `home` target — or the profile when it is closed
  to them — instead of the framework default, which picked the host's
  `dashboard` or `home` route: a leftover starter-kit `dashboard` route sent
  such a user to a page that no longer rendered, and a public `home` took them
  out of the cabinet. The guest routes use `Http\Middleware\NewGuest` (ported
  from posio.cabinet) instead of the `guest` alias.
- Every cabinet page opened with a plain GET is remembered as the intended one
  (`Http\Middleware\NewAuth`, ported from posio.cabinet, first in the fixed
  part of the cabinet stack): sign-in, the cabinet button on the site and a
  confirmed email return to it. Actions, data requests, sign-out and the
  email notice screen are not remembered. A repeated confirmation link opened
  by its own signed-in user adds `verified=1` to the target, as upstream does.
- Permission gates implement `Support\PermissionGate` (a static `allows()`)
  and throw `AccessDeniedHttpException`: `CanSystemPermission` does, and a
  host's own gate joins the rule by doing the same (see EXTENDING).
- Email confirmation: an already confirmed user on the notice screen, the
  resend action or a repeated link goes to the remembered page or `home`, as
  in posio.cabinet (was `after_verify`).

**Fixed**
- Email confirmation link no longer answers 403 when opened while another user
  is signed in: the link is identified by its signature, not by the session
  (route moved out of the `auth` group), and that case renders
  `pages/Auth/EmailVerificationOutcome` — whose address was confirmed, who is
  signed in, "continue" / "sign in as" buttons. Opened signed out it confirms
  and sends to sign-in with a status; a link whose user was deleted or whose
  address changed leads to sign-in with `verification-link-invalid`; a damaged
  signature (GET or a mail scanner's HEAD) leads to sign-in with
  `verification-link-broken` instead of a bare 403.

**Build**
- Settings tabs load lazily: `settingsTabs.js` exports `settingsTabLoader(file)`,
  `CabinetSettings.vue` wraps it in `defineAsyncComponent`, and the cabinet's
  page glob in `createApp.js` no longer imports `Settings/CabinetSettings*Tab.vue`.
  This removes the Vite warning "is dynamically imported … but also statically
  imported" for `CabinetSettingsUserProfileTab.vue`.
- The Vite plugin looks for the dev certificate only for `vite serve`:
  `npm run build` on a server no longer prints "Certificate … was not found".

**SEO**
- `og:image:width` / `og:image:height` are the real size of the image: taken
  from `SeoService::override()` (`og_image_width`, `og_image_height`), else
  measured from the file when it is on the site, else the configured size — but
  only for the default image. An unknown size prints no size tags (it used to
  claim 1200×628 for any image).
- `SeoService::override()` accepts `og_type` (e.g. `product`; empty — the
  `SeoMeta` prop decides, `website` by default) and `follow`: a `noindex` page
  gets `noindex, follow` instead of `noindex, nofollow`. The ready value is in
  the `seo.robots` prop.

**Changed**
- `updcab` / `updcab.bat` leave out of `composer update` every `posio/*`
  package linked into `vendor/` (junction or symlink to a working copy of its
  repository) and name them, so a package under live development keeps its
  link. Existing launchers are never overwritten: to get this, delete them and
  run `php artisan cabinet-kit:sync-config`.
- `updcab` / `updcab.bat` install a `posio/*` module that is required in
  `composer.json` but missing from `vendor/` even when another package is
  linked. Before, a linked package made step 1 skip or update only installed
  packages, and the next step failed on the module's missing classes. Now the
  whole `posio/*` set is updated; linked packages are unlinked for the Composer
  run and relinked after it, also when Composer fails.
- `updcab --full` / `updcab.bat --full`: also updates the packages' Composer
  dependencies (`--with-all-dependencies`) and runs `npm install`. Without it
  dependencies are kept and `npm install` runs only when `node_modules/` is
  missing.
- Table tools panel search (`panelitems` of type `search`) uses the rounded
  search field.
- A table inside a card sits on the card's surface: header and row lines use
  the new `--card-table-header-background` / `--card-table-border-color`
  tokens (both themes). Footer buttons of `ModalCard` get the `card-button`
  class.

**Fixed**
- `AccordionItem2` opens in its initial state from the first render, so
  server-rendered pages no longer arrive collapsed. Outside `cells_mode` the
  header is a keyboard-operable button (`role`, `tabindex`, Enter/Space) with
  `aria-expanded`.
- Tall content in a card with a set height no longer squeezes the card header
  and footer (`flex-shrink: 0`).
- Closing a modal card no longer rebuilds the page and reloads its list: the
  cabinet app now takes over the "back" handling (`takeOverBackButton`) before
  Inertia starts, so the history step a card removes on close doesn't reach the
  router.

## 0.4.0 (unreleased) — Module API

Minor bump: hosts on `^0.3` change the constraint to `^0.4` (or let the first
module's installer do it). Nothing a 0.3 host wrote needs changing.

**Added**
- Cabinet modules: composer packages declaring `extra.cabinet-kit`
  (`module`, `admin`, `alias`, `npm`) are discovered from
  `vendor/composer/installed.json` by the provider and by the Vite plugin.
  Their pages (`pages/<module>/…`) resolve between host overrides and kit pages
  without touching the host entry (`virtual:cabinet-kit-modules`), and join
  Inertia's server-side page paths. See EXTENDING → "Writing a module".
- `Posio\CabinetKit\CabinetKit` + facade: `cabinetRoutes()` / `apiRoutes()`
  (cabinet prefix and full authenticated stack, no copied middleware lists),
  `cabinetMiddleware()`, `systemPermissions()`, `dictionaries()`,
  `translations()`, `sitemap()`, `menu()`, `doctor()`, `syncConfig()`.
- `Support\ModuleMenu::install()/uninstall()` — a module's menu group in
  `admin_links` from its migrations, appended after existing items, idempotent.
- `cabinet-kit.api.dictionaries` (`{prefix}/api/kit-dictionaries`): the kit's
  own dictionaries endpoint, merging module providers with a host's existing
  `cabinet.api.dictionaries` answer (host wins on equal names, `X-only` honoured).
  The cabinet picks it first, so a host endpoint keeps working unchanged.
- `SeoService::override([...])` and `addJsonLd([...])` — per-request meta and
  extra JSON-LD nodes for pages one SEO record cannot describe (a product
  page). `SeoService` is now bound per request (`scoped`).
- `sitemap:generate` calls registered sitemap providers after the SEO records.
- `cabinet-kit:doctor` checks each module: pages under `pages/<module>/`,
  Tailwind content glob, npm packages, plus the module's own checks.
- `cabinet-kit:sync-config` adds each module's Tailwind glob and npm packages
  and runs the module's sync steps.
- `HostComposerJson::raiseCaretConstraint()` for module installers.

**Changed**
- `updcab` / `updcab.bat` update every `posio/*` package, so installed modules
  move together with the kit. `sync-config` switches existing launchers' update
  step in place; the rest of a host-edited launcher is kept.
- System permissions registered by modules are part of the roles sync and of
  `doctor`'s drift check.
- The Vite plugin allows the real path of a junctioned package (`fs.allow`) and
  recognises files reached through it as the package's own when resolving the
  shared `@/js` / `@/_admin` prefixes.

## Unreleased — Side menu and header parity with the source project

**Added**
- `config/seo.php` → `site_creator` (`name`, `url`, `id`): the site developer in
  JSON-LD as `WebSite.creator`, linked by `@id` to the organization node on the
  developer's own site. Empty by default — nothing is emitted. Hosts with an
  already published `config/seo.php` add the key by hand.
- `config/seo.php` → `site_platform` (`id`, `name`, `url`): the platform the
  site is built on in JSON-LD as `WebSite.isBasedOn`, linked by `@id` to the
  product node on the platform's site. Empty by default — nothing is emitted.
  Hosts with an already published `config/seo.php` add the key by hand.
- `updcab` / `updcab.bat`: `--ssr` runs the host's `npm run buildssr` instead of
  `npm run build`, so an update of a server-rendered site rebuilds the SSR
  bundle and restarts its process rather than leaving it on the old bundle.
  Existing launchers are never overwritten: to get the flag, delete `updcab`
  (and `updcab.bat`) in the project root and run `php artisan cabinet-kit:sync-config`.
- `PasswordInput.vue` (`resources/js/Elements/Forms/`): password field with an eye
  toggle inside the input; the caret keeps its position when the type flips.
  Attributes and classes land on the `<input>` itself, so existing field styling
  is unchanged. `reveal: false` hides the eye; `v-model:visible` links fields —
  a confirmation field follows the main one without its own eye. Exposes
  `focus()`, `select()`, `clear()`.
  Used in Login, Register, ResetPassword, the profile settings tab,
  ChangeSystemPassword and `InlineInput type="password"` (new `reveal` / `visible`
  props, used by `UserCard`). `_formMixins` keeps such fields in the Enter/Tab
  chain and change-validation.

**Fixed**
- `SeoMeta.vue`: `canonical` and `og:url` were empty in server-rendered HTML when
  the host layout did not pass the `canonical` prop (the fallback was the
  browser address, which does not exist during SSR). The component now falls
  back to `seo.canonical` from the shared props; an explicit prop still wins.
- `SeoService`: `canonical` and `hreflang` alternates are built on `APP_URL`
  and drop the query string, so a page opened via a host alias (`www`) or with
  tracking parameters no longer declares itself canonical.
- SEO page, "Create sitemaps.xml": the request failed with 500 — `sitemap:generate`
  was registered only for console runs, so the call from the web request found no
  such command. It is now registered in every context.
- `SideMenu.vue`: the ProductTour "expand all groups" call was silently a no-op —
  the emitter bound `tour_show_all_groups`/`tour_restore_groups` to methods named
  `tourShowAll`/`tourRestore`, which didn't exist (the actual methods were named
  `showAllGroups`/`restoreGroups`). Renamed the methods and the `forceExpand` data
  field (now `tourExpand`) to match.
- `SideMenu.vue`: the pinned/collapsed state (`localStorage`) was only restored in
  `created()`, so a menu re-enabled after being `disabled` (e.g. once the initial
  setup step that disables it finishes) stayed collapsed instead of picking the
  saved state back up. Extracted `restorePinnedState()` and call it from an
  `immediate` watcher on `disabled` in addition to mount.
- `SideMenu.vue`: the mobile pullout panel used to only close on explicit events
  (burger click, opening the user menu); a page navigation while it was open left
  it hanging open over the new page. Now closes on every Inertia `navigate` event.

**Changed**
- `spatie/laravel-sitemap` (`^7.3|^8.0`) moved from `suggest` to `require`:
  `sitemap:generate` and the SEO page button work without a separate install.
  Hosts on an older sitemap version must upgrade it.
- `CabinetHeader.vue`: the header title always shows the whole page name, on every
  screen width; the "Group / Page" and "Page / Tab" breadcrumb (muted prefix + `/`)
  is gone. Tabbed pages still pass the active tab via `page_name` — it now only
  drives the browser tab `<title>`. Header priority: `header_title` → menu item name
  (`currentPage.name`) → `page_name`.
- `CabinetLayout.vue`: new `header_title` prop (forwarded to the header) for pages
  that aren't in the cabinet menu, so the server can't name them.
  `CabinetSettings.vue` passes `header_title="Settings"`.
- `SideMenu.vue`: rail (collapsed) mode no longer hides the group-header button —
  it now shows just the arrow (rotated closed by default) as the sole, always
  usable control to expand a group from the rail, with the group name in a
  `title` tooltip and `aria-label`. Previously the header was invisible and
  inert in rail mode, so every group's item list was always fully shown there
  regardless of its collapsed state.
- `SideMenu.vue` / `CabinetHeader.vue`: keyboard-accessibility pass ported from
  the source project — real `<button>` elements (with `aria-label`) instead of
  clickable `span`/`div` for the burger, user-menu and dev-permissions triggers;
  the header title is now an `h1` (previously the page had no level-1 heading);
  visible `:focus-visible` ring (new `--focus-ring-color` token in
  `resources/scss/colors_shared.scss`) on the menu's buttons/links/group-header
  and on the header's icon buttons.
- `SideMenu.vue`: added a "Logout" item (with a divider above it) to the footer
  settings dropdown, next to the settings tabs — an in-menu way to sign out
  without opening the mobile user panel. Uses `method="post"`, matching this
  package's `logout` route (POST-only, unlike the GET route of the source
  project this component was mirrored from).
- `CabinetKitAdminLinksSeeder`: SEO menu item icon changed to
  `icon-park-outline:seo` to match the source project's current icon
  (was `mdi:google`).

## Unreleased — Administration menu and the built-in super administrator

**Removed**
- The `Settings` item (route `cabinet-kit.settings`) of the Administration menu
  group: it is not in posio.cabinet's menu and duplicated the user block of the
  side menu, which still opens the settings page. Migration
  `remove_settings_menu_item` deletes the row from `admin_links`; the item is
  also gone from the `cabinet-kit.menu` fallback and the installer seeder.

**Changed**
- `sysper-log-view` is no longer delegated to `System administrator` — the log
  viewer and the Logs item stay with `SAdmin`. Migration
  `revoke_log_view_from_system_administrator` revokes it on existing hosts, and
  creating the permission from the matrix grants it to `SAdmin` only.
- The roles sync after every `migrate` puts the built-in system user
  (`cabinet-kit.system_users.sa`) back on the `SAdmin` system role. The Users
  page no longer lists that user and refuses to change its role.

## Unreleased — New registrations wait for an administrator's approval

**Added**
- Registration approval mode, **on by default**: `cabinet_onboarding.registration_approval`
  (env `ONBOARDING_REGISTRATION_APPROVAL`, set it to `false` to turn it off).
  A self-registered user — form or first social sign-in — is marked
  `approval_requested_at`, and every user holding the system permission
  `sysper-users` (SAdmin included) gets a letter with a signed approval link.
  Until someone follows it, the user can confirm their email, but signing in
  answers "registration not approved by an administrator" and the cabinet route
  group (`RequireRegistrationApproval`, outside `cabinet-kit.middleware`) logs
  such a session out. Approving requires being signed in with `sysper-users`;
  the user then gets a letter that they can sign in.
- `cabinet_onboarding.registration_approval_emails` (env
  `ONBOARDING_REGISTRATION_APPROVAL_EMAILS`, comma-separated): send the approval
  letter to these addresses instead of every `sysper-users` holder.
- Migration `add_registration_approval_to_users_table`: nullable
  `approval_requested_at`, `approved_at`, `approved_by` on the users table.
- `Services\RegistrationApprovalService`, `Notifications\RegistrationApprovalRequest`,
  `Notifications\RegistrationApproved`, route `registration.approve`
  (`{prefix}/registration/approve/{id}/{hash}`), page `pages/Auth/RegistrationApproval`,
  mail views `registration-approval-request` / `registration-approved` and texts
  `mail.registration_approval_request.*` / `mail.registration_approved.*`,
  `auth.pending_approval`.
- `Approve` button in the Users page table, visible only on rows waiting for
  approval (`approval_requested_at` set, `approved_at` empty): an in-cabinet
  alternative to the letter's link for a `sysper-users` holder, for when the
  letter is lost or never arrives. Route `cabinet-kit.users.approve`
  (`UsersController::approve`), gated by the same `sysper-users` middleware as
  the rest of the Users routes.

**Changed**
- Signing in returns to the page that sent the visitor to the login form
  (`redirect()->intended`) before falling back to `after_login` — the approval
  link from the letter survives the sign-in.
- `AuthMail::siteName()` is public: the approval letters reuse it.

Existing, invited and built-in users are never marked and keep signing in after
the update. Turning the mode off lets everyone still waiting in.

## Unreleased — Roles and permissions are synced on every migrate

**Fixed**
- A host installed on an early release kept the roles seeder of that release:
  no `sysper-*` permissions, `is_system` left at the column defaults, and the
  system roles, account roles and user role pickers in the cabinet showed
  empty. The seeder only ever ran inside `cabinet-kit:install`, so no update
  could repair it.

**Changed**
- Roles and permissions are reconciled after every `php artisan migrate`
  (including `./updcab`), with no seeder to run. System roles and permissions
  mirror posio.cabinet one-to-one; account level is reduced to the roles named
  in `cabinet-kit.roles` plus `manage-members` / `manage-account`. The POS
  permissions posio.cabinet uses (`manage-cashiers`, `manage-orders`,
  `manage-docs`, `manage-integrations`, `view-reports`, `view-owner-reports`)
  are no longer created. Nothing existing is deleted or revoked, and a
  permission the operator removed from a role is not granted back.
- `CabinetKitRolesSeeder` is a thin wrapper over the same sync.
- `cabinet-kit:install` no longer asks about roles; its seeding prompt covers
  the menu, system users and the home page SEO record.

**Added**
- `cabinet-kit:doctor` check: reference roles and permissions exist and carry
  the right level.

## Unreleased — Auth emails are translated and customizable from the host

**Fixed**
- The email confirmation and password reset letters arrived in English: the
  package sent Laravel's stock letters, had no translations of its own, and
  guest auth routes never applied the visitor's language. `ApplyCabinetKitLocale`
  now runs on every cabinet route (it replaces the locale step inside
  `ShareCabinetKitData`), and the letters are built from the package templates.
- Password reset statuses ("We have emailed your password reset link.") fall
  back to the package translation when the host has none for the language.

**Added**
- `lang/` copied whole from posio.cabinet: JSON loaded globally, groups as
  `cabinet-kit::*`; the host's own files win.
- `lang/{uk,en}/mail.php`, `resources/views/mail/{layout,verify-email,reset-password}.blade.php`
  and `Notifications\AuthMail`, wired through `VerifyEmail/ResetPassword::toMailUsing`
  unless a callback is already set.
- `cabinet-kit.auth_mail` (`enabled`, `views`) and the publish tag
  `cabinet-kit-mail`. Host overrides: `lang/vendor/cabinet-kit/{locale}/mail.php`
  (per key) and `resources/views/vendor/cabinet-kit/mail/*` — see EXTENDING.

## Unreleased — Email confirmation is mandatory after form registration

**Fixed**
- A form-based sign-up went straight into the cabinet without confirming the
  email: confirmation depended on `after_register` and on the host `User`
  implementing `MustVerifyEmail`. Registration now always sends the
  confirmation email and lands on `verification.notice`; the cabinet route group
  runs `NotVerified` (ported from posio.cabinet) regardless of
  `cabinet-kit.middleware`.

**Removed**
- `after_register` in `config/cabinet-kit-redirects.php` — nothing to configure
  any more; a leftover value in a published host config is ignored.

## Unreleased — Post-registration steps are switched by config, all off

**Added**
- `config/cabinet_onboarding.php` (merged under `cabinet_onboarding`, publish tag
  `cabinet-kit-onboarding`): the same switches as posio.cabinet for every step
  after registration — `account_setup`, `product_tour`, `spotlight_hints`,
  `first_steps_checklist`, `first_receipt_congrats`, `team_notification`,
  `user_milestones`. All `false` in the package; shared to pages as `onboarding`.
- `ProductTour.vue`, `SpotlightHints.vue`, `FirstReceiptCongrats.vue` and the
  hint registry copied verbatim from posio.cabinet; `_admin` `CabinetLayout`
  mounts them again, each gated by its switch. `Spotlight.vue` re-synced.

**Changed**
- Registration and social sign-up no longer create an account. In posio.cabinet
  the company is set up in a separate onboarding step behind `account_setup`,
  which the package does not ship. A new user gets an account by invitation.

**Fixed**
- Registration silently stayed on the form: the server required
  `company_name`, but the form ported from posio.cabinet has no such field, so
  the rejection had nowhere to show. The requirement is gone together with the
  account creation it fed.
- `Auth/Register.vue` re-synced from posio.cabinet 2.5.42: visible labels,
  `<form>` submit, password-length hint, no minimum length on the name.

## Unreleased - Social sign-in switch and branded auth layout

**Added**
- `cabinet-kit.social_auth.<provider>.enabled` (Google reads
  `GOOGLE_AUTH_ENABLED`, Apple reads `APPLE_AUTH_ENABLED`). A disabled provider
  is rejected by its controller even when credentials remain configured. The
  package also supplies missing nested flags after Laravel's shallow config
  merge, so existing published configs pick up the env switch without being
  overwritten.

**Fixed**
- Login and registration now show the Google button and its divider only when
  Google sign-in is both enabled and configured; projects can turn it off
  without overriding package Vue pages.
- `AuthLayout` now renders the main-site logo from the shared `site` payload,
  with the package brand asset as a safe fallback, instead of pointing at the
  host-specific `/main-assets/images/logo_white.png` path.

## Unreleased — Shared import prefixes resolve by the side of the importing file

**Fixed**
- A project with its own `resources/js/components` could not build: the package
  took the whole `@/js` prefix into `vendor`, so the project had to claim the
  colliding folders back with an alias of its own — and that alias then sent
  the package's own imports into the project (`Could not load
  .../resources/js/components/BurgerMenu/BurgerMenu.vue (imported by
  vendor/posio/cabinet-kit/.../CabinetBurgerMenu.vue)`). The two shared
  prefixes, `@/js` and `@/_admin`, now resolve by the side of the importing
  file: a file of the package gets the package's copy, a file of the project
  gets the project's, and whatever is missing on one side is looked up on the
  other. A project that carried such a workaround alias can drop it; one that
  has no folder of its own sees no change.
- The side is chosen by what is actually stored on disk, and on Windows the
  spelling has to match the stored one: a path differing only in case counts as
  missing, so the choice does not depend on the platform — passing locally and
  failing on a case-sensitive build server is how the previous folder-case bug
  reached production. Only the tail below the shared folder is compared, so a
  project that installs the package through a symlinked path repository
  resolves the same way as one installed from git.
- The same case check now covers `@cabinet-kit` and `@/scss`, the prefixes that
  always point into the package. A path spelled with the wrong case stops the
  build on the spot, naming what was asked for and what is stored, instead of
  passing on Windows and reaching the build server as `Could not load
  .../resources/js/сomponents/SeoMeta.vue`.
- `docs/host/seo.md` told a host to import `@cabinet-kit/сomponents/SeoMeta.vue`
  — the folder is `components`. A project that followed the guide built locally
  and failed on deploy; the same spelling is corrected in `config/seo.php` and
  in the sync manifest's copy target.

## Unreleased — The one-step update runs on a production server too

**Added**
- `updcab` — the shell twin of `updcab.bat`, for updating a project on a
  Linux/macOS host over ssh (`cd /var/www/example.com && ./updcab`). Same six
  steps in the same order, stopping at the first failure, with what a server
  needs on top: `migrate --force` (no tty to confirm a production migration
  with), `php artisan optimize` after the clear when `APP_ENV=production` (the
  update cleared caches the site serves from), `--no-build` for a deploy whose
  assets are built elsewhere, and `PHP_BIN` for a specific PHP binary. A
  missing `npm` without `--no-build` stops the update instead of leaving stale
  assets behind.
- The interpreter is settled before the first step. Shared hosting serves the
  site on the PHP its panel selected while the shell of the same account still
  starts an old default, and the update then died on `composer update` with
  sixteen numbered conflicts whose only cause was `your php version (7.4.33)`.
  A `php` older than 8.2 is replaced by another binary found under the usual
  names and per-version paths (`php8.3`, `/opt/php83/bin/php`,
  `/opt/alt/php83/usr/bin/php`, `/opt/cpanel/ea-php83/root/usr/bin/php`), and
  the launcher says which one it took. Preference goes to the version the site
  is served with, read from the handler line of `.htaccess` — a host offering
  six alt-php versions has no newest-is-right answer, and resolving on a CLI
  newer than the site's installs code the site cannot execute, which shows up
  as a white page and not in this output. Without such a line the oldest
  supported version wins, for the same reason in the other direction. A handler
  line naming a version below 8.2 is reported, not obeyed. Composer is run under that same
  interpreter — as a phar or as a shebang script it would otherwise pick the
  default `php` back up and resolve dependencies for the wrong version.
  `PHP_BIN` overrides the choice; when it names a too-old binary, that is
  reported instead of guessed around.
- Composer is looked for beyond `PATH`, because a shared host rarely puts it
  there: `composer2`, then a phar or binary in the project root, in `~`, in
  `~/bin` and in the common system locations, then whatever the login shell
  resolves — a host that adds it to `PATH` in `~/.bashrc`, or declares it there
  as an alias over a phar, gives it to an interactive session only, so it runs
  by hand while the update stops on its first step. `COMPOSER_BIN` names one
  kept anywhere else. When nothing is found, the launcher prints both ways out
  instead of only stating the absence.
- `Posio\CabinetKit\Support\HostUpdateLaunchers` — writes both launchers into
  the host root. Both are written on every platform: a project is developed on
  one and deployed to another, and the launcher travels with the repository.
  The shell one is written with LF endings (CRLF makes it unrunnable), given
  the executable bit, and registered as executable in the host's git index so
  the bit survives a commit made from Windows.
- `.gitattributes` pins the line endings of the two launcher stubs, so a
  checkout on any machine keeps the shell script runnable and the batch file
  intact.

**Changed**
- `cabinet-kit:sync-config` creates missing update launchers. Projects
  installed before this version get `updcab` from their next update — through
  the composer post-update hook or step 2 of their existing `updcab.bat` —
  instead of having to re-run the installer for one file. An existing launcher
  is never overwritten: a host may have adapted it to its deploy.

## Unreleased — Import paths match the folder names on a case-sensitive disk

**Fixed**
- The build of a consumer project failed on Linux (`Could not resolve
  "../../Layouts/AuthLayout.vue"`) while passing on Windows: twelve imports
  spelled two package folders with a capital first letter — `Layouts/` and
  `Components/` — while the folders committed to git are lowercase. A
  case-insensitive filesystem hid the mismatch locally and the production
  build is where it surfaced. The imports now spell the folders as they are
  stored.
- The Vite plugin carried an alias for the capitalised components folder,
  which resolved to a path that does not exist outside Windows. It was a
  longer spelling of the alias right below it, so it is gone rather than
  corrected.

## Unreleased — Config keys the package dropped are reported instead of lingering

**Fixed**
- `cabinet-kit:sync-config` compared the published config with the package's
  in one direction only: it listed the keys a release had **added** and said
  nothing about the ones it had **removed**. A retired key therefore stayed in
  `config/cabinet-kit.php` of every updated project, reading like a live
  setting while nothing looked at it — which is how a `login_redirect_route`
  pointing at the long-gone `cabinet-kit.dashboard` survived every update of a
  consumer project, and how `settings_tabs` still sits in projects installed
  before the settings page was taken over. Both directions are reported now,
  the removed ones under "delete these".
- `cabinet-kit:doctor` gained the matching check, so the drift surfaces on the
  last step of the update procedure and not only in the output of a step that
  scrolls past. Missing keys stay out of the doctor on purpose: the package's
  own value is merged in underneath them, so they change no behaviour.

**Added**
- `Posio\CabinetKit\Support\HostConfigDrift` — the both-way comparison of the
  published config against the installed version, shared by the two commands.

## Unreleased — Row actions, floating elements and page actions

Ported from the upstream cabinet through release `2.5.38`. The point is
recorded in `tools/upstream-sync-state.json`, so the next pass reviews only
what lands after it.

**Added**
- **A registry of standard row actions.** A page's row bar now names only the
  event — `{ event: 'onEdit' }` — and the table supplies the icon and the
  tooltip from one place (`Elements/Table/rowActions.js`), so "open" no longer
  appears as a folder on one screen and a pencil on the next. Any key in the
  descriptor still overrides the standard, and non-standard buttons work as
  before. The delete/restore pair, its visibility conditions and the rule that
  delete sits rightmost are built in; `{ event: 'onActions' }` folds the whole
  set into a single "⋮" dropdown. The context menu and group rows read the same
  registry. Colours come from a palette key (`color: 'danger'`, or a function of
  the row).
- **One queue for floating elements** (`FloatingDock.vue` + `js/floatingDock.js`).
  Anything living in the bottom-right corner — the table's CTA, the
  scroll-to-top button, a host's own reminder — joins a shared column and
  stacks *above* what is already visible instead of covering it; when the lower
  one disappears, the rest slide down. `local` keeps an element in its own
  container's corner. Floating elements must not declare their own
  `position: fixed` any more.
- **Page actions live in the user panel.** `CabinetLayout` accepts a `page_menu`
  array (`{ name, icon?, action | href, disabled?, in_burger? }`) and passes it
  to the burger panel, where the items render above Settings. Nested blocks that
  cannot reach the header — report tabs — register themselves through
  `resources/_admin/js/mixins/_pageMenuMixins.js`; the panel collects them when
  it opens and keeps only the tab actually on screen (tabs stay mounted after
  switching). The per-page "⋮" button is gone from the filters row.
- `StatusDot.vue` and the table's `dot` column type; `ShowDeletedToggle`; the
  `auto-rows` table mode (row height follows content but never drops below the
  size's normal row); a refresh button in the filters row (`options.refresh`).

**Changed**
- The cabinet root view pins the viewport scale. Safari zooms the page when a
  field smaller than 16px takes focus and never zooms back; the meta tag now
  prevents it, so the 16px-minimum rule that used to fight it in
  `forms_admin.scss` is gone and field text sizes are free again.
- `.button-sm` / `.button-md` moved into `@layer components` and lost their gap
  in favour of `space-x`.

## Unreleased — Package routes are controller actions, not closures

**Changed**
- The four routes the package still declared as closures — the two asset
  routes (`cabinet-assets`, `brand-assets`), the locale switch and the cabinet
  root redirect — now point at `PackageAssetController`, `LocaleController` and
  `HomeController`. Same paths, same route names, same behaviour: `route:list`
  and `route:cache` in a host project were verified to resolve all four
  identically before and after.
- No fix is implied for hosts: Laravel serializes closure routes since 8.x, so
  `php artisan route:cache` worked either way (verified on Laravel 12.67). The
  point is readability and parity with the upstream cabinet, where the same
  locale switch is a controller action.

## Unreleased — Site settings, cabinet settings and SEO move into the package

**Added**
- **Site settings** (`cabinet-kit.sitesettings`) and **Cabinet settings**
  (`cabinet-kit.cabinetsettings`) — two cabinet sections behind `sysper-site`
  where an operator sets the site name, the default light/dark theme, the
  favicon and the logos, separately for the public part and for the cabinet.
  Values live in the new `site_settings` table, uploads in
  `storage/app/public/site/`; every image falls back to a neutral placeholder
  shipped by the package (`/brand-assets/...`, served by a package route), so
  no project's branding is baked into the code.
- The cabinet applies them itself: its root view prints the name and the
  favicon and sets the theme class before the first paint, and the side menu
  reads its logo/symbol from the shared `site` prop. A host's public views opt
  in through `cabinet-kit.site.views` and receive `$site_name`,
  `$site_favicon`, `$site_theme`.
- `php artisan site:import-brand` — bulk import of an installation's own brand
  files from `public/temp/` (names match the setting keys). Idempotent, so it
  also works as a restore path.
- **SEO** (`cabinet-kit.seo`) — per-route meta records (`seo_meta`), the `seo`
  Inertia prop consumed by `SeoMeta.vue`, Schema.org JSON-LD (`WebSite`,
  `Organization`, `WebPage`, `BreadcrumbList`, navigation `ItemList`, optional
  `SoftwareApplication`), `hreflang` alternates and `php artisan
  sitemap:generate`. The installer seeds one record for the `home` route so the
  section is never empty on a fresh project.
- `config/seo.php` and `config/general.php` are published by the installer and
  merged from the package otherwise. Everything project-specific in them is
  config-driven: the main-navigation nodes come from `seo.sitenav_routes`, the
  product node only exists when `seo.software.enabled` is on.
- **Integration docs for host projects.** `docs/host/*.md` is copied into the
  project as `docs/cabinet-kit/*.md` during installation, refreshed by
  `cabinet-kit:sync-config` (which already runs on every update), with a
  pointer block written into the project's `AGENTS.md` and `CLAUDE.md` so any
  assistant finds them.

**Notes**
- The "Generate with AI" button of the SEO card needs a generator of the host's
  own (`cabinet-kit.seo.meta_generator`); the package ships no language model
  and the button answers "not configured" until one is set.
- `sitemap:generate` needs `spatie/laravel-sitemap`; without it the command is
  simply not registered.

## Unreleased — Signing out actually signs out

**Fixed**
- `The GET method is not supported for route cabinet/logout` (405) when leaving
  the cabinet. The package's logout route is `POST` (the source project it was
  taken from used `GET`), but both places that link to it were still plain
  links: the burger menu item and the "Logout" link on the email-verification
  screen. Both send `POST` now, and burger-menu items accept a `method` prop —
  a non-GET item renders as a button, since a browser follows an `<a>` with
  `GET` regardless of what the component asked for.

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
