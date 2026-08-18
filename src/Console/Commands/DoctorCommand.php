<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DoctorCommand extends Command
{
    protected $signature = 'cabinet-kit:doctor';
    protected $description = 'Diagnose common CabinetKit installation problems.';

    protected int $failures = 0;

    public function handle(): int
    {
        $this->failures = 0;

        $entry = config('cabinet-kit.vite_entry', 'resources/_admin/js/cabinet.ts');

        $this->check(File::exists(config_path('cabinet-kit.php')), 'config/cabinet-kit.php is published', 'Run php artisan cabinet-kit:install.');
        $this->check(File::exists(base_path($entry)), "Vite entry exists: {$entry}", "Create {$entry} or update config/cabinet-kit.php.");
        $this->check($this->entryUsesFactory($entry), 'Vite entry uses createCabinetKitApp()', 'Replace the entry with the CabinetKit stub or import createCabinetKitApp().');
        $this->check($this->viteConfigLooksReady($entry), 'vite.config contains CabinetKit plugin and entry', "Add cabinetKit() and '{$entry}' to laravel-vite-plugin input.");
        $this->check($this->tailwindConfigLooksReady(), 'tailwind.config contains CabinetKit preset or package glob', 'Add vendor/posio/cabinet-kit/tailwind-preset.cjs.');
        $this->check($this->userModelLooksReady(), 'User model uses IsCabinetKitUser', 'Add Posio\\CabinetKit\\Traits\\IsCabinetKitUser to app/Models/User.php.');
        $this->check(File::exists(config_path('permission.php')), 'config/permission.php is published', 'Publish Spatie Permission config before running migrations.');
        $this->check((bool) config('permission.teams'), "Spatie Permission 'teams' is true", "Set 'teams' => true in config/permission.php before migrating.");
        $this->check($this->permissionTablesLookReady(), 'Permission role tables include team_id when present', 'Rollback/recreate Spatie Permission migrations with teams enabled.');
        $this->check(Schema::hasTable('accounts') && Schema::hasTable('user_has_accounts'), 'CabinetKit account tables exist', 'Run php artisan migrate.');
        $this->check($this->routeNamesDoNotCollide(), 'Route names can be cached', "Set 'auth_routes' => false or remove duplicate auth route names.");
        $this->check($this->packageJsonHas('ziggy-js'), 'package.json contains ziggy-js', 'Run npm install ziggy-js.');
        $this->check($this->packageJsonHas('@iconify/vue'), 'package.json contains @iconify/vue', 'Run npm install @iconify/vue.');

        if ($this->failures > 0) {
            $this->newLine();
            $this->error("CabinetKit doctor found {$this->failures} problem(s).");
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('CabinetKit doctor is green.');

        return self::SUCCESS;
    }

    protected function check(bool $ok, string $label, string $hint): void
    {
        if ($ok) {
            $this->line("<fg=green>OK</>   {$label}");
            return;
        }

        $this->failures++;
        $this->line("<fg=red>FAIL</> {$label}");
        $this->line("      {$hint}");
    }

    protected function entryUsesFactory(string $entry): bool
    {
        $path = base_path($entry);

        return File::exists($path) && str_contains(File::get($path), 'createCabinetKitApp');
    }

    protected function viteConfigLooksReady(string $entry): bool
    {
        $contents = $this->firstExistingContents(base_path('vite.config.ts'), base_path('vite.config.js'));

        return $contents !== null
            && (str_contains($contents, 'cabinetKit(') || str_contains($contents, "'@cabinet-kit'") || str_contains($contents, '"@cabinet-kit"'))
            && (str_contains($contents, "'{$entry}'") || str_contains($contents, "\"{$entry}\""));
    }

    protected function tailwindConfigLooksReady(): bool
    {
        $contents = $this->firstExistingContents(base_path('tailwind.config.ts'), base_path('tailwind.config.js'), base_path('tailwind.config.cjs'));

        return $contents !== null
            && (str_contains($contents, 'tailwind-preset.cjs') || str_contains($contents, 'vendor/posio/cabinet-kit/resources/js/**/*.vue'));
    }

    protected function userModelLooksReady(): bool
    {
        $path = app_path('Models/User.php');

        return File::exists($path) && str_contains(File::get($path), 'IsCabinetKitUser');
    }

    protected function permissionTablesLookReady(): bool
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'team_id')) {
                return false;
            }
        }

        return true;
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
