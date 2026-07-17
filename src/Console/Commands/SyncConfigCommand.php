<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Package config values that live in the host's own config/cabinet-kit.php
 * never get overwritten on `composer update` (nothing publishes over them).
 * This command only diagnoses drift after an update added new top-level
 * keys — it never rewrites the host's file, to avoid clobbering comments
 * or values a human wrote.
 */
class SyncConfigCommand extends Command
{
    protected $signature = 'cabinet-kit:sync-config';
    protected $description = 'List config/cabinet-kit.php keys the installed package version added that are missing locally.';

    public function handle(): int
    {
        $hostPath = config_path('cabinet-kit.php');
        $packagePath = __DIR__.'/../../../config/cabinet-kit.php';

        if (! File::exists($hostPath)) {
            $this->warn('config/cabinet-kit.php is not published yet — run cabinet-kit:install first.');
            return self::FAILURE;
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
}
