<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * A two-segment version in the host's `require` is not the range it looks like:
 * composer reads `0.3` as the single release 0.3.0, so every later tag of that
 * line stays invisible and `composer update` keeps reporting the project as
 * current. Nothing points at the cause — the host simply never leaves the first
 * release — which is why such a constraint is repaired, not only reported.
 */
class HostComposerJson
{
    public const PACKAGE = 'posio/cabinet-kit';

    public static function path(): ?string
    {
        return File::exists($path = base_path('composer.json')) ? $path : null;
    }

    public static function read(): ?array
    {
        $path = self::path();

        if ($path === null) {
            return null;
        }

        $json = json_decode(File::get($path), true);

        return is_array($json) ? $json : null;
    }

    public static function constraint(array $json): ?string
    {
        $constraint = $json['require'][self::PACKAGE] ?? null;

        return is_string($constraint) ? trim($constraint) : null;
    }

    /**
     * The range a partial version constraint was meant to be, or null when the
     * constraint already accepts more than one release. A full three-segment
     * pin (`0.3.31`) is left alone — that one is a deliberate choice, not the
     * accident this repairs.
     */
    public static function widenedConstraint(string $constraint): ?string
    {
        if (! preg_match('/^v?(\d+)(\.\d+)?$/', trim($constraint), $matches)) {
            return null;
        }

        return '^'.$matches[1].($matches[2] ?? '');
    }

    public static function receivesNewReleases(): bool
    {
        $json = self::read();
        $constraint = $json === null ? null : self::constraint($json);

        return $constraint === null || self::widenedConstraint($constraint) === null;
    }

    public static function runsSyncConfigAfterUpdate(array $json): bool
    {
        foreach ((array) data_get($json, 'scripts.post-update-cmd', []) as $hook) {
            if (is_string($hook) && str_contains($hook, 'cabinet-kit:sync-config')) {
                return true;
            }
        }

        return false;
    }

    public static function encode(array $json): string
    {
        return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
    }
}
