<?php

namespace Posio\AdminKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'admin-kit:install';
    protected $description = 'One-time setup: publish config, run migrations, seed roles, scaffold the overrides folder.';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'admin-kit-config']);

        $this->info('Running migrations (accounts, user_has_accounts, users.settings)...');
        $this->call('migrate');

        $this->seedRolesAndPermissions();
        $this->scaffoldOverridesFolder();
        $this->printNextSteps();

        return self::SUCCESS;
    }

    protected function seedRolesAndPermissions(): void
    {
        if (! $this->confirm('Seed base roles (Account owner, Administrator, Manager, User) and the manage-account permission?', true)) {
            return;
        }

        (new \Posio\AdminKit\Database\Seeders\AdminKitRolesSeeder())->run();
        $this->info('Roles and permissions seeded.');
    }

    protected function scaffoldOverridesFolder(): void
    {
        $overridesPath = resource_path('_admin/overrides');

        if (File::exists($overridesPath)) {
            return;
        }

        File::ensureDirectoryExists($overridesPath.'/pages/Settings');
        File::ensureDirectoryExists($overridesPath.'/layouts');

        File::put($overridesPath.'/README.md', <<<MD
        # AdminKit overrides

        Drop a file here with the same relative path as its counterpart in
        `vendor/posio/admin-kit/resources/js/...` to replace it — the page
        resolver (`resolvePage.js`) checks this folder first, and falls back to
        the package version if nothing matches. This is the escape hatch for
        deep customization; for menu items, settings tabs, and permissions,
        prefer editing `config/admin-kit.php` instead — it survives `composer
        update` with zero effort.

        Example: `overrides/pages/Settings/UsersTab.vue` replaces the package's
        `resources/js/pages/Settings/UsersTab.vue`.
        MD);

        $this->info('Created resources/_admin/overrides/ (see its README.md).');
    }

    protected function printNextSteps(): void
    {
        $this->newLine();
        $this->line('<fg=green>AdminKit installed.</> Remaining manual steps:');
        $this->line('');
        $this->line('1. If spatie/laravel-permission migrations are not published yet:');
        $this->line('     php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"');
        $this->line('   ...and set \'teams\' => true in config/permission.php BEFORE running them.');
        $this->line('');
        $this->line('2. Add HasAccount + HasSettings + HasCustomFields traits to your User model:');
        $this->line('     use Posio\\AdminKit\\Traits\\HasAccount;');
        $this->line('     use Posio\\AdminKit\\Traits\\HasSettings;');
        $this->line('     use Posio\\AdminKit\\Traits\\HasCustomFields;');
        $this->line('');
        $this->line('3. Add the AdminKit Vite alias + fs.allow entry to vite.config.js (see docs/ARCHITECTURE.md):');
        $this->line("     resolve: { alias: { '@admin-kit': path.resolve(__dirname, 'vendor/posio/admin-kit/resources/js') } }");
        $this->line("     server: { fs: { allow: ['vendor/posio/admin-kit'] } }");
        $this->line('');
        $this->line('4. Wire up admin.js using the resolvePage() helper — see stubs/admin-entry.js.stub.');
        $this->line('');
        $this->line('5. Create your own account: php artisan tinker, then');
        $this->line('     app(Posio\\AdminKit\\Services\\AccountService::class)->createAccount(\'My company\', $user);');
    }
}
