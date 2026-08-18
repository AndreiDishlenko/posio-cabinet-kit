# CLAUDE.md — posio/cabinet-kit 

Guidance for Claude Code (or any AI assistant) working directly in this
repository — the standalone package, not a project that consumes it.

## What this is

A Composer package (`posio/cabinet-kit`) that adds a generic admin panel
("cabinet") to other Laravel + Inertia + Vue 3 web projects: shell +
backend + DB + seed data, meant to be installed once and updated via
`composer update`, not copy-pasted per project.

Intended module scope (some already shipped, some not yet started —
check `docs/CHANGELOG.md` for what actually landed before assuming a
module exists):
- **Users** — account membership, invite/remove — shipped.
- **Roles & permissions** — Spatie teams, per-account role assignment —
  shipped.
- **Settings** — config-driven tabs shell (Account/Users/Profile) —
  shipped.
- **Auth** — login/register/logout/password reset/email verification —
  shipped (v0.2.0+).
- **Logs** — not yet built.
- **Localization** — i18n plumbing exists (`i18n.config.js`,
  `localeSync.js`) but there's no dedicated locale-management module yet.
- **Notifications** — not yet built.

See `docs/ARCHITECTURE.md` for the full picture and `docs/EXTENDING.md`
for the intended extension points — **read both before making
structural changes.**

## Origin & extraction method

This package is not written from scratch — it's extracted from the
actively-developed `posio.cabinet` project (`F:\OpenServer\home\posio.cabinet`).
The working method: pull the relevant original files/components from
`posio.cabinet`, lay them out under the *same relative structure* here
(`resources/_admin/...` → `resources/js/...`, `app/Models/...` →
`src/Models/...`, etc.), then generalize/wrap them — strip anything
Posio-specific (product tour, AI widgets, product business logic),
rename to the package's own prefixes (`cabinet-kit.*`, `--ck-*`).

**Always try to preserve the original files and components from
`posio.cabinet` as they are.** Port them over verbatim and change only
what genuinely has to change for the package (Posio-specific logic
stripped, prefixes renamed, host assumptions removed). Do not rewrite,
restructure, re-style or "improve" an original file while porting it,
and do not author a fresh replacement when an original exists — every
gratuitous deviation makes the next re-sync from `posio.cabinet` a
manual merge instead of a mechanical copy.

The mapping between original and package files, plus which files are
safe to mechanically re-sync vs. need hand-review, lives in
`tools/sync-manifest.json` and is applied by
`tools/Sync-CabinetKitFromPosio.ps1` — see `CABINET_KIT_MAINTENANCE.md`
for the full update workflow. Don't re-derive this process from scratch;
read that file first.

## Hard scope boundary

This package stays a **shell**. Do not add:
- Document/order/report/POS business logic (that's posio.cabinet's job)
- Anything that assumes a specific host database beyond `users`/`accounts`

Auth (login/register/logout/password reset/email verification) is bundled
— that boundary moved (see `docs/CHANGELOG.md` v0.2.0). It stays a *generic*
auth flow, though: don't add product-specific onboarding steps, social
login providers, or anything posio.cabinet-specific to it.

If a change only makes sense for one specific consumer project, it belongs
in that project's own `resources/_admin/overrides/`, not here.

## Conventions

- **Vue**: Options API only. Never `<script setup>` or Composition API
  imports (`ref`, `reactive`, `computed` as imports, etc.).
- **No `lang="ts"`** on `<script>` tags.
- Indentation in `.vue` files: tabs, not spaces.
- Props: object syntax, one per line, never shorthand.
- CSS variables: everything prefixed `--ck-*` (never bare names that could
  collide with a host app's own tokens).
- Tailwind for layout/spacing in templates; `<style lang="scss" scoped>`
  only for what Tailwind can't express (CSS vars, pseudo-elements,
  keyframes, complex selectors).
- Forms: always validate client-side before submitting; backend validation
  errors are the fallback layer, not the primary one.
- PHP: PSR-12, typed properties/returns where the codebase already does.

## No copy-on-install, ever

The single most important architectural invariant: Vue/SCSS/PHP source
files are **read directly from `vendor/posio/cabinet-kit/...`** by the host's
build (Vite alias) and framework (service provider `loadMigrationsFrom` /
`loadRoutesFrom`). Nothing gets published/copied except
`config/cabinet-kit.php`. Any change that requires the host to manually
re-copy a file after `composer update` breaks the "simple install, simple
update" premise this package exists for — treat that as a design smell, not
a normal trade-off, and look for a config-driven or override-resolvable
alternative first.

## Versioning

Semver git tags on this repo. Bump `docs/CHANGELOG.md` before tagging.
Breaking changes (renamed config keys, removed props on shipped Vue
components, changed route names) require a major bump — consumer projects
pin a version range in their `composer.json`.

## Testing changes against a real consumer

There is no consumer project inside this repo. To verify a change:
1. Have a Laravel+Inertia+Vue3 project with
   `composer.json` → `repositories: [{ type: "vcs", url: "F:/Packages/posio-cabinet-kit" }]`
   and `require: { "posio/cabinet-kit": "dev-main" }`.
   `composer update posio/cabinet-kit` after each commit here pulls the change.
2. Or, for quick iteration, a `path` repository type instead of `vcs` — but
   remember the final distribution mechanism is `vcs` (git-syncable), so
   don't let path-only behavior (symlinks) leak into how you design things.
