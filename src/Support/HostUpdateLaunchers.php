<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Обновление пакета — несколько шагов в жёстком порядке через composer, artisan
 * и npm. Лаунчер в корне проекта избавляет от необходимости помнить (и путать)
 * этот порядок на каждом релизе.
 *
 * Лаунчеров два, и кладутся оба независимо от текущей ОС: проект разрабатывают
 * на одной платформе, а разворачивают по ssh на другой, и лаунчер едет туда
 * вместе с репозиторием.
 *
 * Существующий файл никогда не перезаписывается: хост мог адаптировать его под
 * свой деплой.
 */
class HostUpdateLaunchers
{
    /** Имя файла в корне проекта => стаб пакета. */
    protected const LAUNCHERS = [
        'updcab.bat' => 'updcab.bat.stub',
        'updcab' => 'updcab.stub',
    ];

    /**
     * @return string[] имена созданных файлов
     */
    public static function scaffold(): array
    {
        $created = [];

        foreach (self::LAUNCHERS as $name => $stub) {
            if (self::write($name, $stub)) {
                $created[] = $name;
            }
        }

        return $created;
    }

    /**
     * Лаунчер из версий до модулей обновляет только сам пакет, и установленный
     * модуль с ним так и остаётся на старом релизе. Файл хоста не заменяется
     * целиком — меняется ровно шаг обновления, остальные правки хоста живут.
     *
     * @return string[] имена изменённых файлов
     */
    public static function updateAllPosioPackages(): array
    {
        $patched = [];

        foreach (array_keys(self::LAUNCHERS) as $name) {
            $path = base_path($name);

            if (! File::exists($path)) {
                continue;
            }

            $contents = File::get($path);
            // Сначала строки-подписи шагов (без кавычек — они сами в кавычках),
            // затем сам вызов: там маска в кавычках, иначе её раскроет оболочка.
            $updated = preg_replace(
                ['#^(\s*(?:echo|announce)\b[^\r\n]*composer update )posio/cabinet-kit\b#m', '#((?:call composer|composer_cmd) update )posio/cabinet-kit\b#'],
                ['$1posio/*', '$1"posio/*"'],
                $contents,
            );

            if ($updated !== null && $updated !== $contents) {
                File::put($path, $updated);
                $patched[] = $name;
            }
        }

        return $patched;
    }

    protected static function write(string $name, string $stub): bool
    {
        $path = base_path($name);

        if (File::exists($path)) {
            return false;
        }

        $source = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'stubs'.DIRECTORY_SEPARATOR.$stub;

        if (! File::exists($source)) {
            return false;
        }

        $contents = File::get($source);
        $isShell = ! str_ends_with($name, '.bat');

        // Скрипт с виндовыми переводами строк ядро запустить отказывается
        // («bad interpreter»), а записан файл может быть с любой платформы.
        if ($isShell) {
            $contents = str_replace("\r\n", "\n", $contents);
        }

        File::put($path, $contents);

        if ($isShell) {
            @chmod($path, 0755);
            self::markExecutableInGit($name);
        }

        return true;
    }

    /**
     * У Windows нет бита прав, поэтому закоммиченный там лаунчер приезжает на
     * ssh-хост неисполняемым. Git хранит этот бит отдельно от файловой системы
     * и принимает его с любой платформы.
     */
    protected static function markExecutableInGit(string $name): void
    {
        if (! File::isDirectory(base_path('.git'))) {
            return;
        }

        Process::path(base_path())->run("git update-index --add --chmod=+x {$name}");
    }
}
