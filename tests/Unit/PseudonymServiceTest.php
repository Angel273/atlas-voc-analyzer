<?php

namespace Tests\Unit;

use App\Services\Privacy\PseudonymService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PseudonymServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PseudonymService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PseudonymService();
    }

    public function test_generates_cryptographically_opaque_tokens(): void
    {
        $scope = 'conv_123';
        $token1 = $this->service->getOrCreatePseudonym($scope, 'agent', 'BMS_9981');
        $token2 = $this->service->getOrCreatePseudonym($scope, 'supervisor', 'Maria Santos');

        $this->assertStringStartsWith('AGT_', $token1);
        $this->assertStringStartsWith('SUP_', $token2);
        $this->assertNotEquals('AGENT_1', $token1); // Not sequential
        $this->assertEquals(10, strlen($token1)); // AGT_ + 6 chars
    }

    public function test_scoped_idempotency_and_resolution(): void
    {
        $scope = 'conv_123';
        $token = $this->service->getOrCreatePseudonym($scope, 'agent', 'BMS_9981');
        $tokenAgain = $this->service->getOrCreatePseudonym($scope, 'agent', 'BMS_9981');

        // Within same scope, same entity gets the same token
        $this->assertEquals($token, $tokenAgain);

        // Resolves back to internal ID
        $resolved = $this->service->resolveToInternalId($scope, $token);
        $this->assertEquals('BMS_9981', $resolved);
    }
}
