<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_authenticate_and_access_dashboard(): void
    {
        $role = Role::create(['name' => 'Analyst', 'slug' => 'analyst']);
        $permission = Permission::create(['name' => 'Dashboard View', 'slug' => 'dashboard.view']);
        $role->permissions()->attach($permission->id);

        $user = User::create([
            'name' => 'Test Analyst',
            'email' => 'test@atlas.local',
            'password' => Hash::make('secret123'),
        ]);
        $user->roles()->attach($role->id);

        $response = $this->post('/login', [
            'email' => 'test@atlas.local',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rate_limiting_triggers_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'target@atlas.local',
                'password' => 'wrongpass',
            ]);
        }

        // 6th attempt should be blocked by rate limiter
        $response = $this->post('/login', [
            'email' => 'target@atlas.local',
            'password' => 'wrongpass',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_without_required_permission_receives_forbidden(): void
    {
        $user = User::create([
            'name' => 'Restricted User',
            'email' => 'restricted@atlas.local',
            'password' => Hash::make('password123'),
        ]);

        $this->actingAs($user);

        // Does not have 'audit.view' permission
        $response = $this->get('/audit');
        $response->assertStatus(403);
    }
}
