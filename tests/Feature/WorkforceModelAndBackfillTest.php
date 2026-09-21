<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Survey;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\WorkforceMember;
use App\Services\Organization\WorkforceBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkforceModelAndBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected Import $import;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->import = Import::create([
            'original_filename' => 'test.xlsx',
            'file_hash' => 'hash123',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => ['nps_score' => 'NPS'],
            'row_count' => 10,
            'accepted_rows' => 10,
            'status' => 'completed',
            'uploaded_by' => $user->id,
        ]);
    }

    public function test_idempotent_backfill_creates_workforce_members_teams_and_links_surveys(): void
    {
        // 1. Create test surveys
        Survey::create([
            'survey_id' => 'SRV-001',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-101',
            'agent_name' => 'Valeria Morales',
            'supervisor' => 'Ana Silva',
            'survey_date' => '2026-01-15',
            'record_hash' => 'hash1',
            'import_id' => $this->import->id,
        ]);

        Survey::create([
            'survey_id' => 'SRV-002',
            'nps_score' => 0.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-101',
            'agent_name' => 'Valeria Morales',
            'supervisor' => 'Ana Silva',
            'survey_date' => '2026-01-20',
            'record_hash' => 'hash2',
            'import_id' => $this->import->id,
        ]);

        Survey::create([
            'survey_id' => 'SRV-003',
            'nps_score' => -1.0,
            'csat_score' => 0.0,
            'professionalism_score' => 0.0,
            'agent_bms' => 'BMS-102',
            'agent_name' => 'Jorge Ramos',
            'supervisor' => 'Carlos Lopez',
            'survey_date' => '2026-02-05',
            'record_hash' => 'hash3',
            'import_id' => $this->import->id,
        ]);

        $service = app(WorkforceBackfillService::class);

        // Run 1st time
        $result1 = $service->run();

        $this->assertEquals(2, $result1['agents_processed']);
        $this->assertEquals(2, $result1['supervisors_processed']);
        $this->assertEquals(2, $result1['teams_created']);
        $this->assertEquals(2, $result1['memberships_created']);
        $this->assertEquals(3, $result1['surveys_linked']);
        $this->assertEmpty($result1['conflicts']);

        $this->assertDatabaseHas('workforce_members', [
            'external_id' => 'BMS-101',
            'name' => 'Valeria Morales',
            'role' => 'agent',
        ]);

        $this->assertDatabaseHas('workforce_members', [
            'name' => 'Ana Silva',
            'role' => 'supervisor',
        ]);

        $this->assertDatabaseHas('teams', [
            'name' => 'Equipo Ana Silva',
        ]);

        // Verify foreign keys linked on surveys
        $survey1 = Survey::where('survey_id', 'SRV-001')->first();
        $this->assertNotNull($survey1->agent_id);
        $this->assertNotNull($survey1->supervisor_id);
        $this->assertNotNull($survey1->team_id);
        $this->assertEquals('Valeria Morales', $survey1->agent->name);
        $this->assertEquals('Ana Silva', $survey1->supervisorMember->name);
        $this->assertEquals('Equipo Ana Silva', $survey1->team->name);

        // Run 2nd time: IDEMPOTENCY check
        $result2 = $service->run();
        $this->assertEquals(0, $result2['teams_created']);
        $this->assertEquals(0, $result2['memberships_created']);

        $this->assertEquals(2, WorkforceMember::where('role', 'agent')->count());
        $this->assertEquals(2, WorkforceMember::where('role', 'supervisor')->count());
        $this->assertEquals(2, Team::count());
    }

    public function test_stable_identity_by_agent_bms_and_name_conflict_detection(): void
    {
        Survey::create([
            'survey_id' => 'SRV-010',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-999',
            'agent_name' => 'Elena Gomez',
            'supervisor' => 'Ana Silva',
            'survey_date' => '2026-01-10',
            'record_hash' => 'hash10',
            'import_id' => $this->import->id,
        ]);

        Survey::create([
            'survey_id' => 'SRV-011',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-999',
            'agent_name' => 'Elena Gomez Diaz', // Name variation for same BMS
            'supervisor' => 'Ana Silva',
            'survey_date' => '2026-01-15',
            'record_hash' => 'hash11',
            'import_id' => $this->import->id,
        ]);

        $service = app(WorkforceBackfillService::class);
        $result = $service->run();

        // Exactly one agent record created
        $agents = WorkforceMember::where('external_id', 'BMS-999')->get();
        $this->assertCount(1, $agents);

        // Conflict reported
        $this->assertNotEmpty($result['conflicts']);
        $conflictTypes = array_column($result['conflicts'], 'type');
        $this->assertContains('name_divergence', $conflictTypes);
    }

    public function test_historical_memberships_and_team_transition_by_survey_date(): void
    {
        // Agent changes team from Ana Silva to Roberto Diaz
        Survey::create([
            'survey_id' => 'SRV-020',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-500',
            'agent_name' => 'Pedro Martinez',
            'supervisor' => 'Ana Silva',
            'survey_date' => '2026-01-05',
            'record_hash' => 'hash20',
            'import_id' => $this->import->id,
        ]);

        Survey::create([
            'survey_id' => 'SRV-021',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-500',
            'agent_name' => 'Pedro Martinez',
            'supervisor' => 'Ana Silva',
            'survey_date' => '2026-02-10',
            'record_hash' => 'hash21',
            'import_id' => $this->import->id,
        ]);

        Survey::create([
            'survey_id' => 'SRV-022',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-500',
            'agent_name' => 'Pedro Martinez',
            'supervisor' => 'Roberto Diaz',
            'survey_date' => '2026-03-01',
            'record_hash' => 'hash22',
            'import_id' => $this->import->id,
        ]);

        $service = app(WorkforceBackfillService::class);
        $service->run();

        $agent = WorkforceMember::where('external_id', 'BMS-500')->first();
        $this->assertNotNull($agent);

        $memberships = TeamMembership::where('workforce_member_id', $agent->id)
            ->orderBy('effective_from', 'asc')
            ->get();

        $this->assertCount(2, $memberships);

        // Membership 1: under Ana Silva
        $this->assertEquals('2026-01-05', $memberships[0]->effective_from->toDateString());
        $this->assertEquals('2026-02-10', $memberships[0]->effective_to->toDateString());

        // Membership 2: under Roberto Diaz (current, so effective_to is null)
        $this->assertEquals('2026-03-01', $memberships[1]->effective_from->toDateString());
        $this->assertNull($memberships[1]->effective_to);

        // Test team resolution at dates
        $teamJan = $agent->resolveTeamAtDate('2026-01-20');
        $this->assertNotNull($teamJan);
        $this->assertEquals('Equipo Ana Silva', $teamJan->name);

        $teamMarch = $agent->resolveTeamAtDate('2026-03-15');
        $this->assertNotNull($teamMarch);
        $this->assertEquals('Equipo Roberto Diaz', $teamMarch->name);
    }

    public function test_same_day_multiple_supervisors_conflict_detection(): void
    {
        Survey::create([
            'survey_id' => 'SRV-030',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-700',
            'agent_name' => 'Laura Paz',
            'supervisor' => 'Ana Silva',
            'survey_date' => '2026-02-14',
            'record_hash' => 'hash30',
            'import_id' => $this->import->id,
        ]);

        Survey::create([
            'survey_id' => 'SRV-031',
            'nps_score' => 0.0,
            'csat_score' => 0.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-700',
            'agent_name' => 'Laura Paz',
            'supervisor' => 'Roberto Diaz', // Same day different supervisor!
            'survey_date' => '2026-02-14',
            'record_hash' => 'hash31',
            'import_id' => $this->import->id,
        ]);

        $service = app(WorkforceBackfillService::class);
        $result = $service->run();

        $this->assertNotEmpty($result['conflicts']);
        $conflictTypes = array_column($result['conflicts'], 'type');
        $this->assertContains('same_date_multiple_supervisors', $conflictTypes);
    }
}
