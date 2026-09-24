<?php

// Тесты пакета запускаются из проекта-хоста: приложение, модель пользователя и
// зависимости берутся хоста, а свой автозагрузчик пакет в проект не приносит —
// у установленной зависимости autoload-dev не читается.

$hostRoot = getenv('APP_BASE_PATH') ?: getcwd();

if (! is_file($hostRoot.'/vendor/autoload.php')) {
    fwrite(STDERR, "CabinetKit tests run from the host project root: php artisan cabinet-kit:test\n");
    exit(1);
}

require $hostRoot.'/vendor/autoload.php';

spl_autoload_register(function (string $class) {
    $prefix = 'Posio\\CabinetKit\\Tests\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($file)) {
        require $file;
    }
});

$appEnv = getenv('APP_ENV') ?: '';
$connection = getenv('DB_CONNECTION') ?: '';
$database = getenv('DB_DATABASE') ?: '';

if ($appEnv !== 'testing') {
    fwrite(STDERR, "Refusing to start CabinetKit tests unless APP_ENV=testing.\n");
    exit(1);
}

$safeSqlite = $connection === 'sqlite' && $database === ':memory:';
$safeNamedDatabase = in_array($connection, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)
    && preg_match('/(^|[._-])test([._-]|$)/i', $database) === 1;

if (! $safeSqlite && ! $safeNamedDatabase) {
    fwrite(STDERR, "Refusing to start CabinetKit tests with unsafe database [{$connection}:{$database}].\n");
    exit(1);
}
