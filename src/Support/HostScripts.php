<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * Скрипты сопровождения проекта: деплой, сборка фронта, сброс кэшей, релиз.
 *
 * Скрипты общие для всех проектов и принадлежат пакету: несут метку обслуживания
 * и перезаписываются из стабов на каждом обновлении кабинета, так что правка
 * стаба сама доезжает до всех проектов. Всё, чем проект отличается, живёт в его
 * собственных файлах — настройках скриптов и проверках перед релизом: их пакет
 * кладёт один раз и больше не трогает.
 *
 * Из файлов проекта пакет трогает ровно одно — шаг своих тестов в проверках
 * перед релизом: без него поломка входа уходит в релиз незамеченной.
 */
class HostScripts
{
    public const CHECKS = 'scripts/pre-push-checks.sh';

    public const RELEASE = 'release.bat';

    public const PROJECT_CONF = 'scripts/host-scripts.conf';

    // Маркер, по которому проверки перед релизом узнаются как уже гоняющие тесты пакета.
    public const TEST_COMMAND = 'cabinet-kit:test';

    /** Скрипты пакета: путь в проекте => стаб. */
    public const MANAGED = [
        'deploy' => 'deploy.stub',
        'build' => 'build.stub',
        'build.bat' => 'build.bat.stub',
        'scripts/kit-build.mjs' => 'kit-build.mjs.stub',
        'cc' => 'cc.stub',
        'cc.bat' => 'cc.bat.stub',
        self::RELEASE => 'release.bat.stub',
    ];

    /** Файлы проекта: путь в проекте => стаб, кладётся только при отсутствии. */
    protected const PROJECT_OWNED = [
        self::PROJECT_CONF => 'host-scripts.conf.stub',
        self::CHECKS => 'pre-push-checks.sh.stub',
    ];

    /**
     * Узнают версии скриптов, разложенные пакетом до того, как скрипты стали его
     * собственностью, и их копии, подогнанные под проект. Всё, что проекту в них
     * было нужно, теперь задаётся его настройками скриптов.
     */
    protected const LEGACY_SIGNATURES = [
        'deploy' => ['Базовая версия от CabinetKit', 'Деплой на прод: обновление кода из git', 'Деплой прода и препрода из git'],
        'build' => ['Сборка фронта проекта под текущее окружение'],
        'build.bat' => ['Сборка фронта проекта под текущее окружение'],
        'cc' => ['Прод-аналог cc.bat'],
        'cc.bat' => ['php artisan permission:cache-reset'],
        self::RELEASE => ['one-step patch release for any git repository'],
    ];

    /**
     * @return string[] пути созданных файлов
     */
    public static function scaffold(): array
    {
        $created = [];

        foreach (self::MANAGED + self::PROJECT_OWNED as $name => $stub) {
            if (HostUpdateLaunchers::writeStub($name, $stub)) {
                $created[] = $name;
            }
        }

        return $created;
    }

    public static function isManaged(string $name): bool
    {
        return array_key_exists($name, self::MANAGED);
    }

    /**
     * Приводит скрипты с меткой обслуживания к стабам текущей версии пакета.
     *
     * @return string[] пути обновлённых файлов
     */
    public static function refreshManaged(): array
    {
        return HostUpdateLaunchers::refreshFiles(self::MANAGED);
    }

    /**
     * Забирает под управление пакета скрипты без метки, узнанные как прежние
     * версии пакетных. Прежний файл остаётся рядом копией: подгонку под проект
     * из него переносят в настройки скриптов проекта.
     *
     * Скрипт без метки и без узнаваемой подписи — собственный скрипт проекта с тем
     * же именем: он не трогается.
     *
     * @return string[] пути заменённых файлов
     */
    public static function adoptLegacy(): array
    {
        $adopted = [];

        foreach (self::LEGACY_SIGNATURES as $name => $signatures) {
            $path = base_path($name);

            if (! File::exists($path)) {
                continue;
            }

            $contents = File::get($path);

            if (str_contains($contents, HostUpdateLaunchers::MANAGED_MARKER) || ! self::containsAny($contents, $signatures)) {
                continue;
            }

            // Первая копия ценнее следующих: в ней подгонка, сделанная ещё руками.
            if (! File::exists($path.'.bak')) {
                File::copy($path, $path.'.bak');
            }

            File::put($path, HostUpdateLaunchers::withPlatformLineEndings(
                $name,
                File::get(HostUpdateLaunchers::stubPath(self::MANAGED[$name])),
            ));

            if (HostUpdateLaunchers::isShellScript($name)) {
                @chmod($path, 0755);
            }

            $adopted[] = $name;
        }

        return $adopted;
    }

    /**
     * Добавляет тесты пакета первым шагом в проверки перед релизом, написанные
     * до них. Шаг встаёт сразу за объявлением функции провала, которой он
     * пользуется.
     *
     * @return bool|null true — шаг добавлен, false — уже был, null — встроить не удалось
     */
    public static function wireTestsIntoReleaseChecks(): ?bool
    {
        $path = base_path(self::CHECKS);

        if (! File::exists($path)) {
            return false;
        }

        $contents = File::get($path);

        if (str_contains($contents, self::TEST_COMMAND)) {
            return false;
        }

        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $step = $eol.$eol.'step "Вход и регистрация CabinetKit"'.$eol
            .'php artisan '.self::TEST_COMMAND.' || fail "тесты CabinetKit"';

        $updated = preg_replace('/^fail\(\)\s*\{.*?^\}[ \t]*$/ms', '$0'.str_replace('$', '\\$', $step), $contents, 1, $count);

        if ($updated === null || $count === 0) {
            return null;
        }

        File::put($path, $updated);

        return true;
    }

    // Состояние для диагностики: гоняются ли тесты пакета при релизе проекта.
    public static function releaseRunsPackageTests(): bool
    {
        $release = base_path(self::RELEASE);
        $checks = base_path(self::CHECKS);

        return File::exists($release)
            && str_contains(File::get($release), 'pre-push-checks.sh')
            && File::exists($checks)
            && str_contains(File::get($checks), self::TEST_COMMAND);
    }

    protected static function containsAny(string $contents, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($contents, $needle)) {
                return true;
            }
        }

        return false;
    }
}
