<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * Tailwind v3 does not merge `content` from presets: the resolved config takes
 * the first `content` it finds, and that is always the host's own. So the globs
 * declared in the preset never reach a host that has a `content` key — i.e.
 * every host — and the package templates silently compile to nothing while the
 * theme from the same preset applies normally. The glob therefore has to live
 * in the host config, which is what this helper installs and keeps current.
 */
class HostTailwindConfig
{
    // One glob for every package resource: moving templates between folders
    // inside the package must never again leave them out of the scan.
    public const CONTENT_GLOB = './vendor/posio/cabinet-kit/resources/**/*.{vue,js,ts}';

    // Any package path already sitting in `content` — from an older installer
    // or added by hand.
    protected const PACKAGE_ENTRY_PATTERN = '#^([ \t]*)([\'"])\./vendor/posio/cabinet-kit/resources/[^\'"]*\2,?[ \t]*(\R)#m';

    public static function path(): ?string
    {
        foreach (['tailwind.config.ts', 'tailwind.config.js', 'tailwind.config.cjs'] as $file) {
            if (File::exists($path = base_path($file))) {
                return $path;
            }
        }

        return null;
    }

    public static function contentCoversPackage(string $contents): bool
    {
        return str_contains($contents, self::CONTENT_GLOB);
    }

    /**
     * The host config with the package glob installed, or null when no
     * `content` array could be found to put it in.
     */
    public static function withPackageContentGlob(string $contents): ?string
    {
        if (self::contentCoversPackage($contents)) {
            return $contents;
        }

        $seen = 0;

        $updated = preg_replace_callback(
            self::PACKAGE_ENTRY_PATTERN,
            function (array $matches) use (&$seen): string {
                $seen++;

                // Narrower package paths are replaced rather than kept next to
                // the broad one: those are what stop covering anything once the
                // templates move.
                return $seen === 1
                    ? $matches[1].$matches[2].self::CONTENT_GLOB.$matches[2].','.$matches[3]
                    : '';
            },
            $contents,
        );

        if ($seen > 0) {
            return $updated;
        }

        $patterns = [
            '#(?<![\w$])(content\s*:\s*\[)#',
            '#(?<![\w$])(content\s*:\s*\{[^\[\]]*files\s*:\s*\[)#s',
        ];

        foreach ($patterns as $pattern) {
            $updated = preg_replace_callback(
                $pattern,
                fn (array $matches): string => $matches[1]."\n        '".self::CONTENT_GLOB."',",
                $contents,
                1,
                $count,
            );

            if ($count > 0) {
                return $updated;
            }
        }

        return null;
    }
}
