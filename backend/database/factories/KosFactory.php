<?php

namespace Database\Factories;

use App\Models\Kos;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kos>
 */
class KosFactory extends Factory
{
    protected $model = Kos::class;

    public function definition(): array
    {
        return [
            'name' => 'Kos ' . fake()->streetName(),
            'price' => fake()->numberBetween(1000000, 5000000),
            'gender_type' => fake()->randomElement(['putra', 'putri', 'campur']),
            'location' => fake()->randomElement(['Karawaci', 'BSD', 'Serpong']),
            'distance_to_campus' => fake()->randomFloat(1, 0.1, 5.0),
            'description' => fake()->sentence(),
            'image_url' => 'https://images.unsplash.com/photo-1522771739844-6a9f6d5f14af',
        ];
    }
}
