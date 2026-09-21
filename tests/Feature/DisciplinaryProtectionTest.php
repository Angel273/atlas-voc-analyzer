<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\PerformanceCase;
use App\Models\PerformanceCaseUpdate;
use App\Models\Permission;
use App\Models\User;
use App\Models\WorkforceMember;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DisciplinaryProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $analystWithoutDisciplinary;

    protected User $analystWithDisciplinary;

    protected PerformanceCase $case;

    protected PerformanceCaseUpdate $update;

    protected string $secretDisciplinaryNote = 'CONFIDENCIAL: Amonestación escrita de RRHH por reiterado incumplimiento del protocolo.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@atlas.local')->first();
        $this->analystWithoutDisciplinary = User::where('email', 'analyst@atlas.local')->first();

        // Create an analyst with explicit cases.view_disciplinary permission
        $this->analystWithDisciplinary = User::factory()->create([
            'email' => 'hr_analyst@atlas.local',
            'name' => 'HR Operations Analyst',
        ]);
        $this->analystWithDisciplinary->assignRole('analyst');
        $this->analystWithDisciplinary->givePermission('cases.view_disciplinary');

        $agent = WorkforceMember::factory()->agent()->create();

        $this->case = PerformanceCase::factory()->create([
            'workforce_member_id' => $agent->id,
            'status' => 'monitoring',
        ]);

        $this->update = PerformanceCaseUpdate::create([
            'performance_case_id' => $this->case->id,
            'created_by_user_id' => $this->admin->id,
            'previous_status' => 'open',
            'resulting_status' => 'monitoring',
            'summary' => 'Reunión formal de revisión de desempeño.',
            'observations' => 'Observaciones generales del supervisor.',
            'actions' => 'Seguimiento quincenal.',
            'commitments' => 'Cumplir con la meta de CSAT.',
            'metrics_snapshot' => ['nps' => 0.20, 'volume' => 15],
            'disciplinary_details' => $this->secretDisciplinaryNote,
        ]);
    }

    public function test_disciplinary_details_are_encrypted_in_database(): void
    {
        // Direct database query without Eloquent cast decryption
        $rawRecord = DB::table('performance_case_updates')->where('id', $this->update->id)->first();
        $this->assertNotNull($rawRecord);

        // The stored text in database must NOT equal the plain secret note
        $this->assertNotEquals($this->secretDisciplinaryNote, $rawRecord->disciplinary_details);
        $this->assertStringNotContainsString('Amonestación escrita', $rawRecord->disciplinary_details);

        // Through Eloquent model, it decrypts cleanly
        $decryptedUpdate = PerformanceCaseUpdate::find($this->update->id);
        $this->assertEquals($this->secretDisciplinaryNote, $decryptedUpdate->disciplinary_details);
    }

    public function test_disciplinary_details_are_hidden_from_default_array_and_json_serialization(): void
    {
        $array = $this->update->toArray();
        $this->assertArrayNotHasKey('disciplinary_details', $array);

        $json = json_encode($this->update);
        $this->assertStringNotContainsString('disciplinary_details', $json);
        $this->assertStringNotContainsString('Amonestación escrita', $json);
    }

    public function test_analyst_without_disciplinary_permission_is_forbidden_from_viewing_details(): void
    {
        // Standard analyst can view the case
        $this->actingAs($this->analystWithoutDisciplinary)
            ->get("/performance-cases/{$this->case->id}")
            ->assertOk();

        // But is 403 Forbidden when requesting the disciplinary detail
        $response = $this->actingAs($this->analystWithoutDisciplinary)
            ->getJson("/performance-cases/{$this->case->id}/updates/{$this->update->id}/disciplinary");

        $response->assertForbidden();
    }

    public function test_authorized_user_can_view_disciplinary_details_and_action_is_audited_safely(): void
    {
        $response = $this->actingAs($this->analystWithDisciplinary)
            ->getJson("/performance-cases/{$this->case->id}/updates/{$this->update->id}/disciplinary");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('disciplinary_details', $this->secretDisciplinaryNote);

        // Assert audit log was registered
        $audit = AuditEvent::where('event_type', 'DISCIPLINARY_DETAIL_ACCESSED')
            ->where('auditable_id', (string) $this->update->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->analystWithDisciplinary->id, $audit->user_id);
        $this->assertEquals($this->case->id, $audit->payload['case_id']);

        // CRITICAL: The audit payload must NEVER contain the secret disciplinary text!
        $auditPayloadJson = json_encode($audit->payload);
        $this->assertStringNotContainsString($this->secretDisciplinaryNote, $auditPayloadJson);
        $this->assertStringNotContainsString('Amonestación escrita', $auditPayloadJson);
    }
}
