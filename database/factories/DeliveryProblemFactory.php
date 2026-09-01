<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProblemSeverity;
use App\Enums\ProblemStatus;
use App\Models\Delivery;
use App\Models\DeliveryProblem;
use App\Models\ProblemCategory;
use App\Models\Supplier;
use Carbon\CarbonImmutable;
use Database\Factories\Concerns\GeneratesDocumentNumbers;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryProblem>
 */
class DeliveryProblemFactory extends Factory
{
    use GeneratesDocumentNumbers;

    protected $model = DeliveryProblem::class;

    public function definition(): array
    {
        $severity = $this->faker->randomElement(ProblemSeverity::cases());
        $date = now()->subDays($this->faker->numberBetween(0, 120));

        return [
            'problem_number' => $this->documentNumber('PRB', $date),
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
            /*
             * Derived from whatever problem_date and severity end up being, not
             * from the pair generated above: a caller that overrides the report
             * date would otherwise get a due date before it, which the
             * chk_problem_due_after_report constraint rejects.
             */
            'due_date' => fn (array $attributes): string => CarbonImmutable::parse($attributes['problem_date'])
                ->addDays(self::severityOf($attributes)->resolutionDays())
                ->toDateString(),
        ];
    }

    /**
     * Severity as an enum whether it arrived as one or as its backing value.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function severityOf(array $attributes): ProblemSeverity
    {
        $severity = $attributes['severity'];

        return $severity instanceof ProblemSeverity
            ? $severity
            : ProblemSeverity::from((string) $severity);
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
