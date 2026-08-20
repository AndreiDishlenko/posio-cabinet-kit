<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Posio\CabinetKit\Support\CabinetRedirects;
use Posio\CabinetKit\Support\FrontendDependencies;
use Posio\CabinetKit\Support\HostTailwindConfig;

/**
 * What `composer update` cannot bring along: the wiring the package needs
 * inside the host's own files. npm dependencies and the Tailwind content glob
 * are repaired in place; config/cabinet-kit.php is only diagnosed, never
 * rewritten, so no hand-written value or comment there is ever clobbered.
 *
 * The installer registers this command as a composer post-update hook, so it
 * must never fail the surrounding `composer update` — everything it cannot do
 * is reported as a warning and it always exits successfully.
 */
class SyncConfigCommand extends Command
{
    protected $signature = 'cabinet-kit:sync-config';
    protected $description = 'Re-apply CabinetKit wiring in the host project (npm deps, Tailwind content) and report new config keys.';

    public function handle(): int
    {
        $this->syncPackageJsonDependencies();
        $this->syncTailwindContent();

        $hostPath = config_path('cabinet-kit.php');
        $packagePath = __DIR__.'/../../../config/cabinet-kit.php';

        if (! File::exists($hostPath)) {
            $this->warn('config/cabinet-kit.php is not published yet — run cabinet-kit:install first.');
            return self::SUCCESS;
        }

        $this->publishRedirectsConfig();

        $hostConfig = require $hostPath;
        $packageConfig = require $packagePath;

        $missing = array_diff_key($packageConfig, $hostConfig);

        if (empty($missing)) {
            $this->info('config/cabinet-kit.php is up to date with the installed package version.');
            return self::SUCCESS;
        }

        $this->warn('New config keys introduced by the installed cabinet-kit version — add these to your config/cabinet-kit.php:');
        $this->newLine();

        foreach ($missing as $key => $value) {
            $this->line("    '{$key}' => ".Str::of(var_export($value, true))->replace("\n", ' ').",");
        }

        return self::SUCCESS;
    }

    /**
     * A config file introduced by a package release never appears in a project
     * that is only updated — composer refreshes vendor and nothing else. It is
     * created here, carrying over the landing pages the host had chosen back
     * when they lived in the main config file. A page whose route the project
     * no longer registers is dropped rather than carried: that stale name is
     * what used to break the sign-in flow.
     */
    protected function publishRedirectsConfig(): void
    {
        $path = config_path('cabinet-kit-redirects.php');

        if (File::exists($path)) {
            return;
        }

        $contents = File::get(__DIR__.'/../../../config/cabinet-kit-redirects.php');
        $carried = [];
        $dropped = [];

        foreach (CabinetRedirects::LEGACY_KEYS as $key => $legacyKey) {
            $target = trim((string) config("cabinet-kit.{$legacyKey}", ''));

            if ($target === '') {
                continue;
            }

            if (! Route::has($target)) {
                $dropped[$legacyKey] = $target;
                continue;
            }

            $contents = preg_replace("/'{$key}'\\s*=>\\s*'[^']*'/", "'{$key}' => '{$target}'", $contents, 1, $count);

            if ($count > 0) {
                $carried[$key] = $target;
            }
        }

        File::put($path, $contents);
        $this->info('Created config/cabinet-kit-redirects.php.');

        foreach ($carried as $key => $target) {
            $this->line("    kept your '{$key}' landing page: {$target}");
        }

        foreach ($dropped as $legacyKey => $target) {
            $this->warn("    '{$legacyKey}' pointed at '{$target}', which this app does not register — replaced with the package default.");
        }

        if ($carried !== [] || $dropped !== []) {
            $this->warn('    Landing pages are read from the new file only — delete the old keys from config/cabinet-kit.php.');
        }
    }

    // A stale glob here has no visible symptom other than package templates
    // rendering unstyled, so it is repaired without asking.
    protected function syncTailwindContent(): void
    {
        $path = HostTailwindConfig::path();
        if (! $path) {
            return;
        }

        $contents = File::get($path);
        if (HostTailwindConfig::contentCoversPackage($contents)) {
            return;
        }

        $updated = HostTailwindConfig::withPackageContentGlob($contents);
        if ($updated === null) {
            $this->warn("Could not patch the tailwind.config content array — add '".HostTailwindConfig::CONTENT_GLOB."' to it manually, or package templates stay unstyled.");
            return;
        }

        $this->backupAndPut($path, $updated);
        $this->info('Patched '.basename($path).' with the CabinetKit content glob.');
        $this->warn('Rebuild your assets: npm run dev/build.');
    }

    protected function syncPackageJsonDependencies(): void
    {
        $path = base_path('package.json');
        if (! File::exists($path)) {
            $this->warn('package.json was not found — install CabinetKit npm dependencies manually: '.implode(' ', array_keys(FrontendDependencies::PACKAGES)).'.');
            return;
        }

        $json = json_decode(File::get($path), true);
        if (! is_array($json)) {
            $this->warn('package.json could not be parsed — install CabinetKit npm dependencies manually: '.implode(' ', array_keys(FrontendDependencies::PACKAGES)).'.');
            return;
        }

        $json['dependencies'] ??= [];
        $added = [];

        foreach (FrontendDependencies::PACKAGES as $package => $version) {
            if (isset($json['dependencies'][$package]) || isset($json['devDependencies'][$package])) {
                continue;
            }

            $json['dependencies'][$package] = $version;
            $added[] = $package;
        }

        if ($added === []) {
            return;
        }

        ksort($json['dependencies']);

        $this->backupAndPut($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
        $this->info('Patched package.json with CabinetKit npm dependencies: '.implode(', ', $added).'.');
        $this->warn('Run npm install before npm run dev/build.');
    }

    protected function backupAndPut(string $path, string $contents): void
    {
        if (! File::exists($path.'.bak')) {
            File::copy($path, $path.'.bak');
        }

        File::put($path, $contents);
    }
}
