<?php

namespace Posio\AdminKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Package config values that live in the host's own config/admin-kit.php
 * never get overwritten on `composer update` (nothing publishes over them).
 * This command only diagnoses drift after an update added new top-level
 * keys — it never rewrites the host's file, to avoid clobbering comments
 * or values a human wrote.
 */
class SyncConfigCommand extends Command
{
    protected $signature = 'admin-kit:sync-config';
    protected $description = 'List config/admin-kit.php keys the installed package version added that are missing locally.';

    public function handle(): int
    {
        $hostPath = config_path('admin-kit.php');
        $packagePath = __DIR__.'/../../../config/admin-kit.php';

        if (! File::exists($hostPath)) {
            $this->warn('config/admin-kit.php is not published yet — run admin-kit:install first.');
            return self::FAILURE;
        }

        $hostConfig = require $hostPath;
        $packageConfig = require $packagePath;

        $missing = array_diff_key($packageConfig, $hostConfig);

        if (empty($missing)) {
            $this->info('config/admin-kit.php is up to date with the installed package version.');
            return self::SUCCESS;
        }

        $this->warn('New config keys introduced by the installed admin-kit version — add these to your config/admin-kit.php:');
        $this->newLine();

        foreach ($missing as $key => $value) {
            $this->line("    '{$key}' => ".Str::of(var_export($value, true))->replace("\n", ' ').",");
        }

        return self::SUCCESS;
    }
}
