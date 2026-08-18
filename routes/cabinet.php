<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;
use Posio\CabinetKit\Http\Controllers\Admin\LogsController;
use Posio\CabinetKit\Http\Controllers\Admin\PermissionsController;
use Posio\CabinetKit\Http\Controllers\Admin\UsersController;
use Posio\CabinetKit\Http\Controllers\AccountController;
use Posio\CabinetKit\Http\Controllers\Auth\LoginController;
use Posio\CabinetKit\Http\Controllers\Auth\PasswordResetController;
use Posio\CabinetKit\Http\Controllers\Auth\RegisterController;
use Posio\CabinetKit\Http\Controllers\Auth\VerificationController;
use Posio\CabinetKit\Http\Controllers\DashboardController;
use Posio\CabinetKit\Http\Controllers\ProfileController;
use Posio\CabinetKit\Http\Controllers\SettingsController;
use Posio\CabinetKit\Http\Middleware\CanSystemPermission;
use Posio\CabinetKit\Http\Middleware\SetPermissionTeam;
use Posio\CabinetKit\Http\Middleware\ShareCabinetKitData;
use Posio\CabinetKit\Http\Middleware\UseCabinetKitRootView;

Route::get('cabinet-assets/{path}', function (string $path) {
    $assetRoot = realpath(__DIR__.'/../public/cabinet-assets');
    $assetPath = $assetRoot ? realpath($assetRoot.DIRECTORY_SEPARATOR.$path) : false;

    if (! $assetRoot || ! $assetPath || ! str_starts_with($assetPath, $assetRoot.DIRECTORY_SEPARATOR)) {
        abort(404);
    }

    return response()->file($assetPath);
})->where('path', '.*')->name('cabinet-kit.assets');

Route::middleware(['web', UseCabinetKitRootView::class])
    ->prefix(config('cabinet-kit.route_prefix', 'cabinet'))
    ->group(function () {
        Route::post('setlocale', function () {
            $locale = request()->string('locale')->toString();
            $locales = collect(config('cabinet-kit.translations.locales', []))
                ->keys()
                ->map(fn ($code) => (string) $code)
                ->all();

            abort_unless(in_array($locale, $locales, true), 422);

            session(['locale' => $locale]);
            app()->setLocale($locale);

            if ($user = request()->user()) {
                if (method_exists($user, 'setSetting')) {
                    $user->setSetting('locale', $locale);
                }
            }

            return response()->json(['locale' => $locale])
                ->withCookie(Cookie::forever('locale', $locale));
        })->name('app.setlocale');

        // Guest-only auth routes. Names stay Laravel's own unprefixed
        // convention (login, register, ...) so framework internals (the
        // `auth` middleware's redirect-to-login, signed verification links)
        // resolve them without extra config.
        if (config('cabinet-kit.auth_routes', true)) {
            Route::middleware('guest')->group(function () {
                Route::get('login', [LoginController::class, 'showLogin'])->name('login');
                Route::post('login', [LoginController::class, 'login']);

                Route::get('register', [RegisterController::class, 'showRegister'])->name('register');
                Route::post('register', [RegisterController::class, 'register']);

                Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
                Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
                Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
                Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.store');
            });

            Route::middleware('auth')->group(function () {
                Route::post('logout', [LoginController::class, 'logout'])->name('logout');

                Route::get('email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
                Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
                    ->middleware('signed')
                    ->name('verification.verify');
                Route::post('email/verification-notification', [VerificationController::class, 'send'])
                    ->middleware('throttle:6,1')
                    ->name('verification.send');
            });
        }

        Route::middleware(array_merge(
                config('cabinet-kit.middleware', ['web', 'auth']),
                [SetPermissionTeam::class, ShareCabinetKitData::class],
            ))
            ->name(config('cabinet-kit.route_name_prefix', 'cabinet-kit.'))
            ->group(function () {
                Route::get('/', fn () => redirect()->route(config('cabinet-kit.home_route', 'cabinet-kit.users')))->name('home');
                Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

                Route::middleware(CanSystemPermission::class.':sysper-users')->group(function () {
                    Route::get('/users', [UsersController::class, 'index'])->name('users');
                    Route::put('/users', [UsersController::class, 'update'])->name('users.update');
                    Route::post('/users', [UsersController::class, 'update'])->name('users.update.post');
                });

                Route::middleware(CanSystemPermission::class.':sysper-roles')->group(function () {
                    Route::get('/permissions', [PermissionsController::class, 'system'])->name('permissions');
                    Route::get('/permissions/account', [PermissionsController::class, 'account'])->name('permissions.account');
                    Route::post('/permissions/toggle', [PermissionsController::class, 'toggle'])->name('permissions.toggle');
                    Route::post('/permissions', [PermissionsController::class, 'store'])->name('permissions.store');
                    Route::put('/permissions', [PermissionsController::class, 'rename'])->name('permissions.rename');
                    Route::post('/permissions/rename', [PermissionsController::class, 'rename'])->name('permissions.rename.post');
                });

                Route::middleware(CanSystemPermission::class.':sysper-log-view')->group(function () {
                    Route::get('/logs', [LogsController::class, 'index'])->name('logs');
                });

                Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
                Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update.post');
                Route::post('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
                Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

                Route::post('/account/set', [AccountController::class, 'set'])->name('account.set');
                Route::post('/account/member/invite', [AccountController::class, 'inviteMember'])->name('account.member.invite');
                Route::post('/account/member/role', [AccountController::class, 'setMemberRole'])->name('account.member.role');
                Route::post('/account/member/remove', [AccountController::class, 'removeMember'])->name('account.member.remove');
            });
    });
