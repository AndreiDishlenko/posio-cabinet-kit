# Задача: установка пакета одной командой

Рабочее задание на версию **0.4.0**. Цель — чтобы установка в новый проект
сводилась к:

```bash
composer require posio/cabinet-kit
php artisan cabinet-kit:install
npm run dev
```

и кабинет открывался без единой ручной правки хоста.

Документ описывает, что для этого нужно изменить в пакете. Основан на реальной
установке в проект `duck` (Laravel 12 + Inertia + Vue 3), где после
`composer require` потребовалось **девять** ручных действий и белый экран
диагностировался с нуля.

---

## 1. Что сломалось при реальной установке

| # | Что пришлось сделать руками | Почему пакет это не покрыл |
|---|---|---|
| 0 | Удалить `routes/auth.php` хоста и auth-часть `routes/settings.php` | Пакет **безусловно** регистрирует `login`/`register`/`password.*`/`verification.*`; у любого стартер-кита они уже есть → `LogicException` на `route:cache` |
| 1 | Поправить `vite_entry` в конфиге | Дефолт жёстко зашит как `resources/_admin/js/admin.js`, хост назвал entry по своей конвенции |
| 2 | Переписать entry-файл (резолвер страниц, шина событий, импорт scss) | Стаб копируется только если файла нет; в существующий entry ничего не вливается |
| 3 | Добавить в `vite.config.ts` alias, `fs.allow`, entry в `input` | `stubs/vite-alias-snippet.js` — файл-комментарий, не исполняемый код |
| 4 | Добавить glob пакета в `tailwind.config.js` | То же — только текстовая инструкция |
| 5 | Подключить 3 трейта к модели `User` | Печатается как ручной шаг |
| 6 | Установить npm-пакет `mitt` | Composer не ставит npm-зависимости |
| 7 | Опубликовать конфиг `spatie/laravel-permission` и включить `teams` | Печатается как «оставшийся шаг», **после** того как миграции уже прогнаны |
| 8 | Настроить HTTPS для dev-сервера Vite | Не покрыто вообще; при HTTPS-хосте даёт Mixed Content и белый экран |

Плюс дефект порядка в текущей установке: `migrate` вызывается до того, как
пользователю сказали включить teams. Если миграции разрешений уже прогнались с
`teams => false`, таблицы окажутся без `team_id`, и починить это можно только
откатом.

---

## 2. Принцип решения

Каждый ручной шаг устраняется одним из трёх способов, в порядке
предпочтения:

1. **Убрать необходимость шага** — перенести код в пакет (плагин Vite,
   Tailwind-пресет, фабрика приложения), чтобы хост подключал одну строку
   вместо трёх правок.
2. **Автоматизировать шаг** в команде установки — с подтверждением и бэкапом,
   если правится существующий файл хоста.
3. **Диагностировать шаг** новой командой `cabinet-kit:doctor`, если
   автоматизировать нельзя.

Инвариант «no copy-on-install» сохраняется: всё новое (плагин, пресет, фабрика)
читается прямо из `vendor/`, копируется по-прежнему только конфиг и файлы,
которые хост дальше правит сам.

---

## 3. Изменения в пакете

### 3.1. Vite-плагин вместо файла-комментария

Новый файл `resources/vite/cabinet-kit.js`:

```js
import fs from 'fs';
import path from 'path';

const PACKAGE_DIR = 'vendor/posio/cabinet-kit';

export default function cabinetKit(options = {}) {
	const root = options.root ?? process.cwd();
	const packageDir = path.resolve(root, PACKAGE_DIR);

	return {
		name: 'cabinet-kit',
		config() {
			const config = {
				resolve: {
					alias: { '@cabinet-kit': path.join(packageDir, 'resources/js') },
				},
				// Dev-сервер по умолчанию отказывается отдавать файлы вне корня
				// проекта — без этого исходники из vendor не собираются.
				server: { fs: { allow: [root, packageDir] } },
			};

			const https = resolveHttps(options.https, root);
			if (https) {
				config.server.https = https;
				// Страница по https не подключит сокет по ws — HMR молча умрёт.
				config.server.hmr = { protocol: 'wss', ...(options.hmr ?? {}) };
			}

			return config;
		},
	};
}

function resolveHttps(option, root) {
	if (!option) return null;
	if (typeof option === 'object' && option.key && option.cert) {
		return { key: fs.readFileSync(option.key), cert: fs.readFileSync(option.cert) };
	}

	// Локальный стенд держит пару сертификатов в каталоге, названном по домену
	// проекта. На машине без него молча остаёмся на http, чтобы общий конфиг
	// не ронял сборку у других разработчиков.
	const domain = typeof option === 'string' ? option : path.basename(root);
	const dir = option?.certDir ?? `F:/OpenServer/data/ssl/projects/${domain}`;
	const key = `${dir}/cert.key`;
	const cert = `${dir}/cert.crt`;

	if (!fs.existsSync(key) || !fs.existsSync(cert)) {
		console.warn(`[cabinet-kit] Сертификат для "${domain}" не найден — dev-сервер останется на http.`);
		return null;
	}

	return { key: fs.readFileSync(key), cert: fs.readFileSync(cert) };
}
```

Хост подключает одной строкой:

```js
import cabinetKit from './vendor/posio/cabinet-kit/resources/vite/cabinet-kit.js';

plugins: [
	laravel({ input: ['resources/_main/js/main.ts', 'resources/_admin/js/cabinet.ts'] }),
	vue(),
	cabinetKit({ https: true }),
],
```

**Важное ограничение:** плагин не может дописать entry в `laravel({ input })` —
этот массив обрабатывается плагином Laravel в его собственном хуке `config()`.
Добавление entry в `input` остаётся единственной ручной правкой `vite.config`
(её берёт на себя установщик, см. 3.5).

Удалить `stubs/vite-alias-snippet.js` — он замещается плагином.

### 3.2. Tailwind-пресет

Новый файл в корне пакета `tailwind-preset.cjs`:

```js
// Утилиты Tailwind внутри шаблонов пакета иначе просто не генерируются.
module.exports = {
	content: ['./vendor/posio/cabinet-kit/resources/js/**/*.vue'],
};
```

Расширение `.cjs` намеренно: конфиг Tailwind у хоста бывает и ESM, и CJS, а
CommonJS-модуль корректно грузится обоими (`require(...)` и default-импортом).

Хост:

```js
import cabinetKitPreset from './vendor/posio/cabinet-kit/tailwind-preset.cjs';

export default {
	presets: [cabinetKitPreset],
	content: [ /* свои пути */ ],
};
```

Tailwind объединяет `content` пресета со своим — путь пакета больше никогда не
придётся дописывать руками.

### 3.3. Фабрика приложения вместо стаба на 40 строк

Новый файл `resources/js/emitter.js` — снимает npm-зависимость `mitt`:

```js
/**
 * Шина событий с тем же интерфейсом, что у внешнего микропакета. Встроена,
 * чтобы установка не требовала ставить npm-зависимость ради полутора десятков
 * строк, которые связывают бургер-кнопку и боковое меню.
 */
export function createEmitter() {
	const handlers = new Map();

	return {
		on(type, handler) {
			const existing = handlers.get(type);
			existing ? existing.push(handler) : handlers.set(type, [handler]);
		},
		off(type, handler) {
			const existing = handlers.get(type);
			if (existing) existing.splice(existing.indexOf(handler) >>> 0, 1);
		},
		emit(type, event) {
			(handlers.get(type) || []).slice().forEach((handler) => handler(event));
			(handlers.get('*') || []).slice().forEach((handler) => handler(type, event));
		},
	};
}
```

Новый файл `resources/js/createApp.js`:

```js
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';

import { createEmitter } from './emitter.js';
import { resolveCabinetKitPage } from './resolvePage.js';
import '../scss/cabinet-kit.scss';

export function createCabinetKitApp({ overrides = {}, title, progress, setup: hostSetup } = {}) {
	return createInertiaApp({
		title,
		progress: progress ?? { color: '#4B5563' },
		resolve: (name) => resolveCabinetKitPage(
			name,
			overrides,
			import.meta.glob('./pages/**/*.vue', { eager: true }),
		),
		setup({ el, App, props, plugin }) {
			const app = createApp({ render: () => h(App, props) });

			app.use(plugin);
			app.use(ZiggyVue);
			app.config.globalProperties.$emitter = createEmitter();

			hostSetup?.(app);
			app.mount(el);
		},
	});
}
```

Новый стаб `stubs/cabinet-entry.js.stub` целиком:

```js
import { createCabinetKitApp } from '@cabinet-kit/createApp.js';
// Загружается после стилей пакета (они импортируются фабрикой выше), поэтому
// правила отсюда выигрывают по каскаду.
import '../scss/cabinet-kit-overrides.scss';

createCabinetKitApp({
	overrides: import.meta.glob('../overrides/**/*.vue', { eager: true }),
});
```

Глоб переопределений обязан остаться в файле хоста — он резолвится
относительно вызывающего файла и должен быть статически анализируем. Глоб
страниц пакета переезжает внутрь пакета, поэтому хост больше не знает про путь
`@cabinet-kit/pages`.

Выигрыш: будущие изменения (новый плагин Vue, новое глобальное свойство) не
требуют правок в проектах-потребителях.

### 3.4. Конфликт имён маршрутов

Главная причина падения установки. Добавить в `config/cabinet-kit.php`:

```php
// Регистрировать ли встроенные маршруты аутентификации. Ставить false, если у
// хоста уже есть свой набор (стартер-кит Laravel) — иначе имена столкнутся и
// кэширование маршрутов упадёт.
'auth_routes' => true,
```

В `routes/cabinet.php` обернуть guest-группу и группу verification:

```php
if (config('cabinet-kit.auth_routes', true)) {
    Route::middleware('guest')->group(function () { /* ... */ });
}
```

Отдельно: пакет вешает `password.update` на POST `reset-password`, тогда как
стартер-кит Laravel 12 использует для этого `password.store`, а `password.update`
занимает под смену пароля в настройках. Переименование в `password.store`
уберёт одно столкновение из десяти и приблизит именование к конвенции
фреймворка — сделать вместе с 0.4.0, это ломающее изменение.

Детектор в установщике:

```php
protected const BUNDLED_ROUTE_NAMES = [
    'login', 'register', 'logout',
    'password.request', 'password.email', 'password.reset', 'password.update',
    'verification.notice', 'verification.verify', 'verification.send',
];

protected function detectRouteConflicts(): array
{
    $conflicts = [];

    foreach (File::glob(base_path('routes/*.php')) as $file) {
        $contents = File::get($file);

        foreach (self::BUNDLED_ROUTE_NAMES as $name) {
            if (str_contains($contents, "->name('{$name}')")) {
                $conflicts[$name][] = basename($file);
            }
        }
    }

    return $conflicts;
}
```

При найденных конфликтах спросить, а не падать:

```
Хост уже определяет маршруты аутентификации:
  login            routes/auth.php
  password.update  routes/settings.php, routes/auth.php
  ...

  [c] Использовать аутентификацию CabinetKit — удалить routes/auth.php и auth-часть routes/settings.php
  [h] Оставить аутентификацию хоста — выставить 'auth_routes' => false
  [a] Прервать установку
```

Вариант `[c]` дополнительно удаляет осиротевшие контроллеры
(`app/Http/Controllers/Auth/`, `app/Http/Requests/Auth/`,
`app/Http/Controllers/Settings/PasswordController.php`) и тесты
`tests/Feature/Auth/` — тоже с подтверждением и списком файлов.

### 3.5. Правка конфигов хоста

Три точечные правки, каждая идемпотентная, с подтверждением и `.bak`:

- **`vite.config.{js,ts}`** — добавить импорт плагина, вызов `cabinetKit()` в
  `plugins`, и entry в массив `laravel({ input })`. Если разобрать файл не
  удалось — не ломать, а напечатать точный фрагмент.
- **`tailwind.config.{js,ts}`** — добавить импорт пресета и `presets: [...]`.
- **`app/Models/User.php`** — подключить трейт (см. 3.6).

Правка JS регулярками хрупкая — поэтому обязательны: проверка «уже сделано»,
показ diff перед записью, бэкап, и корректный откат на печать инструкции.

### 3.6. Один трейт вместо трёх

Новый `src/Traits/IsCabinetKitUser.php`:

```php
trait IsCabinetKitUser
{
    use HasAccount;
    use HasCustomFields;
    use HasSettings;
}
```

Три существующих трейта остаются публичными (кто-то мог подключить их
поштучно), но и README, и установщик используют один составной. Патч модели
сводится к вставке одного `use`-импорта и одного имени в `use`-строку класса.

### 3.7. Порядок шагов установки

Переписать `handle()` — сейчас `migrate` идёт третьим, а требование включить
teams печатается последним:

```
 1. publish config/cabinet-kit.php
 2. spatie/laravel-permission: publish конфига + включить 'teams' => true
    ↳ если таблицы разрешений уже созданы без team_id — остановиться и объяснить откат
 3. детект конфликтов маршрутов → диалог выбора (3.4)
 4. определить/выбрать vite entry → записать реальный путь обратно в конфиг
 5. заскаффолдить или обновить entry, overrides/, cabinet-kit-overrides.scss
 6. правки vite.config / tailwind.config / User (3.5, 3.6)
 7. migrate
 8. сидинг ролей
 9. cabinet-kit:doctor — и печать итога
```

Включение teams (шаг 2) обязано стоять **до** `migrate`:

```php
$contents = File::get(config_path('permission.php'));

if (preg_match("/'teams'\s*=>\s*false/", $contents)) {
    File::put(config_path('permission.php'), preg_replace("/'teams'\s*=>\s*false/", "'teams' => true", $contents, 1));
}
```

### 3.8. Определение entry вместо жёсткого дефолта

```php
protected function resolveViteEntry(): string
{
    $configured = config('cabinet-kit.vite_entry');
    if (File::exists(base_path($configured))) {
        return $configured;
    }

    // Хост, следующий конвенции «модуль → [модуль].ts», уже имеет такой файл;
    // создавать рядом второй с другим именем — верный способ получить 404.
    $candidates = File::glob(base_path('resources/_*/js/*.{ts,js}'), GLOB_BRACE);

    // → предложить выбрать из найденных или создать новый, затем записать
    //   выбранный путь обратно в config/cabinet-kit.php
}
```

Дефолт в конфиге поменять на `resources/_admin/js/cabinet.ts` — имя модуля
совпадает с именем кабинета и с конвенцией `[module].ts` в проектах-потребителях.

Заодно убрать хардкод в провайдере: `resource_path('_admin/overrides')`
вычисляется из нового ключа конфига `overrides_path`, иначе проект с другим
именем модуля теряет переопределения на стороне сервера.

### 3.9. Новая команда `cabinet-kit:doctor`

Самое ценное добавление: превращает «белый экран, пять неизвестных причин» в
чеклист. Проверки, каждая с однострочной подсказкой как чинить:

- конфиг опубликован, все ключи на месте (переиспользовать `sync-config`);
- файл из `vite_entry` существует на диске;
- entry использует фабрику пакета;
- `vite.config` содержит плагин (или alias) и entry в `input`;
- `tailwind.config` содержит пресет или glob пакета;
- модель `User` подключает трейт;
- конфиг разрешений опубликован, `teams => true`, таблицы имеют `team_id`;
- таблицы аккаунтов существуют, роли засеяны;
- **нет столкновений имён маршрутов** — воспроизвести ровно то, что падает при
  кэшировании:

```php
try {
    app('router')->getRoutes()->toSymfonyRouteCollection();
} catch (\LogicException $e) {
    // столкновение имён — вывести сообщение и подсказать про 'auth_routes' => false
}
```

- npm-зависимости из `package.json` хоста (`ziggy-js`, `@iconify/vue`).

Ненулевой код выхода при любой красной проверке, чтобы команду можно было
поставить в CI.

---

## 4. Версия и обновление потребителей

Версия **0.4.0**. Ломающие изменения (по нулевой мажорной версии — минорный
бамп сигнализирует о них):

- удалён `stubs/vite-alias-snippet.js`;
- полностью заменён `stubs/cabinet-entry.js.stub`;
- дефолт `vite_entry` изменён;
- добавлены ключи конфига `auth_routes`, `overrides_path`;
- `mitt` больше не нужен;
- переименование `password.update` → `password.store` (если делать, см. 3.4).

Обновить перед тегом: `docs/CHANGELOG.md`, `README.md` (раздел установки
сжимается до трёх команд), `docs/ARCHITECTURE.md` (пункты 4–6 «контракта с
хостом» заменяются на плагин и пресет), `docs/EXTENDING.md`.

Отдельным пунктом — миграция уже установленного `duck`: заменить ручные правки
`vite.config.ts` на `cabinetKit()`, `tailwind.config.js` на пресет, entry на
четырёхстрочный вариант, три трейта на составной, удалить `mitt` из
`package.json`.

---

## 5. Проверка результата

Критерий приёмки — на **чистом** Laravel 12 со стартер-китом (то есть с уже
существующими `routes/auth.php` и `routes/settings.php`):

```bash
composer require posio/cabinet-kit
php artisan cabinet-kit:install      # отвечая на 2-3 вопроса
npm install && npm run dev
```

и дальше:

1. `/cabinet/login` отдаёт 200 и рисует форму;
2. `php artisan route:cache` проходит без `LogicException`;
3. `php artisan cabinet-kit:doctor` — всё зелёное;
4. регистрация на `/cabinet/register` создаёт пользователя и аккаунт;
5. дашборд и настройки открываются, боковое меню и бургер работают;
6. повторный запуск `cabinet-kit:install` ничего не ломает и не дублирует.

Проверить оба сценария детектора конфликтов — и «заменить своей
аутентификацией», и «оставить хостовую» (во втором `/cabinet/login` должен
отсутствовать, а кабинет — пускать по логину хоста).