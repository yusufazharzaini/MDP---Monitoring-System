<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Setting\KpiSettingService;
use App\Services\Setting\SystemSettingService;
use Database\Seeders\Support\RefusesToRunInProduction;
use Illuminate\Database\Seeder;

/**
 * Full application seed.
 *
 * Order matters: reference data, then users (purchase orders record a creator),
 * then the demo transactions that depend on all of it.
 *
 *   php artisan migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    use RefusesToRunInProduction;

    public function run(): void
    {
        $this->guardAgainstProduction();

        $this->call([
            RolesAndPermissionsSeeder::class,
            MasterDataSeeder::class,
            KpiSettingSeeder::class,
            DemoPlantSeeder::class,
            UserSeeder::class,
            DemoSupplierSeeder::class,
            DemoMaterialSeeder::class,
            DemoPurchaseOrderSeeder::class,
            DemoDeliverySeeder::class,
            DemoProblemSeeder::class,
        ]);

        app(KpiSettingService::class)->flush();
        app(SystemSettingService::class)->flush();
    }
}
