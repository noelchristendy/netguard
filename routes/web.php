<?php

use App\Http\Controllers\ExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Models\Role;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\FilterController;

Route::get('/', fn () => redirect()->route('login'));

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', Role::allow(...Role::ALL)])
    ->name('dashboard');

Route::middleware(['auth', Role::allow(...Role::ALL)])
    ->group(function () {
        Route::get('/logs/decisions', [LogController::class, 'decisions'])
            ->name('logs.decisions');
    });

Route::middleware(['auth', Role::allow(...Role::AUDIT_VIEWERS)])
    ->group(function () {
        Route::get('/logs/audits', [LogController::class, 'audits'])
            ->name('logs.audits');
    });

Route::middleware(['auth', Role::allow(...Role::ALL)])
    ->group(function () {
        Route::get('/export/decisions', [ExportController::class, 'decisions'])
            ->name('export.decisions');
    });

Route::middleware(['auth', Role::allow(...Role::AUDIT_VIEWERS)])
    ->group(function () {
        Route::get('/export/audits', [ExportController::class, 'audits'])
            ->name('export.audits');
    });

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

Route::middleware(['auth', Role::allow(...Role::RULE_MANAGERS)])->group(function () {
    Route::patch('rules/{rule}/toggle', [RuleController::class, 'toggle'])->name('rules.toggle');
    Route::post('rules/{rule}/move', [RuleController::class, 'move'])->name('rules.move');
    Route::resource('rules', RuleController::class)->except(['show']);

    Route::get('filter', [FilterController::class, 'index'])->name('filter.index');
    Route::post('filter/upload', [FilterController::class, 'upload'])->name('filter.upload');
    Route::post('filter/single', [FilterController::class, 'single'])->name('filter.single');
});

require __DIR__.'/auth.php';