<?php

namespace Tests\Unit;

use App\Models\AuditEvent;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TamperEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditService = new AuditService();
    }

    public function test_chained_hash_verification_passes_on_unaltered_chain(): void
    {
        $this->auditService->record('LOGIN', ['ip' => '127.0.0.1']);
        $this->auditService->record('IMPORT_STARTED', ['filename' => 'data.xlsx']);
        $this->auditService->record('AI_REQUEST_SENT', ['turn' => 1]);

        $verification = $this->auditService->verifyChainIntegrity();

        $this->assertTrue($verification['valid']);
        $this->assertEquals(3, $verification['total_events']);
        $this->assertEquals(0, $verification['tampered_count']);
    }

    public function test_detects_tampered_payload_in_historical_event(): void
    {
        $e1 = $this->auditService->record('LOGIN', ['ip' => '127.0.0.1']);
        $e2 = $this->auditService->record('IMPORT_STARTED', ['filename' => 'data.xlsx']);
        $e3 = $this->auditService->record('AI_REQUEST_SENT', ['turn' => 1]);

        // Maliciously alter payload of event 2 directly in DB
        AuditEvent::where('id', $e2->id)->update([
            'payload' => json_encode(['filename' => 'hacked.xlsx']),
        ]);

        $verification = $this->auditService->verifyChainIntegrity();

        $this->assertFalse($verification['valid']);
        $this->assertGreaterThanOrEqual(1, $verification['tampered_count']);
        $this->assertEquals($e2->id, $verification['tampered_events'][0]['id']);
    }
}
