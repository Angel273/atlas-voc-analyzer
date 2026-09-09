<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_check_endpoint_returns_ok_and_database_status(): void
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
            'application' => 'ATLAS VOC Analysis',
            'database' => 'connected',
        ]);
        $response->assertJsonStructure(['status', 'application', 'database', 'timestamp']);
    }

    public function test_root_redirects_to_login_for_guests(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }
}
