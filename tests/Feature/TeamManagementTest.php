<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Role;
use App\Models\Survey;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\WorkforceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->adminUser = User::factory()->create();
        $this->adminUser->roles()->sync(Role::where('slug', 'administrator')->pluck('id'));

        $this->regularUser = User::factory()->create();
    }

    public function test_unauthorized_user_cannot_access_teams(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('teams.index'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->post(route('teams.store'), [
                'name' => 'Team Alpha',
                'code' => 'TEAM-ALPHA',
            ])
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_teams_catalog(): void
    {
        $supervisor = WorkforceMember::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Bruce']);
        $team = Team::factory()->create(['name' => 'Justice League', 'code' => 'JL-01', 'supervisor_id' => $supervisor->id]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('teams.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Teams/Index')
                ->has('teams', 1)
                ->has('supervisors')
                ->has('agents')
            );
    }

    public function test_can_create_and_update_team(): void
    {
        $supervisor = WorkforceMember::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Clark']);

        // Create
        $response = $this->actingAs($this->adminUser)
            ->post(route('teams.store'), [
                'name' => 'Team Metropolis',
                'code' => 'METRO-01',
                'supervisor_id' => $supervisor->id,
                'is_active' => true,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('teams', [
            'name' => 'Team Metropolis',
            'code' => 'METRO-01',
            'supervisor_id' => $supervisor->id,
            'is_active' => true,
        ]);

        $team = Team::where('code', 'METRO-01')->first();

        // Update
        $responseUpdate = $this->actingAs($this->adminUser)
            ->put(route('teams.update', $team), [
                'name' => 'Team Metropolis Updated',
                'code' => 'METRO-02',
                'supervisor_id' => $supervisor->id,
                'is_active' => false,
            ]);

        $responseUpdate->assertRedirect();
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Team Metropolis Updated',
            'code' => 'METRO-02',
            'is_active' => false,
        ]);
    }

    public function test_team_with_surveys_is_deactivated_instead_of_deleted(): void
    {
        $team = Team::factory()->create(['name' => 'Team Gotham', 'is_active' => true]);

        $import = Import::create([
            'original_filename' => 'test.xlsx',
            'file_hash' => 'hash_gotham',
            'sheet_name' => 'Sheet1',
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'import_id' => $import->id,
            'survey_id' => 9999,
            'survey_date' => '2026-01-01',
            'agent_bms' => '1001',
            'agent_name' => 'Agent X',
            'supervisor' => 'Supervisor S',
            'team_id' => $team->id,
            'nps_score' => 10,
            'csat_score' => 5,
            'professionalism_score' => 5,
            'record_hash' => 'hash_survey_gotham',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->delete(route('teams.destroy', $team));

        $response->assertRedirect();
        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'is_active' => false,
        ]);
    }

    public function test_can_add_and_remove_team_members(): void
    {
        $team = Team::factory()->create(['name' => 'Team Titans']);
        $agent = WorkforceMember::factory()->create(['role' => 'agent', 'name' => 'Agente Beast Boy']);

        // Add member
        $responseAdd = $this->actingAs($this->adminUser)
            ->post(route('teams.members.add', $team), [
                'workforce_member_id' => $agent->id,
                'effective_from' => '2026-01-01',
            ]);

        $responseAdd->assertRedirect();
        $this->assertDatabaseHas('team_memberships', [
            'team_id' => $team->id,
            'workforce_member_id' => $agent->id,
            'effective_to' => null,
        ]);

        // Remove member
        $responseRemove = $this->actingAs($this->adminUser)
            ->delete(route('teams.members.remove', [$team, $agent]));

        $responseRemove->assertRedirect();
        $membership = TeamMembership::where('team_id', $team->id)
            ->where('workforce_member_id', $agent->id)
            ->first();

        $this->assertNotNull($membership->effective_to);
    }
}
