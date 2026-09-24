<?php

namespace Posio\CabinetKit\Tests\Host;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * База тестов пакета, которые гоняются внутри проекта-хоста: приложение
 * поднимается из bootstrap/app.php хоста, со всеми его миграциями, моделью
 * пользователя и переопределениями конфига. Проверяется именно то, что
 * получит посетитель этого проекта, а не пакет в вакууме.
 */
abstract class HostTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $password = 'Passw0rd123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSafeTestDatabase();

        // Страницы отдаёт корневой шаблон с ассетами сборки, а тесты гоняются и до неё;
        // серверный рендер тоже требует запущенного процесса, которого при тестах нет.
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);

        Mail::fake();
        Notification::fake();
        Http::preventStrayRequests();

        // Хост, оставивший вход за собой, эти маршруты пакета не регистрирует.
        if (! config('cabinet-kit.auth_routes', true)) {
            $this->markTestSkipped("The host owns the auth routes ('auth_routes' => false).");
        }

        // Каждый тест задаёт режим регистрации сам, а не наследует выбранный хостом.
        config(['cabinet-kit.registration' => 'closed']);
    }

    // Второй рубеж после проверки при старте: конфиг хоста мог подменить базу уже при загрузке приложения.
    protected function assertSafeTestDatabase(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('CabinetKit tests may run only with APP_ENV=testing.');
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        $safeSqlite = $connection === 'sqlite' && $database === ':memory:';
        $safeNamedDatabase = in_array($connection, ['mysql', 'mariadb', 'pgsql', 'sqlsrv'], true)
            && preg_match('/(^|[._-])test([._-]|$)/i', $database) === 1;

        if (! $safeSqlite && ! $safeNamedDatabase) {
            throw new RuntimeException("Unsafe test database [{$connection}:{$database}].");
        }
    }

    protected function userModel(): string
    {
        return config('cabinet-kit.user_model');
    }

    protected function makeUser(bool $verified = true, array $attributes = [])
    {
        return $this->userModel()::forceCreate([
            'name' => 'Test User',
            'email' => 'user-'.Str::lower(Str::random(10)).'@example.com',
            'password' => Hash::make($this->password),
            'email_verified_at' => $verified ? now() : null,
            ...$attributes,
        ]);
    }

    // Администратор платформы со своим паролем: засеянные учётки до смены пароля кабинет не пускает.
    protected function makeSystemAdministrator()
    {
        $admin = $this->makeUser();
        $admin->setSystemRole('System administrator');

        return $admin->fresh();
    }

    protected function findUserByEmail(string $email)
    {
        return $this->userModel()::query()->where('email', $email)->firstOrFail();
    }

    protected function cabinetRoute(string $name, array $parameters = []): string
    {
        return route(config('cabinet-kit.route_name_prefix', 'cabinet-kit.').$name, $parameters);
    }
}
