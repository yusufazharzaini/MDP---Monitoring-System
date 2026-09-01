<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
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
use App\Http\Controllers\Performance\CriticalMaterialController;
use App\Http\Controllers\Performance\SupplierEvaluationController;
use App\Http\Controllers\Performance\SupplierPerformanceController;
use App\Http\Controllers\Problem\CorrectiveActionController;
use App\Http\Controllers\Problem\DeliveryProblemController;
use App\Http\Controllers\Problem\ProblemAttachmentController;
use App\Http\Controllers\PurchaseOrder\PurchaseOrderController;
use App\Http\Controllers\Report\ReportController;
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
     * Administration.
     *
     * Users are retired rather than destroyed - their orders, receipts and
     * audit entries still name them - so destroy() soft-deletes and restore()
     * brings an account back. Roles are seeded job titles: their permissions
     * are editable, the roles themselves are not.
     */
    Route::middleware('permission:user.view')->group(function (): void {
        Route::resource('users', UserController::class)->except('show');
        Route::post('users/{user}/restore', [UserController::class, 'restore'])
            ->withTrashed()
            ->name('users.restore');

        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    /*
     * The activity trail. Read-only: there is no route here that writes to it.
     */
    Route::middleware('permission:audit.view')
        ->get('audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs.index');

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

    /*
     * Supplier performance and the critical material watchlist.
     *
     * These are the reporting side of the dashboard aggregates, so they sit
     * behind report.view rather than dashboard.view - a role may be trusted
     * with the summary without being handed the whole supplier league table.
     */
    Route::middleware('permission:report.view')->group(function (): void {
        Route::get('supplier-performance', [SupplierPerformanceController::class, 'index'])
            ->name('supplier-performance.index');
        Route::get('supplier-performance/{supplier}', [SupplierPerformanceController::class, 'show'])
            ->name('supplier-performance.show');

        Route::get('critical-materials', [CriticalMaterialController::class, 'index'])
            ->name('critical-materials.index');
    });

    /*
     * Monthly supplier evaluations.
     *
     * A scorecard is never deleted and never edited by hand - it is generated
     * from transactions, approved, and reopened if it must change - so the
     * routes are exactly those four verbs.
     */
    /*
     * Reporting.
     *
     * Viewing a report and taking its data out of the building are different
     * rights: the catalogue is report.view, every export is report.export.
     */
    Route::middleware('permission:report.view')->group(function (): void {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{type}/export', [ReportController::class, 'export'])
            ->middleware('permission:report.export')
            ->name('reports.export');
    });

    Route::middleware('permission:evaluation.view')->group(function (): void {
        Route::get('supplier-evaluations', [SupplierEvaluationController::class, 'index'])
            ->name('supplier-evaluations.index');
        Route::post('supplier-evaluations', [SupplierEvaluationController::class, 'store'])
            ->name('supplier-evaluations.store');
        Route::get('supplier-evaluations/{evaluation}', [SupplierEvaluationController::class, 'show'])
            ->name('supplier-evaluations.show');
        Route::post('supplier-evaluations/{evaluation}/approve', [SupplierEvaluationController::class, 'approve'])
            ->name('supplier-evaluations.approve');
        Route::post('supplier-evaluations/{evaluation}/reopen', [SupplierEvaluationController::class, 'reopen'])
            ->name('supplier-evaluations.reopen');
    });
});

require __DIR__.'/auth.php';
