<?php

use Illuminate\Support\Facades\Route;
use Posio\CabinetKit\CabinetKit;
use Posio\CabinetKit\Http\Controllers\Admin\PermissionsController;
use Posio\CabinetKit\Http\Controllers\Api\DictionariesApiController;
use Posio\CabinetKit\Http\Controllers\Admin\UsersController;
use Posio\CabinetKit\Http\Controllers\AccountController;
use Posio\CabinetKit\Http\Controllers\Api\SeoApiController;
use Posio\CabinetKit\Http\Controllers\Api\SiteSettingsApiController;
use Posio\CabinetKit\Http\Controllers\HomeController;
use Posio\CabinetKit\Http\Controllers\LocaleController;
use Posio\CabinetKit\Http\Controllers\PackageAssetController;
use Posio\CabinetKit\Http\Controllers\SeoPageController;
use Posio\CabinetKit\Http\Controllers\SiteSettingsController;
use Posio\CabinetKit\Http\Controllers\Auth\LoginController;
use Posio\CabinetKit\Http\Controllers\Auth\PasswordResetController;
use Posio\CabinetKit\Http\Controllers\Auth\RegisterController;
use Posio\CabinetKit\Http\Controllers\Auth\RegistrationApprovalController;
use Posio\CabinetKit\Http\Controllers\Auth\SocialAuthController;
use Posio\CabinetKit\Http\Controllers\Auth\VerificationController;
use Posio\CabinetKit\Http\Controllers\ProfileController;
use Posio\CabinetKit\Http\Controllers\SettingsController;
use Posio\CabinetKit\Http\Controllers\SystemPasswordController;
use Posio\CabinetKit\Http\Middleware\ApplyCabinetKitLocale;
use Posio\CabinetKit\Http\Middleware\CanSystemPermission;
use Posio\CabinetKit\Http\Middleware\NewGuest;
use Posio\CabinetKit\Http\Middleware\RequireRegistrationNotClosed;
use Posio\CabinetKit\Http\Middleware\UseCabinetKitRootView;

// Ни один маршрут пакета не объявляется замыканием: хост обязан сохранить
// возможность закэшировать свои маршруты, а замыкания не сериализуются.
Route::get('cabinet-assets/{path}', [PackageAssetController::class, 'cabinet'])
    ->where('path', '.*')->name('cabinet-kit.assets');

Route::get('brand-assets/{path}', [PackageAssetController::class, 'brand'])
    ->where('path', '.*')->name('cabinet-kit.brand-assets');

Route::middleware(['web', UseCabinetKitRootView::class, ApplyCabinetKitLocale::class])
    ->prefix(config('cabinet-kit.route_prefix', 'cabinet'))
    ->group(function () {
        Route::post('setlocale', [LocaleController::class, 'update'])->name('app.setlocale');

        // Guest-only auth routes. Names stay Laravel's own unprefixed
        // convention (login, register, ...) so framework internals (the
        // `auth` middleware's redirect-to-login, signed verification links)
        // resolve them without extra config.
        // Вошедшего ведём на стартовую страницу кабинета, а не на «домашнюю» хоста,
        // которую подставил бы штатный гостевой посредник фреймворка.
        if (config('cabinet-kit.auth_routes', true)) {
            Route::middleware(NewGuest::class)->group(function () {
                Route::get('login', [LoginController::class, 'showLogin'])->name('login');
                Route::post('login', [LoginController::class, 'login']);

                Route::middleware(RequireRegistrationNotClosed::class)->group(function () {
                    Route::get('register', [RegisterController::class, 'showRegister'])->name('register');
                    Route::post('register', [RegisterController::class, 'register']);
                });

                Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
                Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
                Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
                Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.store');

                // Social sign-in stays registered even without credentials (the
                // controller answers 404 then), so the sign-in page can resolve
                // these names unconditionally.
                // Первый вход через провайдера заводит учётку, поэтому при закрытой
                // регистрации провайдеры закрыты целиком, в том числе для заведённых.
                Route::middleware(RequireRegistrationNotClosed::class)->group(function () {
                    Route::get('auth/google', [SocialAuthController::class, 'googleRedirect'])->name('auth.google');
                    Route::get('auth/google/callback', [SocialAuthController::class, 'googleCallback'])->name('auth.google.callback');

                    Route::get('auth/apple', [SocialAuthController::class, 'appleRedirect'])->name('auth.apple');
                    // Apple posts its return from its own origin, so no session token rides along.
                    Route::post('auth/apple/callback', [SocialAuthController::class, 'appleCallback'])
                        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
                        ->name('auth.apple.callback');
                });
            });

            // Подтверждение почты удостоверяется подписью URL, а не сессией: ссылку можно
            // открыть на другом устройстве, где пользователь не вошёл или вошёл под другим
            // аккаунтом. Поэтому маршрут вне группы только для вошедших.
            Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
                ->middleware('signed')
                ->name('verification.verify');

            Route::middleware('auth')->group(function () {
                Route::post('logout', [LoginController::class, 'logout'])->name('logout');
                Route::post('email/verify/switch-account', [VerificationController::class, 'switchAccount'])
                    ->name('verification.switch-account');

                Route::get('email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
                Route::post('email/verification-notification', [VerificationController::class, 'send'])
                    ->middleware('throttle:6,1')
                    ->name('verification.send');

                // Ссылка одобрения регистрации из письма администратору: подпись удостоверяет
                // ссылку, вход и системное право — того, кто одобряет (право проверяет контроллер).
                Route::get('registration/approve/{id}/{hash}', [RegistrationApprovalController::class, 'approve'])
                    ->middleware(['signed', RequireRegistrationNotClosed::class])
                    ->name('registration.approve');
            });
        }

        // Тот же стек получают маршруты модулей и хоста через CabinetKit::cabinetRoutes().
        Route::middleware(app(CabinetKit::class)->cabinetMiddleware())
            ->name(config('cabinet-kit.route_name_prefix', 'cabinet-kit.'))
            ->group(function () {
                Route::get('/', HomeController::class)->name('home');
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

                // Справочники модулей и хоста одним ответом; адрес не совпадает с тем,
                // под которым хосты заводили свой эндпоинт, — тот продолжает работать.
                Route::get('/api/kit-dictionaries', [DictionariesApiController::class, 'index'])->name('api.dictionaries');

                // The one pair of routes the gate above lets a seeded account
                // through to, so it can replace the password it was installed with.
                Route::get('/system-password', [SystemPasswordController::class, 'screen'])->name('system-password');
                Route::post('/system-password', [SystemPasswordController::class, 'update'])->name('system-password.update');

                Route::middleware(CanSystemPermission::class.':sysper-users')->group(function () {
                    Route::get('/users', [UsersController::class, 'index'])->name('users');
                    Route::put('/users', [UsersController::class, 'update'])->name('users.update');
                    Route::post('/users', [UsersController::class, 'update'])->name('users.update.post');
                    // Допуск самостоятельной регистрации из кабинета — альтернатива ссылке
                    // из письма (см. RegistrationApprovalService).
                    Route::post('/users/approve', [UsersController::class, 'approve'])->name('users.approve');
                    // Ручное подтверждение почты — если письмо со ссылкой подтверждения не дошло.
                    Route::post('/users/verifyemail', [UsersController::class, 'verifyEmail'])->name('users.verifyemail');
                });

                // Операторские разделы: бренд публичной части и кабинета,
                // постраничная SEO-мета. Страницы данные подтягивают сами через
                // соседнюю api-группу за тем же правом.
                Route::middleware(CanSystemPermission::class.':sysper-site')->group(function () {
                    Route::get('/sitesettings', [SiteSettingsController::class, 'site'])->name('sitesettings');
                    Route::get('/cabinetsettings', [SiteSettingsController::class, 'cabinet'])->name('cabinetsettings');
                    Route::get('/seo', [SeoPageController::class, 'index'])->name('seo');

                    Route::prefix('api')->name('api.')->group(function () {
                        Route::get ('/sitesettings',              [SiteSettingsApiController::class, 'index'])->name('sitesettings.index');
                        Route::post('/sitesettings/update',       [SiteSettingsApiController::class, 'update'])->name('sitesettings.update');
                        Route::post('/sitesettings/theme',        [SiteSettingsApiController::class, 'updateTheme'])->name('sitesettings.theme');
                        Route::post('/sitesettings/image',        [SiteSettingsApiController::class, 'uploadImage'])->name('sitesettings.image');
                        Route::post('/sitesettings/image/delete', [SiteSettingsApiController::class, 'deleteImage'])->name('sitesettings.image.delete');

                        Route::get ('/seo/getdata',      [SeoApiController::class, 'get'])->name('seodata');
                        Route::post('/seo/update',       [SeoApiController::class, 'update'])->name('seodata.update');
                        Route::post('/seo/delete',       [SeoApiController::class, 'delete'])->name('seodata.delete');
                        Route::post('/seo/restore',      [SeoApiController::class, 'restore'])->name('seodata.restore');
                        Route::post('/seo/generatemeta', [SeoApiController::class, 'generateMeta'])->name('seodata.generatemeta');
                        Route::post('/seo/createsitemaps', [SeoApiController::class, 'createSitemaps'])->name('createsitemaps');
                    });
                });

                Route::middleware(CanSystemPermission::class.':sysper-roles')->group(function () {
                    Route::get('/permissions', [PermissionsController::class, 'system'])->name('permissions');
                    Route::get('/permissions/account', [PermissionsController::class, 'account'])->name('permissions.account');
                    Route::post('/permissions/toggle', [PermissionsController::class, 'toggle'])->name('permissions.toggle');
                    Route::post('/permissions', [PermissionsController::class, 'store'])->name('permissions.store');
                    Route::put('/permissions', [PermissionsController::class, 'rename'])->name('permissions.rename');
                    Route::post('/permissions/rename', [PermissionsController::class, 'rename'])->name('permissions.rename.post');
                });

                Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
                Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update.post');
                Route::post('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
                Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

                Route::post('/account/set', [AccountController::class, 'set'])->name('account.set');
                Route::post('/account', [AccountController::class, 'update'])->name('account.update');
                Route::post('/account/logo', [AccountController::class, 'addLogo'])->name('account.addlogo');
                Route::post('/account/member/invite', [AccountController::class, 'inviteMember'])->name('account.member.invite');
                Route::post('/account/member/role', [AccountController::class, 'setMemberRole'])->name('account.member.role');
                Route::post('/account/member/remove', [AccountController::class, 'removeMember'])->name('account.member.remove');
            });
    });
