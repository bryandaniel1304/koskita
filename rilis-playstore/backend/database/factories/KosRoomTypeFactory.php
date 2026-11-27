<?php

namespace Database\Factories;

use App\Models\Kos;
use App\Models\KosRoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KosRoomType>
 */
class KosRoomTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kos_id' => Kos::factory(),
            'name' => $this->faker->randomElement(['Kamar AC', 'Kamar Standar', 'Kamar Deluxe']),
            'price' => $this->faker->numberBetween(1000000, 3000000),
            'total_rooms' => $this->faker->numberBetween(1, 5),
        ];
    }
}
