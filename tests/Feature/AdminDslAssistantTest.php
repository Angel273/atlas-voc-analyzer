<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDslAssistantTest extends TestCase
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
    }

    public function test_guest_cannot_access_dsl_assistant(): void
    {
        $response = $this->postJson('/admin/dsl-assistant/chat', [
            'message' => 'Dame NPS por supervisor',
        ]);
        $response->assertStatus(401);

        $execResponse = $this->postJson('/admin/dsl-assistant/execute', [
            'dsl' => ['metric' => 'nps'],
        ]);
        $execResponse->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_dsl_assistant(): void
    {
        $response = $this->actingAs($this->viewerUser)->postJson('/admin/dsl-assistant/chat', [
            'message' => 'Dame NPS por supervisor',
        ]);
        $response->assertStatus(403);
    }

    public function test_admin_can_chat_with_dsl_assistant_and_get_valid_dsl(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/admin/dsl-assistant/chat', [
            'message' => 'Dame el promedio de CSAT agrupado por supervisor en Wave 1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'content',
            'dsl',
            'dsl_valid',
            'suggested_tool',
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertTrue($data['dsl_valid']);
        $this->assertEquals('csat', $data['dsl']['metric']);
        $this->assertContains('supervisor', $data['dsl']['group_by']);
        $this->assertNotNull($data['suggested_tool']);
        $this->assertEquals('dsl_query', $data['suggested_tool']['execution_mode']);
    }

    public function test_admin_can_execute_dsl_via_assistant_endpoint(): void
    {
        $payload = [
            'dsl' => [
                'metric' => 'survey_volume',
                'aggregation' => 'count',
                'group_by' => ['supervisor'],
                'limit' => 5,
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/admin/dsl-assistant/execute', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'data',
            'duration_ms',
            'dsl',
        ]);
    }

    public function test_dsl_assistant_execute_rejects_invalid_dsl(): void
    {
        $payload = [
            'dsl' => [
                'metric' => 'invalid_metric_xyz',
            ],
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/admin/dsl-assistant/execute', $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }
}
