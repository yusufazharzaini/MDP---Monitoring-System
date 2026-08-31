<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SettingType;
use App\Models\KpiSetting;
use App\Models\SystemSetting;
use App\Services\Setting\KpiSettingService;
use App\Services\Setting\SystemSettingService;
use Illuminate\Database\Seeder;

/**
 * KPI thresholds and runtime business settings.
 *
 * Nothing in the Vue layer hard-codes a target or a grade boundary; every
 * number the dashboard draws a line at originates here.
 */
class KpiSettingSeeder extends Seeder
{
    /**
     * @var array<int, array{code: string, name: string, description: string, target: float, warning: float|null, critical: float|null, unit: string}>
     */
    private const KPIS = [
        [
            'code' => 'SERVICE_RATE',
            'name' => 'Service Rate',
            'description' => 'Persentase delivery yang memenuhi schedule terhadap total delivery.',
            'target' => 95, 'warning' => 90, 'critical' => 85, 'unit' => '%',
        ],
        [
            'code' => 'ON_TIME_RATE',
            'name' => 'On Time Delivery Rate',
            'description' => 'On Time Delivery / Total Delivery x 100.',
            'target' => 95, 'warning' => 90, 'critical' => 85, 'unit' => '%',
        ],
        [
            'code' => 'QUANTITY_FULFILLMENT',
            'name' => 'Quantity Fulfillment',
            'description' => 'Total Quantity Received / Total Quantity Ordered x 100.',
            'target' => 99, 'warning' => 97, 'critical' => 95, 'unit' => '%',
        ],
        [
            'code' => 'LATE_DELIVERY_RATE',
            'name' => 'Late Delivery Rate',
            'description' => 'Persentase delivery yang melewati schedule.',
            'target' => 5, 'warning' => 8, 'critical' => 10, 'unit' => '%',
        ],
        [
            'code' => 'GRADE_EXCELLENT',
            'name' => 'Supplier Grade - Excellent',
            'description' => 'Batas bawah service rate untuk grade Excellent.',
            'target' => 98, 'warning' => null, 'critical' => null, 'unit' => '%',
        ],
        [
            'code' => 'GRADE_GOOD',
            'name' => 'Supplier Grade - Good',
            'description' => 'Batas bawah service rate untuk grade Good.',
            'target' => 95, 'warning' => null, 'critical' => null, 'unit' => '%',
        ],
        [
            'code' => 'GRADE_AVERAGE',
            'name' => 'Supplier Grade - Average',
            'description' => 'Batas bawah service rate untuk grade Average.',
            'target' => 90, 'warning' => null, 'critical' => null, 'unit' => '%',
        ],
    ];

    /**
     * @var array<int, array{key: string, value: string, type: SettingType, group: string, description: string}>
     */
    private const SETTINGS = [
        [
            'key' => SystemSettingService::SERVICE_RATE_FORMULA,
            'value' => 'on_time',
            'type' => SettingType::STRING,
            'group' => 'service_rate',
            'description' => 'Formula service rate: on_time atau weighted.',
        ],
        [
            'key' => SystemSettingService::SERVICE_RATE_WEIGHT_ON_TIME,
            'value' => '0.5',
            'type' => SettingType::DECIMAL,
            'group' => 'service_rate',
            'description' => 'Bobot On Time Rate ketika formula = weighted.',
        ],
        [
            'key' => SystemSettingService::SERVICE_RATE_WEIGHT_QUANTITY,
            'value' => '0.5',
            'type' => SettingType::DECIMAL,
            'group' => 'service_rate',
            'description' => 'Bobot Quantity Fulfillment ketika formula = weighted.',
        ],
        [
            'key' => SystemSettingService::DELIVERY_OVER_TOLERANCE_PERCENT,
            'value' => '0',
            'type' => SettingType::DECIMAL,
            'group' => 'delivery',
            'description' => 'Toleransi kelebihan quantity (persen) sebelum dianggap OVER.',
        ],
        [
            'key' => SystemSettingService::CRITICAL_FLAG_IS_CRITICAL,
            'value' => '1',
            'type' => SettingType::BOOLEAN,
            'group' => 'critical_material',
            'description' => 'Hitung material dengan flag is_critical sebagai critical material.',
        ],
        [
            'key' => SystemSettingService::CRITICAL_FLAG_LATE,
            'value' => '1',
            'type' => SettingType::BOOLEAN,
            'group' => 'critical_material',
            'description' => 'Hitung material dengan delivery terlambat sebagai critical material.',
        ],
        [
            'key' => SystemSettingService::CRITICAL_FLAG_SHORT,
            'value' => '1',
            'type' => SettingType::BOOLEAN,
            'group' => 'critical_material',
            'description' => 'Hitung material dengan quantity shortage sebagai critical material.',
        ],
        [
            'key' => SystemSettingService::CRITICAL_FLAG_CRITICAL_PROBLEM,
            'value' => '1',
            'type' => SettingType::BOOLEAN,
            'group' => 'critical_material',
            'description' => 'Hitung material dengan problem severity CRITICAL sebagai critical material.',
        ],
        [
            'key' => SystemSettingService::PO_REQUIRE_SEPARATE_APPROVER,
            'value' => '1',
            'type' => SettingType::BOOLEAN,
            'group' => 'purchase_order',
            'description' => 'Wajibkan approver purchase order berbeda dari pembuatnya (segregation of duties).',
        ],
        [
            'key' => SystemSettingService::IMPORT_AUTO_CREATE_MASTER,
            'value' => '0',
            'type' => SettingType::BOOLEAN,
            'group' => 'import',
            'description' => 'Izinkan import membuat data master baru secara otomatis.',
        ],
    ];

    public function run(): void
    {
        foreach (self::KPIS as $kpi) {
            KpiSetting::query()->updateOrCreate(
                ['code' => $kpi['code']],
                [
                    'name' => $kpi['name'],
                    'description' => $kpi['description'],
                    'target_value' => $kpi['target'],
                    'warning_value' => $kpi['warning'],
                    'critical_value' => $kpi['critical'],
                    'unit' => $kpi['unit'],
                    'is_active' => true,
                ],
            );
        }

        foreach (self::SETTINGS as $setting) {
            SystemSetting::query()->updateOrCreate(
                ['setting_key' => $setting['key']],
                [
                    'setting_value' => $setting['value'],
                    'type' => $setting['type'],
                    'group' => $setting['group'],
                    'description' => $setting['description'],
                ],
            );
        }

        app(KpiSettingService::class)->flush();
        app(SystemSettingService::class)->flush();
    }
}
