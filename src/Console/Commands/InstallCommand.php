<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'cabinet-kit:install';
    protected $description = 'One-time setup: publish config, run migrations, seed roles, scaffold the overrides folder.';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'cabinet-kit-config']);

        $this->info('Running migrations (accounts, user_has_accounts, users.settings)...');
        $this->call('migrate');

        $this->seedRolesAndPermissions();
        $this->scaffoldOverridesFolder();
        $this->scaffoldViteEntry();
        $this->printNextSteps();

        return self::SUCCESS;
    }

    protected function seedRolesAndPermissions(): void
    {
        if (! $this->confirm('Seed base roles (Account owner, Administrator, Manager, User) and the manage-account permission?', true)) {
            return;
        }

        (new \Posio\CabinetKit\Database\Seeders\CabinetKitRolesSeeder())->run();
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
        # CabinetKit overrides

        Drop a file here with the same relative path as its counterpart in
        `vendor/posio/cabinet-kit/resources/js/...` to replace it — the page
        resolver (`resolvePage.js`) checks this folder first, and falls back to
        the package version if nothing matches. This is the escape hatch for
        deep customization; for menu items, settings tabs, and permissions,
        prefer editing `config/cabinet-kit.php` instead — it survives `composer
        update` with zero effort.

        Example: `overrides/pages/Settings/UsersTab.vue` replaces the package's
        `resources/js/pages/Settings/UsersTab.vue`.
        MD);

        $this->info('Created resources/_admin/overrides/ (see its README.md).');
    }

    protected function scaffoldViteEntry(): void
    {
        $entry = config('cabinet-kit.vite_entry', 'resources/_admin/js/admin.js');
        $entryPath = base_path($entry);

        if (File::exists($entryPath)) {
            return;
        }

        File::ensureDirectoryExists(dirname($entryPath));
        File::copy(__DIR__.'/../../../stubs/cabinet-entry.js.stub', $entryPath);

        $this->info("Created {$entry} (Inertia entry for the cabinet — wired through resolveCabinetKitPage).");
    }

    protected function printNextSteps(): void
    {
        $this->newLine();
        $this->line('<fg=green>CabinetKit installed.</> Remaining manual steps:');
        $this->line('');
        $this->line('1. If spatie/laravel-permission migrations are not published yet:');
        $this->line('     php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"');
        $this->line('   ...and set \'teams\' => true in config/permission.php BEFORE running them.');
        $this->line('');
        $this->line('2. Add HasAccount + HasSettings + HasCustomFields traits to your User model:');
        $this->line('     use Posio\\CabinetKit\\Traits\\HasAccount;');
        $this->line('     use Posio\\CabinetKit\\Traits\\HasSettings;');
        $this->line('     use Posio\\CabinetKit\\Traits\\HasCustomFields;');
        $this->line('');
        $this->line('3. (Optional) implement MustVerifyEmail on your User model if you want email');
        $this->line('   verification enforced — the verify/resend routes work regardless, but');
        $this->line('   nothing blocks unverified users unless you add the `verified` middleware');
        $this->line('   yourself and your User implements it:');
        $this->line('     use Illuminate\\Contracts\\Auth\\MustVerifyEmail;');
        $this->line('     use Illuminate\\Auth\\MustVerifyEmail as MustVerifyEmailTrait;');
        $this->line('');
        $this->line('4. Add the CabinetKit Vite alias + fs.allow + cabinet entry to vite.config.js (see docs/ARCHITECTURE.md):');
        $this->line("     laravel({ input: [..., '".config('cabinet-kit.vite_entry', 'resources/_admin/js/admin.js')."'] })");
        $this->line("     resolve: { alias: { '@cabinet-kit': path.resolve(__dirname, 'vendor/posio/cabinet-kit/resources/js') } }");
        $this->line("     server: { fs: { allow: ['.', 'vendor/posio/cabinet-kit'] } }");
        $this->line('');
        $this->line('5. Frontend deps the cabinet entry uses: ziggy-js, @iconify/vue, mitt (npm i if missing).');
        $this->line('   If Tailwind utilities should apply inside CabinetKit components, add to tailwind.config content:');
        $this->line("     './vendor/posio/cabinet-kit/resources/js/**/*.vue'");
        $this->line('');
        $this->line('6. Login/register/logout/password-reset/email-verification are bundled — visit');
        $this->line('   /'.config('cabinet-kit.route_prefix', 'cabinet').'/register to create your first');
        $this->line('   user + account. To create an account for an existing user instead:');
        $this->line('     php artisan tinker');
        $this->line('     app(Posio\\CabinetKit\\Services\\AccountService::class)->createAccount(\'My company\', $user);');
    }
}
