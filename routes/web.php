<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::middleware('permission:dashboard.view')->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    });

    // The permission-filtered module launcher.
    Route::get('workspace', HomeController::class)->name('home');
});

require __DIR__.'/auth.php';
