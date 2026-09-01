<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Uom;
use Database\Factories\Concerns\GeneratesDocumentNumbers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    use GeneratesDocumentNumbers;

    protected $model = Material::class;

    public function definition(): array
    {
        return [
            'code' => $this->uniqueCode('MAT'),
            'name' => $this->faker->unique()->words(3, true),
            'category_id' => MaterialCategory::factory(),
            'uom_id' => Uom::factory(),
            'specification' => $this->faker->sentence(),
            'minimum_stock' => $this->faker->numberBetween(100, 500),
            'critical_stock' => $this->faker->numberBetween(20, 99),
            'lead_time_days' => $this->faker->numberBetween(3, 30),
            'is_critical' => false,
            'status' => RecordStatus::ACTIVE,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (): array => ['is_critical' => true]);
    }
}
