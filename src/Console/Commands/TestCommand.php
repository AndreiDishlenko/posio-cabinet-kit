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
        {--db-database=:memory: : Test database name — it is wiped and migrated from scratch}
        {--full : Show the complete PHPUnit output with stack traces instead of the summary}';

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
        $env = [
            'APP_BASE_PATH' => base_path(),
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => (string) $this->option('db-connection'),
            'DB_DATABASE' => (string) $this->option('db-database'),
            'DB_URL' => '',
        ];

        return $this->option('full')
            ? $this->runWithFullOutput($command, $env)
            : $this->runWithSummary($command, $env);
    }

    private function runWithFullOutput(array $command, array $env): int
    {
        $process = new Process($command, base_path(), $env);
        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(fn ($type, $buffer) => $this->output->write($buffer));
    }

    // Проверка перед релизом читается человеком: нужны имена упавших тестов и причина, а не стек на экраны.
    private function runWithSummary(array $command, array $env): int
    {
        $report = tempnam(sys_get_temp_dir(), 'cabinet-kit-junit');
        $command = [...$command, '--no-output', '--log-junit', $report];

        $process = new Process($command, base_path(), $env);
        $process->setTimeout(null);
        $status = $process->run();

        $results = $this->readJunitReport($report);
        @unlink($report);

        // Отчёта нет — phpunit упал раньше тестов (конфиг, автозагрузка): тогда показываем его собственный вывод.
        if ($results === null) {
            $this->error('PHPUnit produced no report (exit code '.$status.'):');
            $this->output->write($process->getOutput().$process->getErrorOutput());

            return $status === 0 ? self::FAILURE : $status;
        }

        ['total' => $total, 'skipped' => $skipped, 'problems' => $problems] = $results;

        if ($problems === []) {
            $this->info("OK: {$total} tests passed".($skipped ? ", {$skipped} skipped" : '').'.');

            return $status;
        }

        $this->error(count($problems)." of {$total} tests failed:");

        // Одна причина на много тестов (сломанная миграция, конфиг) печатается один раз, под ней — её тесты.
        $byReason = [];
        foreach ($problems as $problem) {
            $byReason[$problem['reason']][] = $problem['test'];
        }

        foreach ($byReason as $reason => $tests) {
            $this->newLine();
            $this->line('  '.$reason);
            foreach ($tests as $test) {
                $this->line('    - '.$test);
            }
        }

        $this->newLine();
        $this->line('Stack traces: php artisan cabinet-kit:test --full'.($this->option('filter') ? ' --filter='.$this->option('filter') : ''));

        return $status === 0 ? self::FAILURE : $status;
    }

    private function readJunitReport(string $path): ?array
    {
        if (! is_file($path) || filesize($path) === 0) {
            return null;
        }

        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return null;
        }

        $total = 0;
        $skipped = 0;
        $problems = [];

        foreach ($xml->xpath('//testcase') as $case) {
            $total++;

            if (isset($case->skipped)) {
                $skipped++;
                continue;
            }

            $node = isset($case->error) ? $case->error : (isset($case->failure) ? $case->failure : null);
            if ($node === null) {
                continue;
            }

            $short = $this->shortClass((string) $case['class']);
            $problems[] = [
                'test' => $short.'::'.$case['name'],
                'reason' => $this->reasonLine((string) $node, (string) $node['type']),
            ];
        }

        return compact('total', 'skipped', 'problems');
    }

    // Первая строка текста — имя теста, вторая — «Исключение: сообщение»; стек дальше не нужен.
    private function reasonLine(string $text, string $type): string
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/', $text)),
            fn ($line) => $line !== ''
        ));

        $reason = $lines[1] ?? $lines[0] ?? $type;

        return mb_strlen($reason) > 300 ? mb_substr($reason, 0, 300).'…' : $reason;
    }

    private function shortClass(string $class): string
    {
        return str_replace('Posio\\CabinetKit\\Tests\\Host\\', '', $class);
    }
}
