<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Plant;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('WH##')),
            'name' => 'Warehouse '.$this->faker->unique()->word(),
            'address' => $this->faker->address(),
            'status' => RecordStatus::ACTIVE,
        ];
    }

    public function forPlant(Plant $plant): static
    {
        return $this->state(fn (): array => ['plant_id' => $plant->getKey()]);
    }
}
