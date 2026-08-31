<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Enums\UomType;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Uom>
 */
class UomFactory extends Factory
{
    protected $model = Uom::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('U??')),
            'name' => $this->faker->unique()->word(),
            'type' => UomType::QTY,
            'status' => RecordStatus::ACTIVE,
        ];
    }
}
