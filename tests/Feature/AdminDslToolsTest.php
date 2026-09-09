<?php

namespace Tests\Feature;

use App\Models\DslTool;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DslToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDslToolsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $viewerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $managePerm = Permission::create(['name' => 'Users Manage', 'slug' => 'users.manage']);
        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'administrator']);
        $adminRole->permissions()->attach($managePerm->id);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.local',
            'password' => Hash::make('password123'),
        ]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->viewerUser = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer@test.local',
            'password' => Hash::make('password123'),
        ]);

        $this->seed(DslToolSeeder::class);
    }

    public function test_admin_can_view_tools_catalog(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/tools');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Tools/Index')
            ->has('tools')
        );
    }

    public function test_admin_can_create_custom_dsl_tool(): void
    {
        $payload = [
            'name' => 'team_nps_summary',
            'label' => 'Resumen NPS por Equipo',
            'description' => 'Calcula el NPS consolidado por supervisor y equipo en un único paso sin iterar.',
            'execution_mode' => 'dsl_query',
            'is_active' => true,
            'parameters_schema' => [
                'type' => 'object',
                'properties' => [
                    'supervisor' => ['type' => 'string'],
                ],
            ],
            'dsl_template' => [
                'metric' => 'nps',
                'group_by' => ['supervisor'],
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/admin/tools', $payload);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('dsl_tools', [
            'name' => 'team_nps_summary',
            'is_builtin' => false,
        ]);
    }

    public function test_admin_cannot_delete_builtin_tool(): void
    {
        $builtin = DslTool::where('name', 'query_data')->first();

        $response = $this->actingAs($this->adminUser)->deleteJson("/admin/tools/{$builtin->id}");

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
        $this->assertDatabaseHas('dsl_tools', ['id' => $builtin->id]);
    }

    public function test_admin_can_delete_custom_tool(): void
    {
        $custom = DslTool::create([
            'name' => 'temp_tool',
            'label' => 'Herramienta Temporal',
            'description' => 'Descripción de prueba para eliminación de herramienta temporal.',
            'execution_mode' => 'dsl_query',
            'is_builtin' => false,
            'is_active' => true,
            'parameters_schema' => ['type' => 'object'],
        ]);

        $response = $this->actingAs($this->adminUser)->deleteJson("/admin/tools/{$custom->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertDatabaseMissing('dsl_tools', ['id' => $custom->id]);
    }

    public function test_admin_can_toggle_tool_active_status(): void
    {
        $tool = DslTool::where('name', 'query_data')->first();
        $initialState = $tool->is_active;

        $response = $this->actingAs($this->adminUser)->postJson("/admin/tools/{$tool->id}/toggle");

        $response->assertStatus(200);
        $response->assertJson(['success' => true, 'is_active' => ! $initialState]);
        $this->assertDatabaseHas('dsl_tools', ['id' => $tool->id, 'is_active' => ! $initialState]);
    }

    public function test_admin_can_test_tool_in_sandbox(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/tools/test', [
            'tool_name' => 'calculate_metric',
            'arguments' => ['metric' => 'nps'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'tool_name',
            'duration_ms',
            'estimated_tokens',
            'result',
        ]);
    }
}
