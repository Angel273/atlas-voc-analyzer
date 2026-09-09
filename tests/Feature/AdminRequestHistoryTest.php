<?php

namespace Tests\Feature;

use App\Models\AiRun;
use App\Models\AiToolCall;
use App\Models\Conversation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRequestHistoryTest extends TestCase
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

    public function test_guest_cannot_access_requests_history(): void
    {
        $response = $this->get('/admin/requests');
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_requests_history(): void
    {
        $response = $this->actingAs($this->viewerUser)->get('/admin/requests');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_requests_history_with_kpis(): void
    {
        $conv = Conversation::create([
            'user_id' => $this->adminUser->id,
            'title' => 'Test Conversation',
        ]);

        $run1 = AiRun::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminUser->id,
            'user_prompt' => 'Query NPS por supervisor',
            'status' => 'completed',
            'tokens_used' => 5000,
            'latency_ms' => 1200,
        ]);

        $run2 = AiRun::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminUser->id,
            'user_prompt' => 'Dame top 10 agentes por impacto',
            'status' => 'completed',
            'tokens_used' => 85000,
            'latency_ms' => 28000,
        ]);

        AiToolCall::create([
            'ai_run_id' => $run2->id,
            'tool_name' => 'query_data',
            'arguments_sanitized' => ['metric' => 'nps'],
            'result_summary' => '{"count":10}',
            'status' => 'success',
            'duration_ms' => 15,
            'requested_at' => now(),
            'executed_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)->get('/admin/requests');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Requests/Index')
            ->has('requests.data', 2)
            ->where('requests.data.0.tokens_used', 85000) // Default sorted DESC
            ->where('kpis.total_tokens', 90000)
            ->where('kpis.max_tokens', 85000)
            ->where('kpis.total_runs', 2)
        );
    }

    public function test_admin_can_inspect_request_trace_with_diagnostics(): void
    {
        $conv = Conversation::create([
            'user_id' => $this->adminUser->id,
            'title' => 'Test Diagnostics',
        ]);

        $run = AiRun::create([
            'conversation_id' => $conv->id,
            'user_id' => $this->adminUser->id,
            'user_prompt' => 'Test query with high token consumption',
            'status' => 'completed',
            'tokens_used' => 75000,
            'latency_ms' => 25000,
        ]);

        for ($i = 0; $i < 5; $i++) {
            AiToolCall::create([
                'ai_run_id' => $run->id,
                'tool_name' => 'query_data',
                'arguments_sanitized' => ['metric' => 'nps', 'step' => $i],
                'result_summary' => '{"step":'.$i.'}',
                'status' => 'success',
                'duration_ms' => 10,
                'requested_at' => now(),
                'executed_at' => now(),
            ]);
        }

        $response = $this->actingAs($this->adminUser)->getJson("/admin/requests/{$run->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'run' => [
                'id' => $run->id,
                'tokens_used' => 75000,
                'diagnostics' => [
                    'is_high_consumption' => true,
                ],
            ],
        ]);
    }
}
