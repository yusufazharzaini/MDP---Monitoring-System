<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\ProblemCategory;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryProblem>
 */
class DeliveryProblemFactory extends Factory
{
    protected $model = DeliveryProblem::class;

    public function definition(): array
    {
        $severity = $this->faker->randomElement(ProblemSeverity::cases());
        $date = now()->subDays($this->faker->numberBetween(0, 120));

        return [
            'problem_number' => 'PRB-'.$date->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'delivery_id' => Delivery::factory(),
            'supplier_id' => Supplier::factory(),
            'material_id' => null,
            'problem_category_id' => ProblemCategory::factory(),
            'problem_date' => $date->toDateString(),
            'description' => $this->faker->sentence(12),
            'severity' => $severity,
            'root_cause' => $this->faker->sentence(10),
            'status' => ProblemStatus::OPEN,
            'pic' => $this->faker->name(),
            'due_date' => $date->copy()->addDays($severity->resolutionDays())->toDateString(),
        ];
    }

    public function critical(): static
    {
        return $this->state(fn (): array => ['severity' => ProblemSeverity::CRITICAL]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => ProblemStatus::CLOSED,
            'closed_at' => now(),
        ]);
    }
}
