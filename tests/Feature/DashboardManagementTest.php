<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Survey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Dashboard $dashboard;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Analyst', 'slug' => 'analyst']);
        $p1 = Permission::create(['name' => 'Dashboard View', 'slug' => 'dashboard.view']);
        $p2 = Permission::create(['name' => 'Dashboard Edit', 'slug' => 'dashboard.edit']);
        $role->permissions()->attach([$p1->id, $p2->id]);

        $this->user = User::create([
            'name' => 'Analyst User',
            'email' => 'analyst@atlas.local',
            'password' => Hash::make('password123'),
        ]);
        $this->user->roles()->attach($role->id);

        $this->dashboard = Dashboard::create([
            'name' => 'VOC Master Overview',
            'is_default' => true,
            'description' => 'Test analytical overview',
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/Index')
            ->has('dashboard')
            ->has('filter_options')
        );
    }

    public function test_user_can_query_widget_data_via_dsl(): void
    {
        $import = \App\Models\Import::create([
            'original_filename' => 'surveys.xlsx',
            'file_hash' => 'hash_surveys',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_TEST_1',
            'nps_score' => 0.8,
            'csat_score' => 0.9,
            'professionalism_score' => 0.95,
            'agent_bms' => 'AGT_1',
            'supervisor' => 'SUP_1',
            'survey_date' => '2026-09-01',
            'record_hash' => 'hash_123',
            'import_id' => $import->id,
        ]);

        $response = $this->actingAs($this->user)->postJson('/dashboard/query', [
            'metric' => 'nps',
            'aggregation' => 'avg',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data',
        ]);
    }

    public function test_user_can_create_widget_with_declarative_config(): void
    {
        $payload = [
            'title' => 'CSAT Corporate Target',
            'type' => 'metric',
            'w' => 4,
            'h' => 3,
            'configuration' => [
                'metric' => 'csat',
                'showTargetLine' => true,
                'targetLineValue' => 85,
                'targetLineLabel' => 'Meta Corporativa 85%',
                'color' => '#18221d',
            ],
        ];

        $response = $this->actingAs($this->user)->postJson(
            "/dashboard/{$this->dashboard->id}/widgets",
            $payload
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'widget' => [
                'title' => 'CSAT Corporate Target',
                'type' => 'metric',
                'w' => 4,
                'h' => 3,
            ],
        ]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'dashboard_id' => $this->dashboard->id,
            'title' => 'CSAT Corporate Target',
            'type' => 'metric',
        ]);
    }

    public function test_user_can_update_widget_declarative_configuration(): void
    {
        $widget = $this->dashboard->widgets()->create([
            'title' => 'Old Title',
            'type' => 'trend',
            'w' => 6,
            'h' => 4,
            'x' => 0,
            'y' => 0,
            'configuration' => ['metric' => 'nps'],
        ]);

        $updatePayload = [
            'title' => 'Updated Trend Title',
            'type' => 'trend',
            'w' => 8,
            'h' => 5,
            'configuration' => [
                'metric' => 'nps',
                'color' => '#aac69c',
                'showTargetLine' => true,
                'targetLineValue' => 60,
                'targetLineLabel' => 'Objetivo Q4',
            ],
        ];

        $response = $this->actingAs($this->user)->putJson(
            "/dashboard/{$this->dashboard->id}/widgets/{$widget->id}",
            $updatePayload
        );

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'widget' => [
                'id' => $widget->id,
                'title' => 'Updated Trend Title',
                'w' => 8,
                'h' => 5,
            ],
        ]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'id' => $widget->id,
            'title' => 'Updated Trend Title',
            'w' => 8,
            'h' => 5,
        ]);
    }

    public function test_user_can_update_dashboard_layout(): void
    {
        $widget1 = $this->dashboard->widgets()->create([
            'title' => 'W1',
            'type' => 'metric',
            'w' => 3,
            'h' => 3,
            'x' => 0,
            'y' => 0,
            'configuration' => [],
        ]);

        $widget2 = $this->dashboard->widgets()->create([
            'title' => 'W2',
            'type' => 'metric',
            'w' => 3,
            'h' => 3,
            'x' => 3,
            'y' => 0,
            'configuration' => [],
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/dashboard/{$this->dashboard->id}/layout",
            [
                'widgets' => [
                    ['id' => $widget1->id, 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4, 'sort_order' => 2],
                    ['id' => $widget2->id, 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'sort_order' => 1],
                ],
            ]
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'id' => $widget1->id,
            'x' => 6,
            'w' => 6,
            'sort_order' => 2,
        ]);

        $this->assertDatabaseHas('dashboard_widgets', [
            'id' => $widget2->id,
            'x' => 0,
            'w' => 6,
            'sort_order' => 1,
        ]);
    }

    public function test_user_can_delete_widget(): void
    {
        $widget = $this->dashboard->widgets()->create([
            'title' => 'To Delete',
            'type' => 'metric',
            'w' => 3,
            'h' => 3,
            'x' => 0,
            'y' => 0,
            'configuration' => [],
        ]);

        $response = $this->actingAs($this->user)->deleteJson(
            "/dashboard/{$this->dashboard->id}/widgets/{$widget->id}"
        );

        $response->assertOk();
        $this->assertDatabaseMissing('dashboard_widgets', [
            'id' => $widget->id,
        ]);
    }
}
