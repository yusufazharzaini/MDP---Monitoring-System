<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    protected $model = Plant::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('PL##')),
            'name' => 'Plant '.$this->faker->unique()->city(),
            'address' => $this->faker->address(),
            'city' => $this->faker->city(),
            'pic_name' => $this->faker->name(),
            'pic_phone' => $this->faker->numerify('08##########'),
            'status' => RecordStatus::ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => RecordStatus::INACTIVE]);
    }
}
