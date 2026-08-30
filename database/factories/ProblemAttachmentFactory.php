<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DeliveryProblem;
use App\Models\ProblemAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProblemAttachment>
 */
class ProblemAttachmentFactory extends Factory
{
    protected $model = ProblemAttachment::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->lexify('evidence-????').'.pdf';

        return [
            'delivery_problem_id' => DeliveryProblem::factory(),
            'file_name' => $name,
            'file_path' => 'problem-attachments/'.$name,
            'mime_type' => 'application/pdf',
            'file_size' => $this->faker->numberBetween(10_000, 2_000_000),
        ];
    }
}
