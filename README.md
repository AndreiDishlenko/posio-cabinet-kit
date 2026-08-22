# posio/cabinet-kit

## Quick install

The package is not on Packagist — it is installed straight from its git
repository, so any machine with network access to GitHub can install it; no
local checkout of the package is needed.

Run inside an existing Laravel 11/12 + Inertia + Vue 3 project:

```bash
# 1. register the package repository
composer config repositories.cabinet-kit vcs https://github.com/AndreiDishlenko/posio-cabinet-kit.git

# 2. pull the package in
composer require posio/cabinet-kit:^0.3

# 3. wire it into the host project (configs, migrations, seeds, vite/tailwind patches)
php artisan cabinet-kit:install

# 4. install the npm deps the command added to package.json, then build
npm install
npm run dev
```

Then open `/cabinet/register` and create the first user — registration also
creates the account (the "Company name" field), so no separate
account-creation step is needed on a fresh install.

Signing in as one of the seeded accounts (`sa` / `admin`, see `system_users`
in `config/cabinet-kit.php`) instead opens the password screen and stops
there: their installed password is written in that config and is the same in
every project using this package, so the cabinet opens only after it has been
replaced. Set `'force_system_password_change' => false` if those passwords are
managed outside the application.

### Notes on the git source

- Composer needs `git` on PATH; if it is missing, Composer falls back to
  downloading zip archives from GitHub, which also works.
- To follow the unreleased tip instead of a tagged release, require
  `dev-main` and set `"minimum-stability": "dev"` + `"prefer-stable": true`
  in the host `composer.json`.
- Private-repo / rate-limit case: authenticate Composer once with a GitHub
  token — `composer config --global github-oauth.github.com <token>`.
- Write the constraint as `^0.3`, not `0.3`: a two-segment version is an
  *exact* version to composer (it normalizes to `0.3.0`), so `0.3` installs
  the very first release of the line and hides every later tag —
  `composer update` then reports the project as current forever.
  `cabinet-kit:install` and `cabinet-kit:sync-config` rewrite such a
  constraint to its range form, and `cabinet-kit:doctor` fails on it.
- To pin an exact release, use a full tag instead of a range, e.g.
  `composer require posio/cabinet-kit:0.3.31` (left alone by the commands
  above — a three-segment pin is read as deliberate).
- Offline machines: clone or copy the repo to that machine and point the
  repository at the local path — `composer config repositories.cabinet-kit
  vcs /path/to/posio-cabinet-kit` (a plain filesystem path works as a `vcs`
  source because the copy is a git repo).

### Updating

```bash
composer update posio/cabinet-kit
php artisan migrate                   # new versions may ship migrations
php artisan cabinet-kit:doctor        # verifies frontend/backend wiring
npm install && npm run build
```

On Windows the install command drops `updcab.bat` in the project root — running
it performs the whole [Update](#update) procedure in order and stops at the
first failing step.

Requirements and what those commands actually do — below; the full
step-by-step is in [Update](#update).

---

Base admin-panel scaffolding extracted from `posio.cabinet`: multi-tenant
accounts, per-account roles/permissions (Spatie Permission teams), bundled
auth (login, register, logout, password reset, email verification), a
Settings shell, a log viewer, a collapsible side-menu layout, and a small
Vue 3 UI kit — for bootstrapping a new Laravel + Inertia + Vue 3 project's
admin part.

Not a finished product — a **shell** you extend per project. See
`docs/ARCHITECTURE.md` and `docs/EXTENDING.md`.

## Requirements

- Laravel 11/12, PHP 8.2+
- Inertia.js (Laravel adapter) + Vue 3, Options API
- `spatie/laravel-permission`, `opcodesio/log-viewer` (pulled in
  automatically)
- `tightenco/ziggy` (composer) + npm: `vue`, `@inertiajs/vue3`, `ziggy-js`,
  `@iconify/vue`, `@headlessui/vue`, `@vuepic/vue-datepicker`,
  `vue-final-modal`, `vee-validate`, `vue-i18n`, `dayjs`, `axios`,
  `sweetalert2`, `vue3-toastify`, `date-fns`,
  `@fontsource/inter`, `@fontsource/roboto`, `@fontsource/open-sans`,
  `@fontsource/inter-tight`, `@fontsource/pt-sans` — package pages resolve
  URLs through `route()` and use Iconify icons/date inputs with bundled
  fonts, confirmation dialogs and toasts. `php artisan cabinet-kit:install`
  adds them to `package.json` for you; `cabinet-kit:doctor` re-checks the
  list.
- A `User` model with `password`/`email_verified_at` columns (Laravel's
  default `users` migration already has both)

## Install (in a consumer project)

Commands — see [Quick install](#quick-install) above.

The install command publishes `config/cabinet-kit.php` and
`config/cabinet-kit-redirects.php`, enables Spatie
Permission teams before migrations, resolves auth-route conflicts, scaffolds
the cabinet Vite entry (`resources/_admin/js/cabinet.ts` by default),
patches `vite.config`, `tailwind.config` and `app/Models/User.php` with
`.bak` backups, drops `updcab.bat` (the one-step update launcher) in the
project root, runs migrations, seeds base roles, and finishes with
`cabinet-kit:doctor`.

If the database already contains users, the command asks whether to delete
them (with their accounts, memberships and role assignments) before seeding.
The default answer is **no** — say yes only on a database you are willing to
reset. `--purge-users` answers that prompt up front; with `--no-interaction`
and no flag nothing is deleted.

Every CabinetKit page renders into the package's own Blade root view
(`cabinet-kit::app`) with its own Vite entry — the host's main app view and
entry are untouched.

Visit `/cabinet/register` afterwards to create your first user + account —
registration bundles account creation (a "Company name" field), so there's
no separate account-creation step for a brand-new install.

## Update

Windows: run `updcab.bat` from the project root — it is scaffolded by the
install command and runs exactly the six steps below, stopping at the first
one that fails. Everything after this paragraph describes what it does (and
what to run by hand elsewhere).

Full procedure, in order:

```bash
# 1. pull the new package version
composer update posio/cabinet-kit

# 2. only if the post-update hook is missing (see below) — re-apply host wiring
php artisan cabinet-kit:sync-config

# 3. apply migrations a new version may have added
php artisan migrate

# 4. drop caches built from package routes/config/views
php artisan optimize:clear

# 5. rebuild the frontend (sync-config may have added npm packages)
npm install
npm run build

# 6. verify the result
php artisan cabinet-kit:doctor
```

On production, re-cache after step 4 as usual (`php artisan config:cache
route:cache view:cache`) — the caches must be rebuilt because package routes
and views changed, not because the update needs anything special.

`cabinet-kit:install` registers `sync-config` in your `composer.json`
`post-update-cmd`, so from then on `composer update` runs it for you (step 2
is then redundant): it keeps the npm dependency list and the Tailwind
`content` glob for the package templates current, creates config files a new
package version introduced, and only *reports* new `config/cabinet-kit.php`
keys (that file is never rewritten). Rebuild assets afterwards if it patched
anything.

Read its output: keys it lists are new settings your `config/cabinet-kit.php`
does not have yet — copy them in by hand, otherwise the package falls back to
its own defaults for them. `cabinet-kit:doctor` at the end reports anything
still unwired.

Vue/SCSS/routes/migrations are read straight from `vendor/posio/cabinet-kit/`
— nothing was copied into your project, so there's nothing to merge.
Anything you deliberately overrode in `resources/_admin/overrides/` keeps
working untouched.

### Which version you get

`composer update posio/cabinet-kit` only moves inside the range in your
`composer.json` (`^0.3` stays on `0.3.*`). A bare `0.3` there is not that
range — composer reads it as the single release `0.3.0` and silently stops
offering updates; `cabinet-kit:doctor` reports it, and `cabinet-kit:sync-config`
rewrites it to `^0.3` (run `composer update posio/cabinet-kit` once more
afterwards). Breaking changes — renamed config
keys, removed props on released Vue components, changed route names — arrive
as a major bump, so crossing one is a deliberate edit of the constraint
followed by `composer update`, plus a read of `docs/CHANGELOG.md`.

To see what is available before updating:

```bash
composer show posio/cabinet-kit --all | head -20   # versions offered by the repo
composer outdated posio/cabinet-kit                # installed vs latest in range
```

If a `dev-main` install stops picking up new commits, Composer is serving a
cached clone — `composer clearcache`, then update again.

## Customizing

- Menu items, assignable roles, log-viewer path → `config/cabinet-kit.php`
  (survives updates for free).
- Where sign-in, registration, email confirmation and sign-out land →
  `config/cabinet-kit-redirects.php`. Point `home`/`after_login` at your own
  route to open the cabinet on your own page.
- Styles → `resources/_admin/scss/cabinet-kit-overrides.scss` (scaffolded by
  install, loaded after the kit so your rules win; redefine `--ck-*` tokens or
  re-declare element classes — never edit the package's own scss).
- Deeper changes → `resources/_admin/overrides/pages/...` (checked before
  the package's own version — see `docs/EXTENDING.md`).

## Developing this package itself

Open this repo directly (`F:\Packages\posio-cabinet-kit`) and follow its own
`CLAUDE.md`. Tag a new semver version after merging a change; consumer
projects pick it up with `composer update posio/cabinet-kit`.
