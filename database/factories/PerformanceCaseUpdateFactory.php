<?php

namespace Database\Factories;

use App\Models\PerformanceCase;
use App\Models\PerformanceCaseUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceCaseUpdate>
 */
class PerformanceCaseUpdateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'performance_case_id' => PerformanceCase::factory(),
            'created_by_user_id' => User::factory(),
            'previous_status' => 'open',
            'resulting_status' => 'monitoring',
            'summary' => fake()->sentence(),
            'observations' => fake()->paragraph(),
            'actions' => fake()->sentence(),
            'commitments' => fake()->sentence(),
            'next_review_at' => now()->addDays(7)->toDateString(),
            'metrics_snapshot' => [
                'nps' => 0.25,
                'csat' => 0.70,
                'professionalism' => 0.80,
                'volume' => 30,
            ],
            'disciplinary_details' => 'Nota confidencial: Compromiso formal registrado por reincidencia.',
        ];
    }
}
