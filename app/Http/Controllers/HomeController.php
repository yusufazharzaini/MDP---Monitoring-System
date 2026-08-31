<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\Material;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in landing page: a module launcher filtered by the viewer's
 * permissions, with live record counts for the modules they can reach.
 *
 * Phase 5 introduces the analytics dashboard at /dashboard; this page remains
 * the workspace entry point.
 */
class HomeController extends Controller
{
    /**
     * Module cards: [route, label, description, permission, counter].
     *
     * @var array<int, array{key: string, label: string, description: string, permission: string}>
     */
    private const MODULES = [
        ['key' => 'suppliers', 'label' => 'Supplier', 'description' => 'Master data supplier, kontak, dan lead time.', 'permission' => 'supplier.view'],
        ['key' => 'materials', 'label' => 'Material', 'description' => 'Master material, kategori, dan unit of measure.', 'permission' => 'material.view'],
        ['key' => 'purchase-orders', 'label' => 'Purchase Order', 'description' => 'PO, item, approval, dan status pemenuhan.', 'permission' => 'po.view'],
        ['key' => 'deliveries', 'label' => 'Delivery', 'description' => 'Penerimaan material dan perhitungan status otomatis.', 'permission' => 'delivery.view'],
        ['key' => 'problems', 'label' => 'Problem Analysis', 'description' => 'Masalah delivery, root cause, dan corrective action.', 'permission' => 'problem.view'],
        ['key' => 'reports', 'label' => 'Report', 'description' => 'Laporan delivery, supplier, dan material.', 'permission' => 'report.view'],
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Home', [
            'modules' => array_values(array_filter(
                self::MODULES,
                static fn (array $module): bool => $user?->can($module['permission']) ?? false,
            )),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function stats(): array
    {
        return [
            ['label' => 'Supplier', 'value' => Supplier::query()->count()],
            ['label' => 'Material', 'value' => Material::query()->count()],
            ['label' => 'Purchase Order', 'value' => PurchaseOrder::query()->count()],
            ['label' => 'Delivery', 'value' => Delivery::query()->count()],
            ['label' => 'Open Problem', 'value' => DeliveryProblem::query()->open()->count()],
            ['label' => 'User', 'value' => User::query()->count()],
        ];
    }
}
