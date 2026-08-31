<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KpiSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiSetting>
 */
class KpiSettingFactory extends Factory
{
    protected $model = KpiSetting::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('KPI_????')),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'target_value' => 95,
            'warning_value' => 90,
            'critical_value' => 85,
            'unit' => '%',
            'is_active' => true,
        ];
    }
}
