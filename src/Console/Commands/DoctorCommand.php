<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Posio\CabinetKit\CabinetKit;
use Posio\CabinetKit\Support\CabinetKitRoles;
use Posio\CabinetKit\Support\CabinetRedirects;
use Posio\CabinetKit\Support\FrontendDependencies;
use Posio\CabinetKit\Support\HostComposerJson;
use Posio\CabinetKit\Support\HostConfigDrift;
use Posio\CabinetKit\Support\HostDocs;
use Posio\CabinetKit\Support\HostScripts;
use Posio\CabinetKit\Support\HostTailwindConfig;
use Posio\CabinetKit\Support\HostViteConfig;
use Posio\CabinetKit\Traits\IsCabinetKitUser;
use ReflectionClass;

class DoctorCommand extends Command
{
    protected $signature = 'cabinet-kit:doctor';
    protected $description = 'Diagnose common CabinetKit installation problems.';

    protected int $failures = 0;

    protected int $warnings = 0;

    public function handle(): int
    {
        $this->failures = 0;
        $this->warnings = 0;

        $entry = config('cabinet-kit.vite_entry', 'resources/_admin/js/cabinet.ts');

        $this->check(HostComposerJson::receivesNewReleases(), 'composer.json can receive new cabinet-kit releases', $this->composerConstraintHint());
        $this->check(File::exists(config_path('cabinet-kit.php')), 'config/cabinet-kit.php is published', 'Run php artisan cabinet-kit:install.');
        $this->check(File::exists(config_path('cabinet-kit-redirects.php')), 'config/cabinet-kit-redirects.php is published', 'Run php artisan cabinet-kit:sync-config.');
        $this->check(HostConfigDrift::obsoleteKeys() === [], 'config/cabinet-kit.php has no keys the package dropped', $this->obsoleteConfigKeysHint());
        $this->check(CabinetRedirects::unresolvable() === [], 'Auth flow landing pages resolve to registered routes', $this->unresolvableRedirectsHint());
        $menuRoutes = $this->unresolvableMenuRoutes();
        $this->check($menuRoutes === [], 'Menu items point at registered routes', 'These items are hidden until their route exists: '.implode(', ', $menuRoutes).'.'
            .($this->integrates('load_routes') ? '' : ' The project serves the cabinet routes itself: map these names in frontend.routes if the bundled menu shows them.'), ! $this->integrates('load_routes'));
        $this->check(File::exists(base_path($entry)), "Vite entry exists: {$entry}", "Create {$entry} or update config/cabinet-kit.php.");
        $ownBoot = $this->entryBootsInertiaItself($entry);
        $this->check($this->entryUsesFactory($entry), 'Vite entry uses createCabinetKitApp()', $ownBoot
            ? 'The entry boots Inertia itself: package pages open only if its page resolver also looks in vendor/posio/cabinet-kit/resources/_admin/js/pages.'
            : 'Replace the entry with the CabinetKit stub or import createCabinetKitApp().', $ownBoot);
        $this->check($this->viteConfigLooksReady($entry), 'vite.config contains CabinetKit plugin and entry', "Run php artisan cabinet-kit:sync-config, or add ".HostViteConfig::PLUGIN_CALL." and '{$entry}' to laravel-vite-plugin input by hand — without the plugin the package resolves its imports against your own resources/ and renders unstyled.");
        $scansPackage = $this->tailwindContentLooksReady();
        $this->check($this->tailwindConfigLooksReady(), 'tailwind.config contains CabinetKit preset', $scansPackage
            ? 'Package templates are scanned, but the theme is your own: the colors, fonts and screens the preset adds must exist in your config too, or add vendor/posio/cabinet-kit/tailwind-preset.cjs.'
            : 'Add vendor/posio/cabinet-kit/tailwind-preset.cjs.', $scansPackage);
        $this->check($scansPackage, 'tailwind.config scans CabinetKit templates', "Add '".HostTailwindConfig::CONTENT_GLOB."' to the content array, or run php artisan cabinet-kit:sync-config.");
        $missingUserMethods = $this->missingUserMethods();
        $this->check($missingUserMethods === [], 'User model provides the IsCabinetKitUser API', $missingUserMethods === null
            ? 'User model '.$this->userModel().' was not found — set user_model in config/cabinet-kit.php.'
            : 'Missing: '.implode(', ', $missingUserMethods).'. Add Posio\\CabinetKit\\Traits\\IsCabinetKitUser to the model, or implement these methods: the cabinet route stack and module pages call them.');
        $this->check(File::exists(config_path('permission.php')), 'config/permission.php is published', 'Publish Spatie Permission config before running migrations.');
        $this->check((bool) config('permission.teams'), "Spatie Permission 'teams' is true", "Set 'teams' => true in config/permission.php before migrating.");
        $this->check($this->permissionConfigLooksReady(), 'Spatie Permission table config matches CabinetKit', 'Set model_has_roles=user_has_roles, model_has_permissions=user_has_permissions and model_morph_key=user_id.');
        $this->check($this->permissionTablesLookReady(), 'Permission role tables exist and include team_id when present', $this->permissionTablesHint());
        $this->check(CabinetKitRoles::drift() === [], 'System roles and permissions match the package reference', $this->rolesDriftHint());
        $this->check(Schema::hasTable('accounts') && Schema::hasTable('user_has_accounts'), 'CabinetKit account tables exist', 'Run php artisan migrate.');
        $this->check(Schema::hasTable('admin_links'), 'CabinetKit admin_links table exists', $this->integrates('load_migrations')
            ? 'Run php artisan migrate.'
            : 'Package migrations are off (host_integration.load_migrations): the bundled menu is built from the menu key of config/cabinet-kit.php.', ! $this->integrates('load_migrations'));
        $this->check($this->routeNamesDoNotCollide(), 'Route names can be cached', "Set 'auth_routes' => false or remove duplicate auth route names.");
        $this->check($this->socialAuthLooksReady(), 'Configured social sign-in providers have their driver installed', 'Run composer require laravel/socialite (and socialiteproviders/apple for Apple), or clear the credentials in config/cabinet-kit.php.');
        $this->check($this->logViewerLooksReady(), 'Log viewer is mounted where the Logs menu item points', 'Align log-viewer route_path with cabinet-kit.log_viewer.route_path, or drop the Logs menu item.');
        foreach (FrontendDependencies::PACKAGES as $package => $version) {
            $this->check($this->packageJsonHas($package), "package.json contains {$package}", "Run npm install {$package}@\"{$version}\".");
        }

        $this->check(Schema::hasTable('site_settings'), 'Site settings table exists', 'Run php artisan migrate.');
        $this->check(Schema::hasTable('seo_meta'), 'SEO table exists', 'Run php artisan migrate.');
        $this->check(File::exists(public_path('storage')), 'Public storage is linked', 'Run php artisan storage:link — uploaded logos and favicons are served from storage/app/public/site.');
        $this->check($this->seoRecordsExist(), 'At least one SEO record exists', 'Run the SEO seeder (part of cabinet-kit:install) or add a record in the cabinet SEO section.');
        $this->check(File::exists(base_path(HostDocs::TARGET_DIR.'/README.md')), 'Integration docs are present in the project', 'Run php artisan cabinet-kit:sync-config.');

        // Предупреждение, а не провал: как проект выпускает релизы, решает он сам.
        if (! HostScripts::releaseRunsPackageTests()) {
            $this->warn('  ! '.HostScripts::RELEASE.' does not run the CabinetKit tests before a release — run php artisan cabinet-kit:sync-config, or call php artisan cabinet-kit:test from your own release checks.');
        }

        $this->checkModules();

        if ($this->failures > 0) {
            $this->newLine();
            $this->error("CabinetKit doctor found {$this->failures} problem(s).");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('CabinetKit doctor is green.'.($this->warnings > 0 ? " {$this->warnings} warning(s) above are the project's own choices — review them once." : ''));

        return self::SUCCESS;
    }

    /**
     * Модуль ломается теми же тихими способами, что и сам пакет: шаблоны без
     * стилей, если их не сканирует Tailwind, и белая страница, если нет его
     * npm-зависимости. Сверх этого — собственные проверки модуля.
     */
    protected function checkModules(): void
    {
        $kit = app(CabinetKit::class);
        $modules = $kit->modules();
        $checks = $kit->doctorChecks();

        foreach ($modules as $name => $module) {
            $this->newLine();
            $this->line("<options=bold>Module {$name}</> ({$module['package']})");

            $this->check($this->modulePagesArePrefixed($module), "Cabinet pages live under pages/{$name}/", "Move the module's cabinet pages into pages/{$name}/ — Inertia names share one namespace with the package and the host.");
            $this->check(HostTailwindConfig::contentCovers((string) $this->tailwindConfigContents(), $module['tailwind_glob']), 'tailwind.config scans the module templates', "Add '{$module['tailwind_glob']}' to the content array, or run php artisan cabinet-kit:sync-config.");

            foreach ($module['npm'] as $package => $version) {
                $this->check($this->packageJsonHas($package), "package.json contains {$package}", "Run npm install {$package}@\"{$version}\", or php artisan cabinet-kit:sync-config.");
            }

            $this->runModuleChecks($checks[$name] ?? []);
        }

        // Проверки модуля, не объявившего себя в composer.json, всё равно выполняются.
        foreach (array_diff_key($checks, $modules) as $name => $moduleChecks) {
            $this->newLine();
            $this->line("<options=bold>Module {$name}</>");
            $this->runModuleChecks($moduleChecks);
        }
    }

    protected function runModuleChecks(array $checks): void
    {
        foreach ($checks as $provider) {
            try {
                foreach ((array) $provider() as [$ok, $label, $hint]) {
                    $this->check((bool) $ok, (string) $label, (string) $hint);
                }
            } catch (\Throwable $e) {
                $this->check(false, 'Module checks ran', $e->getMessage());
            }
        }
    }

    protected function modulePagesArePrefixed(array $module): bool
    {
        if ($module['admin'] === null) {
            return true;
        }

        $pages = $module['admin'].DIRECTORY_SEPARATOR.'pages';

        if (! File::isDirectory($pages)) {
            return true;
        }

        foreach (File::files($pages) as $file) {
            if ($file->getExtension() === 'vue') {
                return false;
            }
        }

        foreach (File::directories($pages) as $directory) {
            if (basename($directory) !== $module['module']) {
                return false;
            }
        }

        return true;
    }

    // Пустой раздел SEO читается как поломка, поэтому отсутствие записей —
    // повод для подсказки, а не молчания.
    protected function seoRecordsExist(): bool
    {
        if (! Schema::hasTable('seo_meta')) {
            return false;
        }

        return \Posio\CabinetKit\Models\SeoMeta::query()->withTrashed()->exists();
    }

    // Недостающая роль не падает с ошибкой — матрица ролей в кабинете просто пустая.
    protected function rolesDriftHint(): string
    {
        return implode('; ', CabinetKitRoles::drift()).'. Run php artisan migrate — it brings roles and permissions up to the reference.';
    }

    // Мягкая проверка — там, где проект сознательно держит это сам: расхождение
    // видно, но проверку перед релизом оно не валит.
    protected function check(bool $ok, string $label, string $hint, bool $soft = false): void
    {
        if ($ok) {
            $this->line("<fg=green>OK</>   {$label}");
            return;
        }

        if ($soft) {
            $this->warnings++;
            $this->line("<fg=yellow>WARN</> {$label}");
            $this->line("      {$hint}");
            return;
        }

        $this->failures++;
        $this->line("<fg=red>FAIL</> {$label}");
        $this->line("      {$hint}");
    }

    // The one failure with no symptom at all: composer keeps reporting the
    // project as up to date while every release after the pinned one is
    // unreachable.
    protected function composerConstraintHint(): string
    {
        $json = HostComposerJson::read() ?? [];
        $constraint = (string) HostComposerJson::constraint($json);
        $widened = HostComposerJson::widenedConstraint($constraint);

        return "\"{$constraint}\" is an exact version to composer, not a range — it matches one release only. "
            ."Change it to \"{$widened}\" in composer.json require, or run php artisan cabinet-kit:sync-config, then composer update posio/cabinet-kit.";
    }

    // A setting nothing reads any more has no symptom at all: it sits in the
    // published file looking live, and the release notes that retired it are
    // the only place saying otherwise.
    protected function obsoleteConfigKeysHint(): string
    {
        $keys = implode(', ', array_keys(HostConfigDrift::obsoleteKeys()));

        return "The installed version no longer reads these keys: {$keys}. "
            .'Delete them from config/cabinet-kit.php — see docs/CHANGELOG.md of the package for what replaced each one.';
    }

    // A landing page naming a route the project dropped is invisible until
    // someone signs in and lands on an exception instead of the cabinet.
    protected function unresolvableRedirectsHint(): string
    {
        $broken = [];

        foreach (CabinetRedirects::unresolvable() as $key => $target) {
            $broken[] = "{$key} => {$target}";
        }

        return 'Fix these in config/cabinet-kit-redirects.php: '.implode(', ', $broken).'.';
    }

    protected function unresolvableMenuRoutes(): array
    {
        $broken = [];

        foreach ([...config('cabinet-kit.menu', []), ...app(CabinetKit::class)->menuGroups()] as $group) {
            foreach ($group['children'] ?? [] as $item) {
                $route = $item['route'] ?? null;
                $target = config('cabinet-kit.frontend.routes', [])[$route] ?? $route;

                if (! empty($route) && ! Route::has($target)) {
                    $broken[] = $route;
                }
            }
        }

        return array_values(array_unique($broken));
    }

    protected function entryUsesFactory(string $entry): bool
    {
        $path = base_path($entry);

        return File::exists($path) && str_contains(File::get($path), 'createCabinetKitApp');
    }

    // An `@cabinet-kit` alias on its own used to be accepted here. It is not
    // enough any more and passing it green is what hides an unstyled cabinet:
    // the kit's own sources resolve each other through aliases that only the
    // plugin declares.
    protected function viteConfigLooksReady(string $entry): bool
    {
        $path = HostViteConfig::path();

        return $path !== null
            && HostViteConfig::usesPlugin($contents = File::get($path))
            && HostViteConfig::hasEntry($contents, $entry);
    }

    protected function tailwindConfigLooksReady(): bool
    {
        $contents = $this->tailwindConfigContents();

        return $contents !== null
            && str_contains($contents, 'tailwind-preset.cjs');
    }

    // The preset alone proves nothing: Tailwind v3 drops a preset's `content`
    // in favour of the host's, so the package glob has to be in the host list.
    protected function tailwindContentLooksReady(): bool
    {
        $contents = $this->tailwindConfigContents();

        return $contents !== null
            && HostTailwindConfig::contentCoversPackage($contents);
    }

    protected function tailwindConfigContents(): ?string
    {
        $path = HostTailwindConfig::path();

        return $path === null ? null : File::get($path);
    }

    protected function userModel(): string
    {
        return (string) config('cabinet-kit.user_model', 'App\\Models\\User');
    }

    // Проект со своим набором трейтов проходит, если у модели есть всё, что
    // пакет на ней вызывает: имя трейта в файле этого не доказывает.
    protected function missingUserMethods(): ?array
    {
        $model = $this->userModel();

        if (! class_exists($model)) {
            return null;
        }

        $missing = [];

        foreach ((new ReflectionClass(IsCabinetKitUser::class))->getMethods() as $method) {
            if (! method_exists($model, $method->getName())) {
                $missing[] = $method->getName();
            }
        }

        return $missing;
    }

    protected function entryBootsInertiaItself(string $entry): bool
    {
        $path = base_path($entry);

        return File::exists($path) && str_contains(File::get($path), 'createInertiaApp');
    }

    // Хост с кабинетом старше пакета выключает то, что пакет иначе делает со всем приложением.
    protected function integrates(string $switch): bool
    {
        return (bool) config("cabinet-kit.host_integration.{$switch}", true);
    }

    protected function permissionTablesLookReady(): bool
    {
        foreach ($this->permissionTables() as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        foreach ($this->permissionPivotTables() as $table) {
            if (! Schema::hasColumn($table, config('permission.column_names.team_foreign_key', 'team_id'))) {
                return false;
            }
        }

        return true;
    }

    // Pivots left under Spatie's own names are the one shape that looks like a
    // missing migration and is not: the tables are there, the config points
    // somewhere else, and every role query dies on a table that never existed.
    protected function permissionTablesHint(): string
    {
        $stale = array_values(array_filter(
            ['model_has_roles', 'model_has_permissions'],
            fn ($default) => config("permission.table_names.{$default}") !== $default
                && Schema::hasTable($default)
                && ! Schema::hasTable(config("permission.table_names.{$default}")),
        ));

        if ($stale === []) {
            return 'Run php artisan migrate after CabinetKit patches config/permission.php.';
        }

        return 'This project used Spatie before CabinetKit: '.implode(' and ', $stale)
            .' still carry their original names while config/permission.php points at the CabinetKit ones. '
            .'Run php artisan migrate to rename them.';
    }

    protected function permissionConfigLooksReady(): bool
    {
        return config('permission.table_names.model_has_roles') === 'user_has_roles'
            && config('permission.table_names.model_has_permissions') === 'user_has_permissions'
            && config('permission.column_names.model_morph_key') === 'user_id';
    }

    protected function permissionPivotTables(): array
    {
        return [
            config('permission.table_names.model_has_roles', 'user_has_roles'),
            config('permission.table_names.model_has_permissions', 'user_has_permissions'),
        ];
    }

    protected function permissionTables(): array
    {
        return [
            config('permission.table_names.permissions', 'permissions'),
            config('permission.table_names.roles', 'roles'),
            ...$this->permissionPivotTables(),
            config('permission.table_names.role_has_permissions', 'role_has_permissions'),
        ];
    }

    protected function routeNamesDoNotCollide(): bool
    {
        try {
            app('router')->getRoutes()->toSymfonyRouteCollection();
        } catch (\LogicException) {
            return false;
        }

        return true;
    }

    // Credentials without the driver behind them is the one silent failure of
    // social sign-in: the buttons render, the route answers 404, nothing says why.
    protected function socialAuthLooksReady(): bool
    {
        foreach (array_keys((array) config('cabinet-kit.social_auth', [])) as $provider) {
            if (! config("cabinet-kit.social_auth.{$provider}.enabled", true)) {
                continue;
            }

            if (blank(config("services.{$provider}.client_id"))) {
                continue;
            }

            if (! class_exists(\Laravel\Socialite\Facades\Socialite::class)) {
                return false;
            }

            if ($provider === 'apple' && ! class_exists(\SocialiteProviders\Apple\Provider::class)) {
                return false;
            }
        }

        return true;
    }

    // The Logs menu item is a bare href, so nothing links it to the viewer's own
    // path: publishing the viewer's config, or disabling it, turns that item
    // into a 404 with no other symptom.
    protected function logViewerLooksReady(): bool
    {
        $expected = trim((string) config('cabinet-kit.log_viewer.route_path', ''), '/');

        if ($expected === '') {
            return true;
        }

        return (bool) config('log-viewer.enabled', true)
            && trim((string) config('log-viewer.route_path', ''), '/') === $expected;
    }

    protected function packageJsonHas(string $package): bool
    {
        $path = base_path('package.json');
        if (! File::exists($path)) {
            return false;
        }

        $json = json_decode(File::get($path), true);
        if (! is_array($json)) {
            return false;
        }

        return isset($json['dependencies'][$package]) || isset($json['devDependencies'][$package]);
    }

    protected function firstExistingContents(string ...$paths): ?string
    {
        foreach ($paths as $path) {
            if (File::exists($path)) {
                return File::get($path);
            }
        }

        return null;
    }
}
