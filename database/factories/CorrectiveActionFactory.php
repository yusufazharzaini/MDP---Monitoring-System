<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CorrectiveActionStatus;
use App\Models\CorrectiveAction;
use App\Models\DeliveryProblem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectiveAction>
 */
class CorrectiveActionFactory extends Factory
{
    protected $model = CorrectiveAction::class;

    public function definition(): array
    {
        return [
            'delivery_problem_id' => DeliveryProblem::factory(),
            'action_date' => now()->toDateString(),
            'description' => $this->faker->sentence(12),
            'status' => CorrectiveActionStatus::OPEN,
            'due_date' => now()->addDays(14)->toDateString(),
        ];
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => CorrectiveActionStatus::DONE,
            'completed_at' => now(),
        ]);
    }
}
