<?php

namespace Database\Factories;

use App\Models\PerformanceCase;
use App\Models\User;
use App\Models\WorkforceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceCase>
 */
class PerformanceCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_number' => PerformanceCase::generateCaseNumber(),
            'workforce_member_id' => WorkforceMember::factory(),
            'target_type' => 'agent',
            'type' => fake()->randomElement(['nps_improvement', 'csat_recovery', 'quality_compliance']),
            'reason' => fake()->sentence(),
            'priority' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'status' => 'open',
            'opened_at' => now()->subDays(14)->toDateString(),
            'closed_at' => null,
            'assigned_to_user_id' => User::factory(),
            'baseline' => [
                'nps' => 0.10,
                'csat' => 0.65,
                'professionalism' => 0.70,
                'volume' => 25,
            ],
            'objectives' => [
                'target_nps' => 0.50,
                'target_csat' => 0.80,
                'target_professionalism' => 0.85,
            ],
            'next_review_at' => now()->addDays(7)->toDateString(),
        ];
    }
}
