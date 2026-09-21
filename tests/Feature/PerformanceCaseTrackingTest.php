<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\PerformanceCase;
use App\Models\PerformanceCaseUpdate;
use App\Models\Survey;
use App\Models\Team;
use App\Models\TeamMembership;
use App\Models\User;
use App\Models\WorkforceMember;
use App\Services\Cases\CaseRecalculationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceCaseTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $analyst;

    protected User $admin;

    protected User $unauthorizedUser;

    protected WorkforceMember $agent;

    protected WorkforceMember $supervisor;

    protected Team $team;

    protected Import $import;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->admin = User::where('email', 'admin@atlas.local')->first();
        $this->analyst = User::where('email', 'analyst@atlas.local')->first();
        $this->unauthorizedUser = User::factory()->create(); // No roles

        $this->supervisor = WorkforceMember::factory()->supervisor()->create(['name' => 'Marcos Ramos']);
        $this->team = Team::factory()->create([
            'name' => 'Equipo Marcos Ramos',
            'supervisor_id' => $this->supervisor->id,
        ]);

        $this->agent = WorkforceMember::factory()->agent()->create([
            'name' => 'Lucia Mendez',
            'external_id' => 'BMS-444',
        ]);

        TeamMembership::create([
            'team_id' => $this->team->id,
            'workforce_member_id' => $this->agent->id,
            'role' => 'agent',
            'effective_from' => now()->subMonths(3)->toDateString(),
            'effective_to' => null,
        ]);

        $this->import = Import::create([
            'original_filename' => 'cases_test.xlsx',
            'file_hash' => 'hash_cases',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => ['nps_score' => 'NPS'],
            'row_count' => 5,
            'accepted_rows' => 5,
            'status' => 'completed',
            'uploaded_by' => $this->analyst->id,
        ]);
    }

    public function test_analyst_can_create_performance_case_for_agent_and_initial_recalculation_is_run(): void
    {
        // Add surveys for the agent
        Survey::create([
            'survey_id' => 'SRV-C1',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-444',
            'agent_id' => $this->agent->id,
            'supervisor' => 'Marcos Ramos',
            'supervisor_id' => $this->supervisor->id,
            'team_id' => $this->team->id,
            'survey_date' => now()->subDays(5)->toDateString(),
            'record_hash' => 'h_c1',
            'import_id' => $this->import->id,
        ]);

        $payload = [
            'workforce_member_id' => $this->agent->id,
            'type' => 'nps_improvement',
            'reason' => 'Bajo NPS acumulado en la última quincena',
            'priority' => 'high',
            'opened_at' => now()->subDays(7)->toDateString(),
            'target_nps' => 0.50,
            'target_csat' => 0.85,
            'target_professionalism' => 0.90,
            'next_review_at' => now()->addDays(7)->toDateString(),
        ];

        $response = $this->actingAs($this->analyst)->post('/performance-cases', $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('performance_cases', [
            'workforce_member_id' => $this->agent->id,
            'priority' => 'high',
            'status' => 'open',
            'type' => 'nps_improvement',
        ]);

        $case = PerformanceCase::first();
        $this->assertNotNull($case);
        $this->assertStringStartsWith('CAS-', $case->case_number);

        // Check that Version 1 recalculation was created automatically
        $recalc = $case->recalculations()->where('version_number', 1)->first();
        $this->assertNotNull($recalc);
        $this->assertEquals('case_creation', $recalc->reason);

        // Daily results generated from opened_at to today (at least 8 days)
        $dailyCount = $recalc->dailyResults()->count();
        $this->assertGreaterThanOrEqual(7, $dailyCount);
    }

    public function test_can_create_case_for_supervisor(): void
    {
        $payload = [
            'workforce_member_id' => $this->supervisor->id,
            'type' => 'csat_recovery',
            'reason' => 'Desviación negativa de CSAT a nivel equipo',
            'priority' => 'critical',
            'opened_at' => now()->subDays(10)->toDateString(),
        ];

        $response = $this->actingAs($this->analyst)->post('/performance-cases', $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('performance_cases', [
            'workforce_member_id' => $this->supervisor->id,
            'target_type' => 'supervisor',
            'priority' => 'critical',
        ]);
    }

    public function test_analyst_can_view_all_cases_and_unauthorized_user_is_forbidden(): void
    {
        $case = PerformanceCase::factory()->create([
            'workforce_member_id' => $this->agent->id,
            'assigned_to_user_id' => $this->admin->id,
        ]);

        // Analyst has cases.view
        $response = $this->actingAs($this->analyst)->get('/performance-cases');
        $response->assertOk();

        $showResponse = $this->actingAs($this->analyst)->get("/performance-cases/{$case->id}");
        $showResponse->assertOk();

        // Unauthorized user without cases.view is forbidden
        $forbiddenResponse = $this->actingAs($this->unauthorizedUser)->get('/performance-cases');
        $forbiddenResponse->assertForbidden();

        $forbiddenShow = $this->actingAs($this->unauthorizedUser)->get("/performance-cases/{$case->id}");
        $forbiddenShow->assertForbidden();
    }

    public function test_days_without_sample_show_null_scores_and_has_sample_false(): void
    {
        $openedAt = now()->subDays(3)->toDateString();
        $surveyDate = now()->subDays(1)->toDateString();

        // Only one day has surveys (yesterday)
        Survey::create([
            'survey_id' => 'SRV-DS1',
            'nps_score' => 0.5,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-444',
            'agent_id' => $this->agent->id,
            'supervisor' => 'Marcos Ramos',
            'supervisor_id' => $this->supervisor->id,
            'team_id' => $this->team->id,
            'survey_date' => $surveyDate,
            'record_hash' => 'h_ds1',
            'import_id' => $this->import->id,
        ]);

        $case = PerformanceCase::factory()->create([
            'workforce_member_id' => $this->agent->id,
            'opened_at' => $openedAt,
        ]);

        $service = app(CaseRecalculationService::class);
        $recalc = $service->recalculate($case, $this->analyst, 'test_sample');

        // Check the day without sample (3 days ago)
        $noSampleDay = $recalc->dailyResults()->whereDate('date', $openedAt)->first();
        $this->assertNotNull($noSampleDay);
        $this->assertFalse($noSampleDay->has_sample);
        $this->assertEquals(0, $noSampleDay->survey_volume);
        $this->assertNull($noSampleDay->nps_score, 'Score must be NULL, never zero when no sample');
        $this->assertNull($noSampleDay->csat_score, 'Score must be NULL, never zero when no sample');
        $this->assertNull($noSampleDay->professionalism_score, 'Score must be NULL, never zero when no sample');

        // Check the day with sample (yesterday)
        $sampledDay = $recalc->dailyResults()->whereDate('date', $surveyDate)->first();
        $this->assertNotNull($sampledDay);
        $this->assertTrue($sampledDay->has_sample);
        $this->assertEquals(1, $sampledDay->survey_volume);
        $this->assertEquals(0.5, $sampledDay->nps_score);
    }

    public function test_logging_case_session_captures_immutable_metric_snapshot(): void
    {
        $case = PerformanceCase::factory()->create([
            'workforce_member_id' => $this->agent->id,
            'status' => 'open',
            'opened_at' => now()->subDays(5)->toDateString(),
        ]);

        // Add survey before session
        Survey::create([
            'survey_id' => 'SRV-SNAP',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-444',
            'agent_id' => $this->agent->id,
            'supervisor' => 'Marcos Ramos',
            'supervisor_id' => $this->supervisor->id,
            'team_id' => $this->team->id,
            'survey_date' => now()->subDays(2)->toDateString(),
            'record_hash' => 'h_snap',
            'import_id' => $this->import->id,
        ]);

        $sessionPayload = [
            'resulting_status' => 'monitoring',
            'summary' => 'Primera sesión de feedback y coaching.',
            'observations' => 'Se identificó área de mejora en manejo de objeciones.',
            'actions' => 'Capacitación en resolución en primer contacto.',
            'commitments' => 'Revisar 5 llamadas diarias con supervisor.',
            'next_review_at' => now()->addDays(5)->toDateString(),
        ];

        $response = $this->actingAs($this->analyst)->post("/performance-cases/{$case->id}/updates", $sessionPayload);
        $response->assertRedirect();

        $update = PerformanceCaseUpdate::where('performance_case_id', $case->id)->first();
        $this->assertNotNull($update);
        $this->assertEquals('open', $update->previous_status);
        $this->assertEquals('monitoring', $update->resulting_status);
        $this->assertNotNull($update->metrics_snapshot);
        $this->assertTrue($update->metrics_snapshot['metrics']['has_sample']);
        $this->assertEquals(1, $update->metrics_snapshot['metrics']['volume']);

        // Assert case was updated
        $case->refresh();
        $this->assertEquals('monitoring', $case->status);
    }

    public function test_recalculation_increments_version_preserves_history_and_allows_comparison(): void
    {
        $openedAt = now()->subDays(4)->toDateString();
        $case = PerformanceCase::factory()->create([
            'workforce_member_id' => $this->agent->id,
            'opened_at' => $openedAt,
        ]);

        $service = app(CaseRecalculationService::class);

        // Version 1: empty surveys
        $v1 = $service->recalculate($case, $this->analyst, 'v1_initial', null, now()->toDateString());
        $this->assertEquals(1, $v1->version_number);

        // Now new surveys arrive (e.g. from backfill or data import)
        Survey::create([
            'survey_id' => 'SRV-RECALC',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS-444',
            'agent_id' => $this->agent->id,
            'supervisor' => 'Marcos Ramos',
            'supervisor_id' => $this->supervisor->id,
            'team_id' => $this->team->id,
            'survey_date' => now()->subDays(2)->toDateString(),
            'record_hash' => 'h_recalc',
            'import_id' => $this->import->id,
        ]);

        // Version 2 recalculation via controller endpoint
        $recalcResponse = $this->actingAs($this->analyst)->postJson("/performance-cases/{$case->id}/recalculate", [
            'notes' => 'Recálculo tras sincronización de encuestas',
        ]);
        $recalcResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('version', 2);

        // Ensure both versions exist in database
        $this->assertDatabaseHas('case_metric_recalculations', [
            'performance_case_id' => $case->id,
            'version_number' => 1,
        ]);
        $this->assertDatabaseHas('case_metric_recalculations', [
            'performance_case_id' => $case->id,
            'version_number' => 2,
        ]);

        // Compare Version 1 and Version 2
        $compareResponse = $this->actingAs($this->analyst)->getJson("/performance-cases/{$case->id}/versions?version_a=1&version_b=2");
        $compareResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('comparison.has_differences', true);

        $comparison = $compareResponse->json('comparison');
        $changedDay = collect($comparison['days'])->firstWhere('is_different', true);
        $this->assertNotNull($changedDay);
        $this->assertEquals(1, $changedDay['diff']['volume_delta']);
    }

    public function test_show_case_view_includes_appended_metrics_and_recalculation_attributes(): void
    {
        $case = PerformanceCase::factory()->create([
            'workforce_member_id' => $this->agent->id,
            'opened_at' => now()->subDays(5)->toDateString(),
            'baseline' => [
                'nps' => 0.45,
                'csat' => 0.85,
                'professionalism' => 0.90,
                'volume' => 12,
                'period_from' => now()->subDays(35)->toDateString(),
                'period_to' => now()->subDays(5)->toDateString(),
            ],
        ]);

        $service = app(CaseRecalculationService::class);
        $service->recalculate($case, $this->analyst, 'case_creation');

        $response = $this->actingAs($this->analyst)->get("/performance-cases/{$case->id}");
        $response->assertOk();

        // Check Inertia props
        $props = $response->original->getData()['page']['props'];
        $this->assertArrayHasKey('caseItem', $props);
        $caseItem = $props['caseItem'];

        // Assert baseline_metrics accessor works
        $this->assertNotNull($caseItem['baseline_metrics']);
        $this->assertEquals(0.45, $caseItem['baseline_metrics']['nps_score']);
        $this->assertEquals(0.85, $caseItem['baseline_metrics']['csat_score']);
        $this->assertEquals(12, $caseItem['baseline_metrics']['survey_volume']);

        // Assert recalculation appends work
        $this->assertNotEmpty($caseItem['recalculations']);
        $recalc = $caseItem['recalculations'][0];
        $this->assertArrayHasKey('period_from', $recalc);
        $this->assertArrayHasKey('period_to', $recalc);
        $this->assertArrayHasKey('survey_volume', $recalc);
        $this->assertArrayHasKey('nps_score', $recalc);
        $this->assertArrayHasKey('csat_score', $recalc);
        $this->assertArrayHasKey('professionalism_score', $recalc);

        // Assert latest_recalculation is present and daily_results loaded
        $this->assertArrayHasKey('latest_recalculation', $caseItem);
        $this->assertNotNull($caseItem['latest_recalculation']);
        $this->assertNotEmpty($caseItem['latest_recalculation']['daily_results']);
    }
}
