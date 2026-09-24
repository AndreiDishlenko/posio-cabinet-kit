<?php

namespace Posio\CabinetKit\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Тесты входа и регистрации пакета, прогнанные внутри проекта-хоста: его
 * конфиг, миграции и модель пользователя легко ломают поток авторизации, а
 * собственные тесты хоста этого не покрывают. Команду зовёт проверка перед
 * релизом хоста (scripts/pre-push-checks.sh).
 */
class TestCommand extends Command
{
    protected $signature = 'cabinet-kit:test
        {--filter= : Run only the tests matching this PHPUnit filter}
        {--db-connection=sqlite : Test database connection; anything but in-memory SQLite needs a database name with a "test" marker}
        {--db-database=:memory: : Test database name — it is wiped and migrated from scratch}';

    protected $description = 'Run the CabinetKit auth and registration tests against this host project.';

    public function handle(): int
    {
        // Кэш конфига заменяет собой переменные окружения, и тестовая база молча превратится в рабочую.
        if ($this->laravel->configurationIsCached()) {
            $this->error('Configuration is cached, so the tests would ignore the test database. Run php artisan config:clear first.');

            return self::FAILURE;
        }

        $phpunit = base_path('vendor/phpunit/phpunit/phpunit');

        if (! File::exists($phpunit)) {
            $this->error('PHPUnit is not installed. It is a dev dependency: run composer install without --no-dev.');

            return self::FAILURE;
        }

        $command = [PHP_BINARY, $phpunit, '--configuration', dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'phpunit.host.xml'];

        if ($this->option('filter')) {
            $command[] = '--filter='.$this->option('filter');
        }

        // Artisan уже разложил .env этого проекта по окружению процесса, и phpunit
        // унаследовал бы рабочую базу, — тестовая передаётся явно.
        $process = new Process($command, base_path(), [
            'APP_BASE_PATH' => base_path(),
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => (string) $this->option('db-connection'),
            'DB_DATABASE' => (string) $this->option('db-database'),
            'DB_URL' => '',
        ]);
        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(fn ($type, $buffer) => $this->output->write($buffer));
    }
}
