<?php

use App\Http\Controllers\Auth\PasskeyManagementController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureUserIsAdmin::class])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified', EnsureUserIsAdmin::class])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::get('passkeys', PasskeyManagementController::class)
    ->middleware('auth')
    ->name('passkey.manage');

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('passkey.manage'),
        'manage' => route('passkey.manage'),
    ]);
})->name('well-known.passkeys');
