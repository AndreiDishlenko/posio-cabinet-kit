<?php

namespace Posio\CabinetKit\Support;

use Illuminate\Support\Facades\File;

/**
 * Базовые скрипты сопровождения проекта: деплой, сброс кэшей, релиз и набор
 * проверок перед ним. Кладутся один раз, если файла ещё нет: дальше скрипт
 * принадлежит проекту и подгоняется под его сервер.
 *
 * Из готовых файлов проекта пакет трогает ровно одно — шаг своих тестов в
 * проверках перед релизом: без него поломка входа уходит в релиз незамеченной.
 */
class HostScripts
{
    public const CHECKS = 'scripts/pre-push-checks.sh';

    public const RELEASE = 'release.bat';

    // Маркер, по которому проверки перед релизом узнаются как уже гоняющие тесты пакета.
    public const TEST_COMMAND = 'cabinet-kit:test';

    /** Путь в проекте => стаб пакета. */
    protected const SCRIPTS = [
        'deploy' => 'deploy.stub',
        'cc' => 'cc.stub',
        'cc.bat' => 'cc.bat.stub',
        self::RELEASE => 'release.bat.stub',
        self::CHECKS => 'pre-push-checks.sh.stub',
    ];

    // Узнаёт скрипт релиза того же происхождения, что и стаб пакета.
    protected const RELEASE_SIGNATURE = 'one-step patch release for any git repository';

    /**
     * @return string[] пути созданных файлов
     */
    public static function scaffold(): array
    {
        $created = [];

        foreach (self::SCRIPTS as $name => $stub) {
            if (HostUpdateLaunchers::writeStub($name, $stub)) {
                $created[] = $name;
            }
        }

        return $created;
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

    /**
     * Скрипт релиза старше проверок перед релизом их не запускает, и тесты
     * пакета в нём молча не выполняются. Такой заменяется стабом: это тот же
     * самодостаточный скрипт, только новее, — прежний остаётся рядом копией.
     *
     * @return bool|null true — заменён, false — замена не нужна, null — чужой скрипт, запускающий проверки не умеет
     */
    public static function upgradeReleaseScript(): ?bool
    {
        $path = base_path(self::RELEASE);

        if (! File::exists($path)) {
            return false;
        }

        $contents = File::get($path);

        if (self::releaseRunsChecks($contents)) {
            return false;
        }

        if (! str_contains($contents, self::RELEASE_SIGNATURE)) {
            return null;
        }

        if (! File::exists($path.'.bak')) {
            File::copy($path, $path.'.bak');
        }

        File::put($path, HostUpdateLaunchers::withPlatformLineEndings(
            self::RELEASE,
            File::get(HostUpdateLaunchers::stubPath(self::SCRIPTS[self::RELEASE])),
        ));

        return true;
    }

    // Состояние для диагностики: гоняются ли тесты пакета при релизе проекта.
    public static function releaseRunsPackageTests(): bool
    {
        $release = base_path(self::RELEASE);
        $checks = base_path(self::CHECKS);

        return File::exists($release)
            && self::releaseRunsChecks(File::get($release))
            && File::exists($checks)
            && str_contains(File::get($checks), self::TEST_COMMAND);
    }

    protected static function releaseRunsChecks(string $contents): bool
    {
        return str_contains($contents, 'pre-push-checks.sh');
    }
}
