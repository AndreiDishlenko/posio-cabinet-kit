# CabinetKit maintenance guide

This package is the reusable cabinet shell extracted from `posio.cabinet`.
It must stay autonomous: Composer package, own migrations/routes/auth/UI,
and no copied vendor files inside consumer projects except published config.

## Scope

Keep here:

- Auth: login, register, logout, password reset, email verification.
- Account shell: accounts, user-account membership, per-account roles.
- Cabinet layout: root Blade view, Inertia resolver, header, side menu,
  account switcher, settings shell.
- Generic UI kit and generic SCSS tokens/classes.
- Database needed by the shell itself.

Keep out:

- Docs, orders, products, cashflow, reports, fiscalization, Telegram/bot,
  analytics, onboarding funnels, demo data.
- Any route, migration, Vue prop, permission or service that makes sense
  only for `posio.cabinet`.

## Update workflow from posio.cabinet

For a low-token future pass, start with `.claude/context/index.md` and
`.claude/context/modules/maintenance-sync.md`; they summarize package-specific
decisions that should not be rediscovered from the full project context.

Run from the package root on Windows:

```powershell
.\tools\Sync-CabinetKitFromPosio.ps1 `
  -SourceRoot "F:\OpenServer\home\posio.cabinet" `
  -OpenDiff
```

The script reads `tools/sync-manifest.json` and creates
`sync-audit-YYYYMMDD-HHMMSS.md` in this package. It checks every mapped file,
prints hash/status information, and can embed `git diff --no-index` sections
for changed files.

The manifest has three modes:

- `manual`: shared ancestry, but the package version is generalized. Review
  the diff and port only generic behavior by hand.
- `copy`: safe mechanical mirror. `-Apply` may overwrite the target.
- `package_only`: owned by this package; no upstream source.

Current manifest is intentionally conservative and uses `manual` for extracted
code. Change an entry to `copy` only after proving it can be overwritten
without reintroducing Posio-specific logic.

## Recommended release checklist

1. Run the sync audit and review every `different` or `missing` row.
2. Port generic improvements into package files; leave product-specific code
   in `posio.cabinet` or in future feature packages. Ported files arrive with
   upstream route names — rename them to the package's own, otherwise Ziggy
   throws mid-render and takes the whole page down:

```powershell
Select-String -Path resources -Include *.vue,*.js -Recurse `
  -Pattern "route\('(cabinet|admin)\." 
```

   Names the package genuinely does not own (host-only screens) must be
   resolved defensively, never inline in a template.
3. Update `docs/CHANGELOG.md`, `docs/ARCHITECTURE.md` or `docs/EXTENDING.md`
   when contracts change.
4. Run package-level syntax checks:

```powershell
Get-ChildItem src,database,routes,config -Recurse -Filter *.php |
  ForEach-Object { php -l $_.FullName }
```

5. Test in a real consumer project with a VCS/path repository:

```powershell
composer update posio/cabinet-kit
php artisan optimize:clear
php artisan migrate
php artisan cabinet-kit:sync-config
npm run build
```

6. Tag and publish:

```powershell
git status
git add .
git commit -m "Prepare cabinet kit update"
git tag vX.Y.Z
git push origin main --tags
```

## Consumer update contract

Consumer projects should update with:

```powershell
composer update posio/cabinet-kit
php artisan cabinet-kit:sync-config
npm run build
```

They should not copy Vue, SCSS, PHP routes, migrations, controllers or models
from `vendor/posio/cabinet-kit`. Custom project behavior belongs in
`config/cabinet-kit.php`, `resources/_admin/scss/cabinet-kit-overrides.scss`,
or `resources/_admin/overrides/pages/...`.
