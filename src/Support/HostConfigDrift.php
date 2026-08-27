<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * Compares the config published into the host project with the one the
 * installed version ships.
 *
 * The published file is copied once at installation and never reconciled
 * afterwards, so the two drift apart in both directions — and only one of them
 * used to be reported. A key the package has since dropped keeps sitting in the
 * project looking like a live setting: nothing reads it, nothing complains, and
 * the next person to edit it spends the afternoon wondering why it changes
 * nothing. A key the package has since added is the harmless direction, because
 * the package's own value is merged in underneath.
 */
class HostConfigDrift
{
    protected static ?array $host = null;

    protected static ?array $package = null;

    /** Top-level keys the project still carries that the installed version no longer reads. */
    public static function obsoleteKeys(): array
    {
        return array_diff_key(static::host(), static::package());
    }

    /** Top-level keys the installed version introduced that the project has not adopted. */
    public static function missingKeys(): array
    {
        return array_diff_key(static::package(), static::host());
    }

    protected static function host(): array
    {
        if (static::$host !== null) {
            return static::$host;
        }

        $path = config_path('cabinet-kit.php');

        return static::$host = File::exists($path) ? (array) require $path : [];
    }

    protected static function package(): array
    {
        return static::$package ??= (array) require dirname(__DIR__, 2).'/config/cabinet-kit.php';
    }
}