<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Models\Role;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/dashboard', fn () => view('dashboard'))
    ->middleware('auth')
    ->name('dashboard');

Route::middleware(['auth', Role::allow(...Role::USER_MANAGERS)])
    ->prefix('users')
    ->name('users.')
    ->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::patch('/{user}/role', [UserController::class, 'updateRole'])->name('role');
        Route::post('/{user}/lock', [UserController::class, 'lock'])->name('lock');
        Route::post('/{user}/unlock', [UserController::class, 'unlock'])->name('unlock');
    });

require __DIR__.'/auth.php';