<?php

use Illuminate\Support\Facades\Route;
use Posio\AdminKit\Http\Controllers\AccountController;
use Posio\AdminKit\Http\Controllers\DashboardController;
use Posio\AdminKit\Http\Controllers\SettingsController;
use Posio\AdminKit\Http\Middleware\SetPermissionTeam;
use Posio\AdminKit\Http\Middleware\ShareAdminKitData;

Route::middleware(array_merge(
        config('admin-kit.middleware', ['web', 'auth']),
        [SetPermissionTeam::class, ShareAdminKitData::class],
    ))
    ->prefix(config('admin-kit.route_prefix', 'admin'))
    ->name(config('admin-kit.route_name_prefix', 'admin-kit.'))
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/settings', SettingsController::class)->name('settings');

        Route::post('/account/set', [AccountController::class, 'set'])->name('account.set');
        Route::post('/account/member/invite', [AccountController::class, 'inviteMember'])->name('account.member.invite');
        Route::post('/account/member/role', [AccountController::class, 'setMemberRole'])->name('account.member.role');
        Route::post('/account/member/remove', [AccountController::class, 'removeMember'])->name('account.member.remove');
    });
