<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Uom;
use Database\Seeders\Support\DemoBlueprint;
use Illuminate\Database\Seeder;

/**
 * Twenty demo materials.
 *
 * MAT-0001..0005 carry the late and short receipts; MAT-0001, 0002, 0006 and
 * 0007 are flagged is_critical. The union of the critical-material rules over
 * these sets is exactly seven materials - the reference dashboard's figure.
 */
class DemoMaterialSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, category: string, uom: string}>
     */
    private const MATERIALS = [
        ['name' => 'Resin PP Homopolymer', 'category' => 'RESIN', 'uom' => 'KG'],
        ['name' => 'Resin ABS Natural', 'category' => 'RESIN', 'uom' => 'KG'],
        ['name' => 'Steel Coil SPCC', 'category' => 'METAL', 'uom' => 'TON'],
        ['name' => 'Masterbatch Black', 'category' => 'MSTBT', 'uom' => 'KG'],
        ['name' => 'Additive UV Stabilizer', 'category' => 'ADDTV', 'uom' => 'KG'],
        ['name' => 'Resin PC Clear', 'category' => 'RESIN', 'uom' => 'KG'],
        ['name' => 'Aluminium Sheet 1100', 'category' => 'METAL', 'uom' => 'TON'],
        ['name' => 'Masterbatch White', 'category' => 'MSTBT', 'uom' => 'KG'],
        ['name' => 'Additive Antioxidant', 'category' => 'ADDTV', 'uom' => 'KG'],
        ['name' => 'Carton Box A1', 'category' => 'PACK', 'uom' => 'PCS'],
        ['name' => 'Carton Box B2', 'category' => 'PACK', 'uom' => 'PCS'],
        ['name' => 'Stretch Film 500mm', 'category' => 'PACK', 'uom' => 'PCS'],
        ['name' => 'Solvent Toluene', 'category' => 'CHEM', 'uom' => 'LTR'],
        ['name' => 'Solvent Acetone', 'category' => 'CHEM', 'uom' => 'LTR'],
        ['name' => 'Resin PE HDPE', 'category' => 'RESIN', 'uom' => 'KG'],
        ['name' => 'Resin PVC Suspension', 'category' => 'RESIN', 'uom' => 'KG'],
        ['name' => 'Masterbatch Blue', 'category' => 'MSTBT', 'uom' => 'KG'],
        ['name' => 'Steel Bar SS400', 'category' => 'METAL', 'uom' => 'TON'],
        ['name' => 'Pallet Plastic', 'category' => 'PACK', 'uom' => 'PCS'],
        ['name' => 'Additive Lubricant', 'category' => 'ADDTV', 'uom' => 'KG'],
    ];

    public function run(): void
    {
        $categories = MaterialCategory::query()->pluck('id', 'code');
        $uoms = Uom::query()->pluck('id', 'code');

        foreach (self::MATERIALS as $index => $definition) {
            $code = sprintf('MAT-%04d', $index + 1);

            Material::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $definition['name'],
                    'category_id' => $categories[$definition['category']],
                    'uom_id' => $uoms[$definition['uom']],
                    'specification' => 'Spesifikasi standar untuk '.$definition['name'],
                    'minimum_stock' => 500 + $index * 25,
                    'critical_stock' => 100 + $index * 5,
                    'lead_time_days' => 7 + ($index % 14),
                    'is_critical' => in_array($code, DemoBlueprint::CRITICAL_FLAGGED_MATERIALS, true),
                    'status' => RecordStatus::ACTIVE,
                ],
            );
        }
    }

    /**
     * Material codes usable by clean, on-time lines. Includes the two flagged
     * materials that must appear in the period without ever running late.
     *
     * @return array<int, string>
     */
    public static function normalMaterialCodes(): array
    {
        $codes = [];

        for ($i = 1; $i <= count(self::MATERIALS); $i++) {
            $code = sprintf('MAT-%04d', $i);

            if (! in_array($code, DemoBlueprint::PROBLEM_MATERIALS, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
