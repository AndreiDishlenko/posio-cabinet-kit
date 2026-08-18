<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'cabinet-kit:install {--no-doctor : Skip the final cabinet-kit:doctor run}';
    protected $description = 'Install CabinetKit into a Laravel host project with minimal manual wiring.';

    protected const BUNDLED_ROUTE_NAMES = [
        'login', 'register', 'logout',
        'password.request', 'password.email', 'password.reset', 'password.store', 'password.update',
        'verification.notice', 'verification.verify', 'verification.send',
    ];

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'cabinet-kit-config']);

        if (! $this->ensurePermissionConfig()) {
            return self::FAILURE;
        }

        if (! $this->handleRouteConflicts()) {
            return self::FAILURE;
        }

        $entry = $this->resolveViteEntry();
        $this->setCabinetConfigValue('vite_entry', $entry);
        $this->setCabinetConfigValue('overrides_path', '_admin/overrides');

        $this->scaffoldOverridesFolder();
        $this->scaffoldStyleOverrides($entry);
        $this->scaffoldViteEntry($entry);

        $this->patchViteConfig($entry);
        $this->patchTailwindConfig();
        $this->patchUserModel();
        $this->publishPermissionMigrations();

        $this->info('Running migrations (permission tables, accounts, user settings)...');
        $this->call('migrate');

        $this->seedRolesAndPermissions();

        if (! $this->option('no-doctor')) {
            $this->call('cabinet-kit:doctor');
        }

        $this->printSummary($entry);

        return self::SUCCESS;
    }

    protected function ensurePermissionConfig(): bool
    {
        if (! File::exists(config_path('permission.php'))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
                '--tag' => 'permission-config',
            ]);
        }

        if (! File::exists(config_path('permission.php'))) {
            $this->error('config/permission.php was not published. Publish Spatie permission config and rerun the installer.');
            return false;
        }

        if ($this->permissionTablesWereMigratedWithoutTeams()) {
            $this->error('Spatie permission tables already exist without the team_id column.');
            $this->line('Rollback those migrations or add the missing team columns before installing CabinetKit.');
            return false;
        }

        $path = config_path('permission.php');
        $contents = File::get($path);
        $updated = $this->patchPermissionConfig($contents);

        if ($updated !== $contents) {
            if (! $this->confirmPatch($path, 'Patch config/permission.php for CabinetKit role tables and teams?')) {
                $this->error('CabinetKit requires Spatie Permission teams and user_has_* pivot tables.');
                return false;
            }

            $this->backupAndPut($path, $updated);
            $this->info('Patched config/permission.php.');
        }

        $this->applyPermissionRuntimeConfig();

        return true;
    }

    protected function patchPermissionConfig(string $contents): string
    {
        $replacements = [
            "/'model_has_permissions'\\s*=>\\s*'[^']+'/" => "'model_has_permissions' => 'user_has_permissions'",
            "/'model_has_roles'\\s*=>\\s*'[^']+'/" => "'model_has_roles' => 'user_has_roles'",
            "/'model_morph_key'\\s*=>\\s*'[^']+'/" => "'model_morph_key' => 'user_id'",
            "/'teams'\\s*=>\\s*false/" => "'teams' => true",
        ];

        foreach ($replacements as $pattern => $replacement) {
            $contents = preg_replace($pattern, $replacement, $contents, 1);
        }

        return $contents;
    }

    protected function publishPermissionMigrations(): void
    {
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
            '--tag' => 'permission-migrations',
        ]);
    }

    protected function applyPermissionRuntimeConfig(): void
    {
        config([
            'permission.table_names.model_has_permissions' => 'user_has_permissions',
            'permission.table_names.model_has_roles' => 'user_has_roles',
            'permission.column_names.model_morph_key' => 'user_id',
            'permission.teams' => true,
        ]);
    }

    protected function permissionTablesWereMigratedWithoutTeams(): bool
    {
        $tables = [
            config('permission.table_names.model_has_roles', 'user_has_roles'),
            config('permission.table_names.model_has_permissions', 'user_has_permissions'),
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'team_id')) {
                return true;
            }
        }

        return false;
    }

    protected function handleRouteConflicts(): bool
    {
        $conflicts = $this->detectRouteConflicts();

        if ($conflicts === []) {
            return true;
        }

        $this->warn('The host already defines Laravel auth route names:');
        foreach ($conflicts as $name => $files) {
            $this->line('  '.str_pad($name, 24).implode(', ', $files));
        }

        $choice = $this->choice(
            'Which auth routes should CabinetKit use?',
            ['host', 'cabinet', 'abort'],
            'host',
        );

        if ($choice === 'abort') {
            $this->warn('CabinetKit installation aborted because of route name conflicts.');
            return false;
        }

        $this->setCabinetConfigValue('auth_routes', $choice === 'cabinet');

        if ($choice === 'cabinet') {
            $this->warn('Host auth route files were left untouched. Remove or rename conflicting routes before running route:cache.');
        } else {
            $this->info("Set 'auth_routes' => false so the host auth routes remain authoritative.");
        }

        return true;
    }

    protected function detectRouteConflicts(): array
    {
        $conflicts = [];

        foreach (File::glob(base_path('routes/*.php')) as $file) {
            $contents = File::get($file);

            foreach (self::BUNDLED_ROUTE_NAMES as $name) {
                if (str_contains($contents, "->name('{$name}')") || str_contains($contents, '->name("'.$name.'")')) {
                    $conflicts[$name][] = basename($file);
                }
            }
        }

        return $conflicts;
    }

    protected function resolveViteEntry(): string
    {
        $configured = config('cabinet-kit.vite_entry', 'resources/_admin/js/cabinet.ts');
        if (File::exists(base_path($configured))) {
            return $configured;
        }

        $candidates = array_values(array_filter(
            File::glob(base_path('resources/_*/js/*.{ts,js}'), GLOB_BRACE) ?: [],
            fn ($path) => ! str_contains(str_replace('\\', '/', $path), '/node_modules/'),
        ));

        if ($candidates !== []) {
            $choices = array_map(fn ($path) => str_replace('\\', '/', ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR)), $candidates);
            $choices[] = $configured;

            return $this->choice('Choose the CabinetKit Vite entry path', $choices, array_search($configured, $choices, true) ?: 0);
        }

        return $configured;
    }

    protected function scaffoldOverridesFolder(): void
    {
        $overridesPath = resource_path(config('cabinet-kit.overrides_path', '_admin/overrides'));

        if (File::exists($overridesPath)) {
            return;
        }

        File::ensureDirectoryExists($overridesPath.'/pages/Settings');
        File::ensureDirectoryExists($overridesPath.'/layouts');

        File::put($overridesPath.'/README.md', <<<'MD'
# CabinetKit overrides

Drop a file here with the same relative path as its counterpart in
`vendor/posio/cabinet-kit/resources/js/...` to replace it. The page resolver
checks this folder first, then falls back to the package version.

Example: `overrides/pages/Settings/UsersTab.vue` replaces the package tab.
MD);

        $this->info('Created resources/'.config('cabinet-kit.overrides_path', '_admin/overrides').'/');
    }

    protected function scaffoldStyleOverrides(string $entry): void
    {
        $entryDir = trim(dirname($entry), '.');
        $moduleRoot = preg_replace('#/js$#', '', $entryDir) ?: 'resources/_admin';
        $overridePath = base_path($moduleRoot.'/scss/cabinet-kit-overrides.scss');

        if (File::exists($overridePath)) {
            return;
        }

        File::ensureDirectoryExists(dirname($overridePath));
        File::copy(__DIR__.'/../../../stubs/cabinet-kit-overrides.scss.stub', $overridePath);

        $this->info('Created '.str_replace('\\', '/', ltrim(str_replace(base_path(), '', $overridePath), DIRECTORY_SEPARATOR)).'.');
    }

    protected function scaffoldViteEntry(string $entry): void
    {
        $entryPath = base_path($entry);

        if (File::exists($entryPath) && str_contains(File::get($entryPath), 'createCabinetKitApp')) {
            return;
        }

        if (File::exists($entryPath) && ! $this->confirmPatch($entryPath, "Replace {$entry} with the CabinetKit entry factory?")) {
            return;
        }

        File::ensureDirectoryExists(dirname($entryPath));
        $this->backupAndPut($entryPath, File::get(__DIR__.'/../../../stubs/cabinet-entry.js.stub'));

        $this->info("Prepared {$entry}.");
    }

    protected function patchViteConfig(string $entry): void
    {
        $path = $this->firstExisting(base_path('vite.config.ts'), base_path('vite.config.js'));
        if (! $path) {
            $this->warn("vite.config.js/ts was not found. Add {$entry} to laravel-vite-plugin input and use cabinetKit().");
            return;
        }

        $contents = File::get($path);
        $updated = $contents;

        if (! str_contains($updated, 'cabinet-kit/resources/vite/cabinet-kit.js')) {
            $updated = "import cabinetKit from './vendor/posio/cabinet-kit/resources/vite/cabinet-kit.js';\n".$updated;
        }

        if (! str_contains($updated, 'cabinetKit(')) {
            $updated = preg_replace('/plugins\\s*:\\s*\\[/', "plugins: [\n        cabinetKit({ https: true }),", $updated, 1, $pluginCount);
            if (($pluginCount ?? 0) === 0) {
                $this->warn('Could not patch vite.config plugins array. Add cabinetKit({ https: true }) manually.');
            }
        }

        if (! str_contains($updated, "'{$entry}'") && ! str_contains($updated, "\"{$entry}\"")) {
            $updated = $this->patchViteInput($updated, $entry);
        }

        if ($updated !== $contents && $this->confirmPatch($path, 'Patch vite.config with CabinetKit plugin and entry?')) {
            $this->backupAndPut($path, $updated);
            $this->info('Patched '.basename($path).'.');
        }
    }

    protected function patchViteInput(string $contents, string $entry): string
    {
        $updated = preg_replace_callback('/input\\s*:\\s*\\[([^\\]]*)\\]/s', function ($matches) use ($entry) {
            $inner = rtrim($matches[1]);
            $comma = trim($inner) === '' || str_ends_with(trim($inner), ',') ? '' : ',';

            return "input: [{$inner}{$comma} '{$entry}']";
        }, $contents, 1, $count);

        if ($count > 0) {
            return $updated;
        }

        $updated = preg_replace("/input\\s*:\\s*(['\"])([^'\"]+)\\1/", "input: ['$2', '{$entry}']", $contents, 1, $count);

        if ($count === 0) {
            $this->warn("Could not patch laravel-vite-plugin input. Add '{$entry}' manually.");
        }

        return $updated;
    }

    protected function patchTailwindConfig(): void
    {
        $path = $this->firstExisting(base_path('tailwind.config.ts'), base_path('tailwind.config.js'), base_path('tailwind.config.cjs'));
        if (! $path) {
            $this->warn('tailwind.config.js/ts was not found. Add the CabinetKit preset manually if you use Tailwind.');
            return;
        }

        $contents = File::get($path);
        if (str_contains($contents, 'tailwind-preset.cjs') || str_contains($contents, 'vendor/posio/cabinet-kit/resources/js/**/*.vue')) {
            return;
        }

        $usesCommonJs = str_contains($contents, 'module.exports');
        $updated = $usesCommonJs
            ? "const cabinetKitPreset = require('./vendor/posio/cabinet-kit/tailwind-preset.cjs');\n".$contents
            : "import cabinetKitPreset from './vendor/posio/cabinet-kit/tailwind-preset.cjs';\n".$contents;

        if (preg_match('/presets\\s*:\\s*\\[/', $updated)) {
            $updated = preg_replace('/presets\\s*:\\s*\\[/', 'presets: [cabinetKitPreset, ', $updated, 1, $count);
        } else {
            $updated = preg_replace_callback(
                '/(export\\s+default\\s+\\{|module\\.exports\\s*=\\s*\\{)/',
                fn ($matches) => $matches[1]."\n    presets: [cabinetKitPreset],",
                $updated,
                1,
                $count,
            );
        }

        if (($count ?? 0) === 0) {
            $this->warn('Could not patch tailwind.config. Add presets: [cabinetKitPreset] manually.');
            return;
        }

        if ($this->confirmPatch($path, 'Patch tailwind.config with CabinetKit preset?')) {
            $this->backupAndPut($path, $updated);
            $this->info('Patched '.basename($path).'.');
        }
    }

    protected function patchUserModel(): void
    {
        $path = app_path('Models/User.php');
        if (! File::exists($path)) {
            $this->warn('app/Models/User.php was not found. Add Posio\\CabinetKit\\Traits\\IsCabinetKitUser manually.');
            return;
        }

        $contents = File::get($path);
        if (str_contains($contents, 'IsCabinetKitUser')) {
            return;
        }

        $updated = preg_replace('/^namespace\\s+App\\\\Models;\\s*$/m', "namespace App\\Models;\n\nuse Posio\\CabinetKit\\Traits\\IsCabinetKitUser;", $contents, 1);

        $updated = preg_replace('/(class\\s+User[^\\{]*\\{\\s*)(use\\s+[^;]+;)/s', '$1$2'."\n    use IsCabinetKitUser;", $updated, 1, $count);
        if (($count ?? 0) === 0) {
            $updated = preg_replace_callback(
                '/(class\\s+User[^\\{]*\\{)/',
                fn ($matches) => $matches[1]."\n    use IsCabinetKitUser;",
                $updated,
                1,
                $count,
            );
        }

        if (($count ?? 0) === 0) {
            $this->warn('Could not patch User model. Add the IsCabinetKitUser trait manually.');
            return;
        }

        if ($this->confirmPatch($path, 'Patch app/Models/User.php with IsCabinetKitUser?')) {
            $this->backupAndPut($path, $updated);
            $this->info('Patched app/Models/User.php.');
        }
    }

    protected function seedRolesAndPermissions(): void
    {
        if (! $this->confirm('Seed base roles and manage-account permission?', true)) {
            return;
        }

        (new \Posio\CabinetKit\Database\Seeders\CabinetKitRolesSeeder())->run();
        $this->info('Roles and permissions seeded.');

        (new \Posio\CabinetKit\Database\Seeders\CabinetKitAdminLinksSeeder())->run();
        $this->info('Admin links seeded.');

        if ($this->confirm('Seed CabinetKit system users and assign their roles?', true)) {
            (new \Posio\CabinetKit\Database\Seeders\CabinetKitSystemUsersSeeder())->run();
            $this->info('System users seeded.');
        }
    }

    protected function setCabinetConfigValue(string $key, mixed $value): void
    {
        $path = config_path('cabinet-kit.php');
        if (! File::exists($path)) {
            return;
        }

        $contents = File::get($path);
        $export = var_export($value, true);

        if (preg_match("/'{$key}'\\s*=>\\s*([^,]+),/", $contents)) {
            $updated = preg_replace("/'{$key}'\\s*=>\\s*([^,]+),/", "'{$key}' => {$export},", $contents, 1);
        } else {
            $updated = preg_replace_callback(
                "/'root_view'\\s*=>\\s*([^,]+),/",
                fn ($matches) => "'root_view' => {$matches[1]},\n\n    '{$key}' => {$export},",
                $contents,
                1,
            );
        }

        if ($updated !== $contents) {
            $this->backupAndPut($path, $updated);
            config(["cabinet-kit.{$key}" => $value]);
        }
    }

    protected function confirmPatch(string $path, string $question): bool
    {
        $relative = str_replace('\\', '/', ltrim(str_replace(base_path(), '', $path), DIRECTORY_SEPARATOR));

        return $this->confirm($question." A .bak copy of {$relative} will be created.", true);
    }

    protected function backupAndPut(string $path, string $contents): void
    {
        if (File::exists($path) && ! File::exists($path.'.bak')) {
            File::copy($path, $path.'.bak');
        }

        File::put($path, $contents);
    }

    protected function firstExisting(string ...$paths): ?string
    {
        foreach ($paths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function printSummary(string $entry): void
    {
        $this->newLine();
        $this->line('<fg=green>CabinetKit install finished.</>');
        $this->line("Vite entry: {$entry}");
        $this->line('Run npm install if the host is missing ziggy-js or @iconify/vue, then npm run dev.');
    }
}
