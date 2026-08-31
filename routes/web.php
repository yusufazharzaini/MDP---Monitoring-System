<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Delivery\DeliveryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MasterData\DepartmentController;
use App\Http\Controllers\MasterData\MaterialCategoryController;
use App\Http\Controllers\MasterData\MaterialController;
use App\Http\Controllers\MasterData\PlantController;
use App\Http\Controllers\MasterData\SupplierContactController;
use App\Http\Controllers\MasterData\SupplierController;
use App\Http\Controllers\MasterData\UomController;
use App\Http\Controllers\MasterData\WarehouseController;
use App\Http\Controllers\Problem\CorrectiveActionController;
use App\Http\Controllers\Problem\DeliveryProblemController;
use App\Http\Controllers\Problem\ProblemAttachmentController;
use App\Http\Controllers\PurchaseOrder\PurchaseOrderController;
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

    /*
     * Purchase orders.
     *
     * A purchase order is never deleted, so the resource excludes destroy;
     * cancellation is the only exit and it carries its own permission.
     */
    Route::middleware('permission:po.view')->group(function (): void {
        Route::resource('purchase-orders', PurchaseOrderController::class)->except('destroy');

        Route::controller(PurchaseOrderController::class)
            ->prefix('purchase-orders/{purchase_order}')
            ->name('purchase-orders.')
            ->group(function (): void {
                Route::post('submit', 'submit')->name('submit');
                Route::post('approve', 'approve')->name('approve');
                Route::post('cancel', 'cancel')->name('cancel');
            });
    });

    /*
     * Deliveries.
     *
     * Receiving always starts from a purchase order, so create and store are
     * nested under one; everything else addresses the receipt itself.
     */
    Route::middleware('permission:delivery.view')->group(function (): void {
        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
        Route::get('deliveries/{delivery}/edit', [DeliveryController::class, 'edit'])->name('deliveries.edit');
        Route::put('deliveries/{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update');
        Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel'])->name('deliveries.cancel');

        Route::get('purchase-orders/{purchase_order}/receive', [DeliveryController::class, 'create'])
            ->name('deliveries.create');
        Route::post('purchase-orders/{purchase_order}/receive', [DeliveryController::class, 'store'])
            ->name('deliveries.store');
    });

    /*
     * Delivery problems.
     *
     * A problem is always raised against a receipt, so reporting is nested
     * under a delivery. A problem is never deleted - it is cancelled - so the
     * resource excludes destroy. Corrective actions and attachments are nested
     * under their problem because neither means anything on its own.
     */
    Route::middleware('permission:problem.view')->group(function (): void {
        Route::get('problems', [DeliveryProblemController::class, 'index'])->name('problems.index');
        Route::get('problems/{problem}', [DeliveryProblemController::class, 'show'])->name('problems.show');
        Route::get('problems/{problem}/edit', [DeliveryProblemController::class, 'edit'])->name('problems.edit');
        Route::put('problems/{problem}', [DeliveryProblemController::class, 'update'])->name('problems.update');
        Route::post('problems/{problem}/close', [DeliveryProblemController::class, 'close'])->name('problems.close');
        Route::post('problems/{problem}/cancel', [DeliveryProblemController::class, 'cancel'])->name('problems.cancel');

        Route::get('deliveries/{delivery}/problems/create', [DeliveryProblemController::class, 'create'])
            ->name('problems.create');
        Route::post('deliveries/{delivery}/problems', [DeliveryProblemController::class, 'store'])
            ->name('problems.store');

        Route::controller(CorrectiveActionController::class)
            ->prefix('problems/{problem}/corrective-actions')
            ->name('corrective-actions.')
            ->group(function (): void {
                Route::post('/', 'store')->name('store');
                Route::put('{action}', 'update')->name('update');
                Route::post('{action}/start', 'start')->name('start');
                Route::post('{action}/complete', 'complete')->name('complete');
                Route::delete('{action}', 'destroy')->name('destroy');
            });

        Route::controller(ProblemAttachmentController::class)
            ->prefix('problems/{problem}/attachments')
            ->name('problem-attachments.')
            ->group(function (): void {
                Route::post('/', 'store')->name('store');
                // The only route to the bytes: the private disk is not served.
                Route::get('{attachment}', 'download')->name('download');
                Route::delete('{attachment}', 'destroy')->name('destroy');
            });
    });
});

require __DIR__.'/auth.php';
