<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\ProblemCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemCategory>
 */
class ProblemCategoryFactory extends Factory
{
    protected $model = ProblemCategory::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('PC_????')),
            'name' => $this->faker->unique()->words(2, true),
            'description' => $this->faker->sentence(),
            'status' => RecordStatus::ACTIVE,
        ];
    }
}
