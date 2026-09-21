<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\WorkforceMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMembership>
 */
class TeamMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'workforce_member_id' => WorkforceMember::factory(),
            'role' => 'agent',
            'effective_from' => now()->subMonths(6)->toDateString(),
            'effective_to' => null,
        ];
    }
}
