<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordStatus;
use App\Models\Plant;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DemoPlantSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, city: string, pic: string, warehouses: array<int, array{code: string, name: string}>}>
     */
    private const PLANTS = [
        [
            'code' => 'PLANT-01',
            'name' => 'Plant Cikarang',
            'city' => 'Bekasi',
            'pic' => 'Rudi Hartono',
            'warehouses' => [
                ['code' => 'WH-RM1', 'name' => 'Raw Material Warehouse 1'],
                ['code' => 'WH-FG1', 'name' => 'Finished Goods Warehouse 1'],
            ],
        ],
        [
            'code' => 'PLANT-02',
            'name' => 'Plant Karawang',
            'city' => 'Karawang',
            'pic' => 'Siti Rahayu',
            'warehouses' => [
                ['code' => 'WH-RM2', 'name' => 'Raw Material Warehouse 2'],
                ['code' => 'WH-SP2', 'name' => 'Spare Part Warehouse 2'],
            ],
        ],
        [
            'code' => 'PLANT-03',
            'name' => 'Plant Surabaya',
            'city' => 'Surabaya',
            'pic' => 'Bambang Wijaya',
            'warehouses' => [
                ['code' => 'WH-RM3', 'name' => 'Raw Material Warehouse 3'],
                ['code' => 'WH-CH3', 'name' => 'Chemical Warehouse 3'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANTS as $index => $definition) {
            $plant = Plant::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'address' => 'Kawasan Industri '.$definition['city'].' Blok '.($index + 1),
                    'city' => $definition['city'],
                    'pic_name' => $definition['pic'],
                    'pic_phone' => '021-'.str_pad((string) (5550000 + $index), 7, '0', STR_PAD_LEFT),
                    'status' => RecordStatus::ACTIVE,
                ],
            );

            foreach ($definition['warehouses'] as $warehouse) {
                Warehouse::query()->updateOrCreate(
                    ['plant_id' => $plant->getKey(), 'code' => $warehouse['code']],
                    [
                        'name' => $warehouse['name'],
                        'address' => $plant->address,
                        'status' => RecordStatus::ACTIVE,
                    ],
                );
            }
        }
    }
}
