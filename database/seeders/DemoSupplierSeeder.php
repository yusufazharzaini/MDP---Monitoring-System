<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierType;
use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Database\Seeder;

/**
 * The eight demo suppliers. Codes match DemoBlueprint::CURRENT_MONTH_ALLOCATION,
 * which is what pins each supplier's service rate on the dashboard.
 */
class DemoSupplierSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, short: string, city: string, type: SupplierType, lead: int}>
     */
    private const SUPPLIERS = [
        ['code' => 'SUP-001', 'name' => 'Supplier A', 'short' => 'SUP-A', 'city' => 'Bekasi', 'type' => SupplierType::LOCAL, 'lead' => 7],
        ['code' => 'SUP-002', 'name' => 'Supplier B', 'short' => 'SUP-B', 'city' => 'Tangerang', 'type' => SupplierType::LOCAL, 'lead' => 10],
        ['code' => 'SUP-003', 'name' => 'Supplier C', 'short' => 'SUP-C', 'city' => 'Surabaya', 'type' => SupplierType::LOCAL, 'lead' => 14],
        ['code' => 'SUP-004', 'name' => 'Supplier D', 'short' => 'SUP-D', 'city' => 'Singapore', 'type' => SupplierType::IMPORT, 'lead' => 30],
        ['code' => 'SUP-005', 'name' => 'Supplier E', 'short' => 'SUP-E', 'city' => 'Semarang', 'type' => SupplierType::LOCAL, 'lead' => 12],
        ['code' => 'SUP-006', 'name' => 'Supplier F', 'short' => 'SUP-F', 'city' => 'Bandung', 'type' => SupplierType::LOCAL, 'lead' => 9],
        ['code' => 'SUP-007', 'name' => 'Supplier G', 'short' => 'SUP-G', 'city' => 'Cikarang', 'type' => SupplierType::TOLLING, 'lead' => 6],
        ['code' => 'SUP-008', 'name' => 'Supplier H', 'short' => 'SUP-H', 'city' => 'Gresik', 'type' => SupplierType::LOCAL, 'lead' => 11],
    ];

    public function run(): void
    {
        foreach (self::SUPPLIERS as $index => $definition) {
            $slug = strtolower(str_replace('-', '', $definition['short']));

            $supplier = Supplier::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'short_name' => $definition['short'],
                    'address' => 'Jl. Industri No. '.($index + 10).', '.$definition['city'],
                    'city' => $definition['city'],
                    'country' => $definition['type'] === SupplierType::IMPORT ? 'Singapore' : 'Indonesia',
                    'pic_name' => 'PIC '.$definition['short'],
                    'pic_email' => $slug.'@example.com',
                    'pic_phone' => '0811'.str_pad((string) (100000 + $index), 6, '0', STR_PAD_LEFT),
                    'lead_time_days' => $definition['lead'],
                    'payment_term' => $index % 2 === 0 ? 'NET 30' : 'NET 45',
                    'supplier_type' => $definition['type'],
                    'status' => SupplierStatus::ACTIVE,
                ],
            );

            SupplierContact::query()->updateOrCreate(
                ['supplier_id' => $supplier->getKey(), 'email' => 'sales.'.$slug.'@example.com'],
                [
                    'name' => 'Sales '.$definition['short'],
                    'position' => 'Sales Manager',
                    'phone' => $supplier->pic_phone,
                    'is_primary' => true,
                    'status' => RecordStatus::ACTIVE,
                ],
            );
        }
    }
}
