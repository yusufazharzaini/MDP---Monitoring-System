<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Setting\KpiSettingService;
use App\Services\Setting\SystemSettingService;
use Illuminate\Database\Seeder;

/**
 * The only seeder that is safe to run against a real deployment.
 *
 * It installs what the application cannot function without - the role and
 * permission matrix, the reference lists every form draws from, and the KPI and
 * system settings the dashboard reads - and nothing else. No accounts, no
 * suppliers, no transactions: those are the deployment's own data.
 *
 * Every seeder it calls is idempotent (updateOrCreate), so re-running it after
 * an upgrade adds new reference rows without disturbing existing ones.
 *
 *   php artisan db:seed --class=ProductionSeeder
 *   php artisan mdp:create-admin
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            MasterDataSeeder::class,
            KpiSettingSeeder::class,
        ]);

        app(KpiSettingService::class)->flush();
        app(SystemSettingService::class)->flush();
    }
}
