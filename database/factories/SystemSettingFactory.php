<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SettingType;
use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemSetting>
 */
class SystemSettingFactory extends Factory
{
    protected $model = SystemSetting::class;

    public function definition(): array
    {
        return [
            'setting_key' => $this->faker->unique()->lexify('setting.????'),
            'setting_value' => $this->faker->word(),
            'type' => SettingType::STRING,
            'group' => 'general',
            'description' => $this->faker->sentence(),
        ];
    }
}
