<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * Набор тестов пакета в конфиге PHPUnit проекта. Обозреватель тестов редактора
 * и `php artisan test` видят только наборы из этого конфига: без записи тесты
 * входа и регистрации запускаются лишь отдельной командой пакета.
 */
class HostPhpunitConfig
{
    public const SUITE_NAME = 'CabinetKit';

    public const TESTS_DIRECTORY = 'vendor/posio/cabinet-kit/tests/Host';

    public static function path(): ?string
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $name) {
            if (File::exists(base_path($name))) {
                return base_path($name);
            }
        }

        return null;
    }

    public static function hasSuite(string $contents): bool
    {
        return str_contains($contents, self::TESTS_DIRECTORY);
    }

    // Набор встаёт последним, с отступами соседних наборов; null — место вставки не найдено.
    public static function withSuite(string $contents): ?string
    {
        if (! preg_match('/^([ \t]*)<\/testsuites>/m', $contents, $closing, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $outer = $closing[1][0];
        $inner = preg_match('/^([ \t]*)<testsuite[\s>]/m', $contents, $suite) ? $suite[1] : $outer.'    ';
        $step = substr($inner, strlen($outer)) ?: '    ';
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";

        $block = $inner.'<testsuite name="'.self::SUITE_NAME.'">'.$eol
            .$inner.$step.'<directory>'.self::TESTS_DIRECTORY.'</directory>'.$eol
            .$inner.'</testsuite>'.$eol;

        return substr_replace($contents, $block, $closing[0][1], 0);
    }

    /**
     * @return bool|null true — набор добавлен, false — уже был или конфига нет, null — встроить не удалось
     */
    public static function addSuite(): ?bool
    {
        $path = self::path();

        if ($path === null) {
            return false;
        }

        $contents = File::get($path);

        if (self::hasSuite($contents)) {
            return false;
        }

        $updated = self::withSuite($contents);

        if ($updated === null) {
            return null;
        }

        if (! File::exists($path.'.bak')) {
            File::copy($path, $path.'.bak');
        }

        File::put($path, $updated);

        return true;
    }
}
