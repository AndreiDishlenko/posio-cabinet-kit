<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Posio\CabinetKit\Support\CabinetRedirects;
use Posio\CabinetKit\Support\FrontendDependencies;
use Posio\CabinetKit\Support\HostTailwindConfig;

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
        $this->check(File::exists(config_path('cabinet-kit-redirects.php')), 'config/cabinet-kit-redirects.php is published', 'Run php artisan cabinet-kit:sync-config.');
        $this->check(CabinetRedirects::unresolvable() === [], 'Auth flow landing pages resolve to registered routes', $this->unresolvableRedirectsHint());
        $this->check(File::exists(base_path($entry)), "Vite entry exists: {$entry}", "Create {$entry} or update config/cabinet-kit.php.");
        $this->check($this->entryUsesFactory($entry), 'Vite entry uses createCabinetKitApp()', 'Replace the entry with the CabinetKit stub or import createCabinetKitApp().');
        $this->check($this->viteConfigLooksReady($entry), 'vite.config contains CabinetKit plugin and entry', "Add cabinetKit() and '{$entry}' to laravel-vite-plugin input.");
        $this->check($this->tailwindConfigLooksReady(), 'tailwind.config contains CabinetKit preset', 'Add vendor/posio/cabinet-kit/tailwind-preset.cjs.');
        $this->check($this->tailwindContentLooksReady(), 'tailwind.config scans CabinetKit templates', "Add '".HostTailwindConfig::CONTENT_GLOB."' to the content array, or run php artisan cabinet-kit:sync-config.");
        $this->check($this->userModelLooksReady(), 'User model uses IsCabinetKitUser', 'Add Posio\\CabinetKit\\Traits\\IsCabinetKitUser to app/Models/User.php.');
        $this->check(File::exists(config_path('permission.php')), 'config/permission.php is published', 'Publish Spatie Permission config before running migrations.');
        $this->check((bool) config('permission.teams'), "Spatie Permission 'teams' is true", "Set 'teams' => true in config/permission.php before migrating.");
        $this->check($this->permissionConfigLooksReady(), 'Spatie Permission table config matches CabinetKit', 'Set model_has_roles=user_has_roles, model_has_permissions=user_has_permissions and model_morph_key=user_id.');
        $this->check($this->permissionTablesLookReady(), 'Permission role tables exist and include team_id when present', 'Run php artisan migrate after CabinetKit patches config/permission.php.');
        $this->check(Schema::hasTable('accounts') && Schema::hasTable('user_has_accounts'), 'CabinetKit account tables exist', 'Run php artisan migrate.');
        $this->check(Schema::hasTable('admin_links'), 'CabinetKit admin_links table exists', 'Run php artisan migrate.');
        $this->check($this->routeNamesDoNotCollide(), 'Route names can be cached', "Set 'auth_routes' => false or remove duplicate auth route names.");
        $this->check($this->socialAuthLooksReady(), 'Configured social sign-in providers have their driver installed', 'Run composer require laravel/socialite (and socialiteproviders/apple for Apple), or clear the credentials in config/cabinet-kit.php.');
        $this->check($this->logViewerLooksReady(), 'Log viewer is mounted where the Logs menu item points', 'Align log-viewer route_path with cabinet-kit.log_viewer.route_path, or drop the Logs menu item.');
        foreach (FrontendDependencies::PACKAGES as $package => $version) {
            $this->check($this->packageJsonHas($package), "package.json contains {$package}", "Run npm install {$package}@\"{$version}\".");
        }

        $this->check(File::exists(public_path('cabinet-assets/images/cabinet_logo_dark_theme.svg')), 'CabinetKit original menu assets are published', 'Run php artisan vendor:publish --tag=cabinet-kit-assets --force.');

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

    protected function userModelLooksReady(): bool
    {
        $path = app_path('Models/User.php');

        return File::exists($path) && str_contains(File::get($path), 'IsCabinetKitUser');
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
