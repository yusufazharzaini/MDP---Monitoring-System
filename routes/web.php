<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MasterData\DepartmentController;
use App\Http\Controllers\MasterData\MaterialCategoryController;
use App\Http\Controllers\MasterData\MaterialController;
use App\Http\Controllers\MasterData\PlantController;
use App\Http\Controllers\MasterData\SupplierContactController;
use App\Http\Controllers\MasterData\SupplierController;
use App\Http\Controllers\MasterData\UomController;
use App\Http\Controllers\MasterData\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::middleware('permission:dashboard.view')->group(function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    });

    // The permission-filtered module launcher.
    Route::get('workspace', HomeController::class)->name('home');

    /*
     * Master data.
     *
     * The permission middleware is the outer gate; every controller action also
     * runs its policy, so a route that ever loses its middleware still cannot
     * be reached by the wrong user.
     */
    Route::middleware('permission:supplier.view')->group(function (): void {
        Route::resource('suppliers', SupplierController::class);

        Route::controller(SupplierContactController::class)
            ->prefix('suppliers/{supplier}/contacts')
            ->name('supplier-contacts.')
            ->group(function (): void {
                Route::post('/', 'store')->name('store');
                Route::put('{contact}', 'update')->name('update');
                Route::delete('{contact}', 'destroy')->name('destroy');
            });
    });

    Route::middleware('permission:plant.view')
        ->resource('plants', PlantController::class);

    Route::middleware('permission:warehouse.view')
        ->resource('warehouses', WarehouseController::class);

    Route::middleware('permission:material.view')->group(function (): void {
        Route::resource('materials', MaterialController::class);
        Route::resource('material-categories', MaterialCategoryController::class)->except('show');
        Route::resource('uoms', UomController::class)->except('show');
    });

    Route::middleware('permission:user.view')
        ->resource('departments', DepartmentController::class)
        ->except('show');
});

require __DIR__.'/auth.php';
