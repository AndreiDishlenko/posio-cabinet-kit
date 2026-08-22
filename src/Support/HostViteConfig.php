<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * The package's Vue and SCSS sources import each other through aliases that
 * only the bundled Vite plugin declares (`@/js`, `@/scss`, `@/_admin`). A host
 * that merely aliases `@cabinet-kit` — the wiring older installers wrote, back
 * when the entry pulled in a single flat stylesheet — resolves those imports
 * against its own `resources/` instead, so the kit's stylesheet entry never
 * compiles and its pages render unstyled. The plugin is therefore mandatory,
 * and this helper is what installs it and keeps it there.
 */
class HostViteConfig
{
    public const PLUGIN_PATH = './vendor/posio/cabinet-kit/resources/vite/cabinet-kit.js';

    // One canonical call for both the install and the update path, so a project
    // repaired later ends up with the same config as one installed today.
    public const PLUGIN_CALL = 'cabinetKit({ https: true })';

    public static function path(): ?string
    {
        foreach (['vite.config.ts', 'vite.config.js'] as $file) {
            if (File::exists($path = base_path($file))) {
                return $path;
            }
        }

        return null;
    }

    public static function usesPlugin(string $contents): bool
    {
        return str_contains($contents, 'cabinetKit(');
    }

    public static function hasEntry(string $contents, string $entry): bool
    {
        return str_contains($contents, "'{$entry}'") || str_contains($contents, "\"{$entry}\"");
    }

    /**
     * The host config with the plugin imported and registered, or null when no
     * `plugins` array could be found to register it in.
     */
    public static function withPlugin(string $contents): ?string
    {
        $updated = $contents;

        if (! str_contains($updated, self::PLUGIN_PATH)) {
            $updated = "import cabinetKit from '".self::PLUGIN_PATH."';\n".$updated;
        }

        if (self::usesPlugin($updated)) {
            return $updated;
        }

        $updated = preg_replace('/plugins\s*:\s*\[/', "plugins: [\n        ".self::PLUGIN_CALL.",", $updated, 1, $count);

        return ($count ?? 0) > 0 ? $updated : null;
    }

    /**
     * The host config with the cabinet entry added to laravel-vite-plugin's
     * input, or null when no input declaration could be found.
     */
    public static function withEntry(string $contents, string $entry): ?string
    {
        if (self::hasEntry($contents, $entry)) {
            return $contents;
        }

        $updated = preg_replace_callback('/input\s*:\s*\[([^\]]*)\]/s', function (array $matches) use ($entry): string {
            $inner = rtrim($matches[1]);
            $comma = trim($inner) === '' || str_ends_with(trim($inner), ',') ? '' : ',';

            return "input: [{$inner}{$comma} '{$entry}']";
        }, $contents, 1, $count);

        if ($count > 0) {
            return $updated;
        }

        $updated = preg_replace("/input\s*:\s*(['\"])([^'\"]+)\\1/", "input: ['$2', '{$entry}']", $contents, 1, $count);

        return $count > 0 ? $updated : null;
    }
}
