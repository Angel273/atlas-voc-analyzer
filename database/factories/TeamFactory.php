<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Team '.fake()->unique()->lastName();

        return [
            'name' => $name,
            'code' => strtoupper(fake()->unique()->bothify('TEAM-###')),
            'supervisor_id' => null,
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
