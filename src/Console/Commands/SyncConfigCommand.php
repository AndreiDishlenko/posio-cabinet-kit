<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
