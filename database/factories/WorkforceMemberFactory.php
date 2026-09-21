<?php

namespace Database\Factories;

use App\Models\WorkforceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkforceMember>
 */
class WorkforceMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_id' => 'BMS-'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
            'role' => 'agent',
            'is_active' => true,
            'metadata' => [],
        ];
    }

    public function agent(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'agent',
        ]);
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'supervisor',
            'external_id' => 'SUP-'.fake()->unique()->numerify('###'),
        ]);
    }
}
