<?php

use Illuminate\Support\Facades\Route;
use Posio\CabinetKit\Http\Controllers\AccountController;
use Posio\CabinetKit\Http\Controllers\Auth\LoginController;
use Posio\CabinetKit\Http\Controllers\Auth\PasswordResetController;
use Posio\CabinetKit\Http\Controllers\Auth\RegisterController;
use Posio\CabinetKit\Http\Controllers\Auth\VerificationController;
use Posio\CabinetKit\Http\Controllers\DashboardController;
use Posio\CabinetKit\Http\Controllers\ProfileController;
use Posio\CabinetKit\Http\Controllers\SettingsController;
use Posio\CabinetKit\Http\Middleware\SetPermissionTeam;
use Posio\CabinetKit\Http\Middleware\ShareCabinetKitData;
use Posio\CabinetKit\Http\Middleware\UseCabinetKitRootView;

Route::middleware(['web', UseCabinetKitRootView::class])
    ->prefix(config('cabinet-kit.route_prefix', 'cabinet'))
    ->group(function () {

        // Guest-only auth routes. Names stay Laravel's own unprefixed
        // convention (login, register, ...) so framework internals (the
        // `auth` middleware's redirect-to-login, signed verification links)
        // resolve them without extra config.
        Route::middleware('guest')->group(function () {
            Route::get('login', [LoginController::class, 'showLogin'])->name('login');
            Route::post('login', [LoginController::class, 'login']);

            Route::get('register', [RegisterController::class, 'showRegister'])->name('register');
            Route::post('register', [RegisterController::class, 'register']);

            Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
            Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email');
            Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
            Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update');
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

        Route::middleware(array_merge(
                config('cabinet-kit.middleware', ['web', 'auth']),
                [SetPermissionTeam::class, ShareCabinetKitData::class],
            ))
            ->name(config('cabinet-kit.route_name_prefix', 'cabinet-kit.'))
            ->group(function () {
                Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

                Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
                Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

                Route::post('/account/set', [AccountController::class, 'set'])->name('account.set');
                Route::post('/account/member/invite', [AccountController::class, 'inviteMember'])->name('account.member.invite');
                Route::post('/account/member/role', [AccountController::class, 'setMemberRole'])->name('account.member.role');
                Route::post('/account/member/remove', [AccountController::class, 'removeMember'])->name('account.member.remove');
            });
    });
