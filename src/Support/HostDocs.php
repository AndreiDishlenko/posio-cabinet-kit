<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * Инструкции по интеграции доезжают до проекта-потребителя копией, а не ссылкой
 * в vendor: файл внутри проекта видят и человек, и любой ассистент, который
 * читает репозиторий, — до vendor большинство из них не доходит.
 *
 * Копия обновляется при каждой синхронизации конфига, поэтому отдельного шага
 * в процедуре обновления не появляется. Правки хоста в этих файлах не
 * сохраняются: источник правды — пакет, место для своих заметок — свой файл.
 *
 * Указатель в AGENTS.md / CLAUDE.md обновляется по маркерам, всё остальное
 * содержимое этих файлов остаётся нетронутым.
 */
class HostDocs
{
    public const TARGET_DIR = 'docs/cabinet-kit';

    protected const MARKER_START = '<!-- cabinet-kit:docs start -->';

    protected const MARKER_END = '<!-- cabinet-kit:docs end -->';

    /** Файлы, в которые дописывается указатель. Разные ассистенты читают разные. */
    protected const POINTER_FILES = ['AGENTS.md', 'CLAUDE.md'];

    /**
     * @return array{copied: string[], pointers: string[]}
     */
    public static function sync(bool $createPointerFiles = true): array
    {
        $source = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'host';

        if (! File::isDirectory($source)) {
            return ['copied' => [], 'pointers' => []];
        }

        $target = base_path(self::TARGET_DIR);
        File::ensureDirectoryExists($target);

        $copied = [];

        foreach (File::files($source) as $file) {
            $destination = $target.DIRECTORY_SEPARATOR.$file->getFilename();

            if (File::exists($destination) && File::get($destination) === File::get($file->getPathname())) {
                continue;
            }

            File::copy($file->getPathname(), $destination);
            $copied[] = self::TARGET_DIR.'/'.$file->getFilename();
        }

        return [
            'copied' => $copied,
            'pointers' => self::writePointers($createPointerFiles),
        ];
    }

    /**
     * @return string[]
     */
    protected static function writePointers(bool $createMissing): array
    {
        $block = self::pointerBlock();
        $written = [];

        foreach (self::POINTER_FILES as $name) {
            $path = base_path($name);
            $exists = File::exists($path);

            if (! $exists && ! $createMissing) {
                continue;
            }

            $contents = $exists ? File::get($path) : '';

            $updated = str_contains($contents, self::MARKER_START)
                ? self::replaceBlock($contents, $block)
                : rtrim($contents)."\n\n".$block."\n";

            if ($exists && $updated === $contents) {
                continue;
            }

            File::put($path, ltrim($updated, "\n"));
            $written[] = $name;
        }

        return $written;
    }

    protected static function replaceBlock(string $contents, string $block): string
    {
        $pattern = '/'.preg_quote(self::MARKER_START, '/').'.*?'.preg_quote(self::MARKER_END, '/').'/s';

        return preg_replace($pattern, $block, $contents, 1);
    }

    protected static function pointerBlock(): string
    {
        $dir = self::TARGET_DIR;

        return implode("\n", [
            self::MARKER_START,
            '## CabinetKit (posio/cabinet-kit)',
            '',
            'This project uses the `posio/cabinet-kit` package for its cabinet (admin panel),',
            'site settings, and SEO. Before changing anything in those areas — public pages and',
            'their meta, brand/logo/favicon/theme, cabinet pages, menu, roles — **read the',
            "instructions in `{$dir}/`**, starting with `{$dir}/README.md`.",
            '',
            'Those files are generated from the installed package version and are overwritten on',
            'every update: do not edit them, and do not copy package sources out of',
            '`vendor/posio/cabinet-kit`.',
            self::MARKER_END,
        ]);
    }
}
