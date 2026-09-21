<?php

namespace Tests\Feature;

use App\Jobs\GenerateTeamReportJob;
use App\Models\Import;
use App\Models\PerformanceCase;
use App\Models\PerformanceCaseUpdate;
use App\Models\Role;
use App\Models\Survey;
use App\Models\Team;
use App\Models\TeamReport;
use App\Models\User;
use App\Models\WorkforceMember;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Reports\AiTeamReportNarrativeService;
use App\Services\Reports\RunChartSvgService;
use App\Services\Reports\TeamPdfRenderer;
use App\Services\Reports\TeamReportDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeamReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected User $reportManager;

    protected User $regularUser;

    protected Team $team;

    protected WorkforceMember $supervisor;

    protected WorkforceMember $agent;

    protected Import $import;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed();

        $this->import = Import::create([
            'original_filename' => 'test.xlsx',
            'file_hash' => 'dummy_hash',
            'sheet_name' => 'Sheet1',
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        $this->supervisor = WorkforceMember::factory()->create([
            'role' => 'supervisor',
            'name' => 'Supervisor Diana Prince',
        ]);

        $this->team = Team::factory()->create([
            'name' => 'Team Titans',
            'code' => 'TT-01',
            'supervisor_id' => $this->supervisor->id,
        ]);

        $this->agent = WorkforceMember::factory()->create([
            'role' => 'agent',
            'name' => 'Agente Dick Grayson',
            'external_id' => '1001',
        ]);

        $this->reportManager = User::factory()->create();
        $this->reportManager->roles()->sync(Role::where('slug', 'administrator')->pluck('id'));

        $this->regularUser = User::factory()->create();
    }

    public function test_unauthorized_user_cannot_access_reports_or_generate(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('reports.teams.index'))
            ->assertForbidden();

        $this->actingAs($this->regularUser)
            ->postJson(route('reports.teams.generate'), [
                'team_ids' => [$this->team->id],
                'period_from' => '2026-01-01',
                'period_to' => '2026-01-31',
            ])
            ->assertForbidden();
    }

    public function test_preview_returns_accurate_metrics_and_sanitized_open_cases(): void
    {
        // Create 2 surveys for this team with verbatims
        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9001,
            'survey_date' => '2026-01-10',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 10,
            'csat_score' => 5,
            'professionalism_score' => 5,
            'verbatim' => 'Excelente atencion muy paciente y atento',
            'record_hash' => 'hash_9001',
        ]);

        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9002,
            'survey_date' => '2026-01-15',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 8,
            'csat_score' => 4,
            'professionalism_score' => 5,
            'verbatim' => 'Todo bien pero tardaron un poco en contestar',
            'record_hash' => 'hash_9002',
        ]);

        // Create an open case with disciplinary note for this agent
        $case = PerformanceCase::factory()->create([
            'workforce_member_id' => $this->agent->id,
            'status' => 'under_review',
            'reason' => 'Bajo CSAT en llamadas de soporte',
        ]);

        PerformanceCaseUpdate::factory()->create([
            'performance_case_id' => $case->id,
            'disciplinary_details' => 'SUPER SECRET DISCIPLINARY SANCTION INFO',
        ]);

        $response = $this->actingAs($this->reportManager)
            ->postJson(route('reports.teams.preview'), [
                'team_id' => $this->team->id,
                'period_from' => '2026-01-01',
                'period_to' => '2026-01-31',
                'include_open_cases' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.team.name', 'Team Titans')
            ->assertJsonPath('data.metrics.survey_volume', 2)
            ->assertJsonPath('data.is_low_sample', true)
            ->assertJsonStructure([
                'data' => [
                    'team',
                    'period',
                    'data_version',
                    'is_low_sample',
                    'metrics',
                    'daily_trends',
                    'agent_reviews',
                    'open_cases',
                ],
            ]);

        $responseData = $response->json();
        $responseContent = json_encode($responseData);

        // Strictly verify NO disciplinary notes leaked in the response!
        $this->assertStringNotContainsString('SUPER SECRET DISCIPLINARY SANCTION INFO', $responseContent);
        $this->assertCount(1, $responseData['data']['open_cases']);
        $this->assertEquals($case->case_number, $responseData['data']['open_cases'][0]['case_number']);

        // Verify that all agent verbatims were properly captured
        $this->assertCount(1, $responseData['data']['agent_reviews']);
        $agentReview = $responseData['data']['agent_reviews'][0];
        $this->assertCount(2, $agentReview['verbatims']);
        $this->assertEquals('Excelente atencion muy paciente y atento', $agentReview['verbatims'][0]['verbatim']);
        $this->assertEquals('promoter', $agentReview['verbatims'][0]['sentiment']);
        $this->assertEquals('passive', $agentReview['verbatims'][1]['sentiment']);
    }

    public function test_generate_queues_job_and_creates_pending_report(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->reportManager)
            ->postJson(route('reports.teams.generate'), [
                'team_ids' => [$this->team->id],
                'period_from' => '2026-01-01',
                'period_to' => '2026-01-31',
                'compare_previous_period' => true,
                'include_open_cases' => true,
            ]);

        $response->assertStatus(202);

        $this->assertDatabaseHas('team_reports', [
            'team_id' => $this->team->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(GenerateTeamReportJob::class);
    }

    public function test_full_report_generation_job_with_dompdf_and_hash(): void
    {
        // Seed surveys
        for ($i = 1; $i <= 16; $i++) {
            Survey::create([
                'import_id' => $this->import->id,
                'survey_id' => 9100 + $i,
                'survey_date' => '2026-01-10',
                'agent_id' => $this->agent->id,
                'agent_bms' => $this->agent->external_id,
                'agent_name' => $this->agent->name,
                'team_id' => $this->team->id,
                'supervisor_id' => $this->supervisor->id,
                'supervisor' => $this->supervisor->name,
                'nps_score' => 10,
                'csat_score' => 5,
                'professionalism_score' => 5,
                'record_hash' => "hash_910{$i}",
            ]);
        }

        $report = TeamReport::create([
            'team_id' => $this->team->id,
            'period_from' => '2026-01-01',
            'period_to' => '2026-01-31',
            'cutoff_date' => '2026-01-31',
            'data_version' => 'initial_hash',
            'status' => 'pending',
            'created_by_user_id' => $this->reportManager->id,
        ]);

        $job = new GenerateTeamReportJob($report);
        $job->handle(
            app(TeamReportDataService::class),
            app(AiTeamReportNarrativeService::class),
            app(TeamPdfRenderer::class)
        );

        $report->refresh();

        $this->assertEquals('completed', $report->status);
        $this->assertNotNull($report->file_path);
        $this->assertNotNull($report->file_hash);
        $this->assertGreaterThan(0, $report->file_size);
        $this->assertIsArray($report->narrative);
        $this->assertArrayHasKey('executive_summary', $report->narrative);
        $this->assertArrayHasKey('team_strengths', $report->narrative);

        // Verify PDF file was written to disk
        $this->assertTrue(Storage::disk('local')->exists($report->file_path));
        $fileBytes = Storage::disk('local')->get($report->file_path);
        $this->assertEquals(hash('sha256', $fileBytes), $report->file_hash);

        // Test Download endpoint
        $downloadResponse = $this->actingAs($this->reportManager)
            ->get(route('reports.teams.download', $report));

        $downloadResponse->assertOk();
        $this->assertEquals('application/pdf', $downloadResponse->headers->get('content-type'));
    }

    public function test_ai_narrative_uses_deterministic_fallback_when_provider_throws(): void
    {
        // Bind a throwing provider
        $throwingProvider = new class implements AiProvider
        {
            public function providerName(): string
            {
                return 'failing-provider';
            }

            public function generate(array $messages, array $tools = [], array $options = []): array
            {
                throw new \RuntimeException('AI Service Down / Timeout');
            }
        };

        $narrativeService = new AiTeamReportNarrativeService($throwingProvider);
        $dataService = app(TeamReportDataService::class);

        $reportData = $dataService->buildReportData($this->team, '2026-01-01', '2026-01-31');
        $result = $narrativeService->generateNarrative($reportData);

        $this->assertTrue($result['is_fallback']);
        $this->assertEquals('deterministic-rules-engine', $result['model']);
        $this->assertNotEmpty($result['narrative']['executive_summary']);
        $this->assertNotEmpty($result['narrative']['team_strengths']);
        $this->assertNotEmpty($result['narrative']['team_risks']);
        $this->assertNotEmpty($result['narrative']['recommended_actions']);

        if (! empty($result['narrative']['agent_reviews'])) {
            $firstAgentReview = $result['narrative']['agent_reviews'][0];
            $this->assertArrayHasKey('verbatim_analysis', $firstAgentReview);
            $this->assertArrayHasKey('strengths', $firstAgentReview);
            $this->assertArrayHasKey('friction_points', $firstAgentReview);
        }
    }

    public function test_full_report_generation_renders_agent_dossiers_and_all_verbatims(): void
    {
        // Seed 3 surveys for the agent with diverse verbatims
        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9301,
            'survey_date' => '2026-01-05',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 10,
            'csat_score' => 5,
            'professionalism_score' => 5,
            'verbatim' => 'Excelente servicio y resolucion inmediata',
            'record_hash' => 'hash_9301',
        ]);

        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9302,
            'survey_date' => '2026-01-12',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 3,
            'csat_score' => 2,
            'professionalism_score' => 4,
            'verbatim' => 'Demora excesiva en la llamada y no resolvieron el trámite',
            'record_hash' => 'hash_9302',
        ]);

        $report = TeamReport::create([
            'team_id' => $this->team->id,
            'period_from' => '2026-01-01',
            'period_to' => '2026-01-31',
            'cutoff_date' => '2026-01-31',
            'data_version' => 'agent_dossier_hash',
            'status' => 'pending',
            'created_by_user_id' => $this->reportManager->id,
        ]);

        $job = new GenerateTeamReportJob($report);
        $job->handle(
            app(TeamReportDataService::class),
            app(AiTeamReportNarrativeService::class),
            app(TeamPdfRenderer::class)
        );

        $report->refresh();

        $this->assertEquals('completed', $report->status);
        $this->assertTrue(Storage::disk('local')->exists($report->file_path));

        // Check metrics_data saved on the report
        $this->assertArrayHasKey('agent_reviews', $report->metrics_data);
        $this->assertCount(1, $report->metrics_data['agent_reviews']);
        $agentData = $report->metrics_data['agent_reviews'][0];
        $this->assertCount(2, $agentData['verbatims']);
        $this->assertEquals(1, $agentData['promoters_count']);
        $this->assertEquals(1, $agentData['detractors_count']);
        $this->assertArrayHasKey('daily_trends', $agentData);
        $this->assertCount(2, $agentData['daily_trends']);
        $this->assertEquals('2026-01-05', $agentData['daily_trends'][0]['date']);
        $this->assertEquals('2026-01-12', $agentData['daily_trends'][1]['date']);
    }

    public function test_team_and_agent_run_charts_are_generated(): void
    {
        // Seed surveys on different dates
        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9401,
            'survey_date' => '2026-01-02',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 10,
            'csat_score' => 5,
            'professionalism_score' => 5,
            'verbatim' => 'Excelente todo',
            'record_hash' => 'hash_9401',
        ]);

        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9402,
            'survey_date' => '2026-01-03',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 2,
            'csat_score' => 1,
            'professionalism_score' => 2,
            'verbatim' => 'Muy mal servicio',
            'record_hash' => 'hash_9402',
        ]);

        $dataService = app(TeamReportDataService::class);
        $data = $dataService->buildReportData($this->team, '2026-01-01', '2026-01-31');

        $this->assertNotEmpty($data['daily_trends']);
        $this->assertCount(2, $data['daily_trends']);
        $this->assertNotEmpty($data['agent_reviews'][0]['daily_trends']);
        $this->assertCount(2, $data['agent_reviews'][0]['daily_trends']);

        // Verify RunChartSvgService generates valid SVG
        $teamSvg = RunChartSvgService::render($data['daily_trends'], 0.50, 0.80, 520, 110, false);
        $this->assertStringContainsString('<svg', $teamSvg);
        $this->assertStringContainsString('<polyline', $teamSvg);
        $this->assertStringContainsString('NPS', $teamSvg);

        $agentSvg = RunChartSvgService::render($data['agent_reviews'][0]['daily_trends'], 0.50, 0.80, 500, 75, true);
        $this->assertStringContainsString('<svg', $agentSvg);
        $this->assertStringContainsString('<polyline', $agentSvg);

        // Verify PDF renders cleanly with SVG included
        $report = TeamReport::create([
            'team_id' => $this->team->id,
            'period_from' => '2026-01-01',
            'period_to' => '2026-01-31',
            'cutoff_date' => '2026-01-31',
            'data_version' => 'runchart_test',
            'status' => 'pending',
            'created_by_user_id' => $this->reportManager->id,
        ]);

        $pdfRenderer = app(TeamPdfRenderer::class);
        $narrative = [
            'executive_summary' => 'Resumen de prueba',
            'team_strengths' => ['Fuerza 1'],
            'team_risks' => ['Riesgo 1'],
            'agent_reviews' => [
                [
                    'agent_name' => $this->agent->name,
                    'assessment' => 'Buen trabajo',
                    'action' => 'Seguimiento',
                    'verbatim_analysis' => 'Verbatims analizados',
                    'strengths' => ['Atencion'],
                    'friction_points' => ['Tiempo'],
                ],
            ],
            'recommended_actions' => ['Accion 1'],
            'data_quality_notes' => 'Notas',
        ];

        $rendered = $pdfRenderer->renderAndStore($report, $data, $narrative);
        $this->assertNotEmpty($rendered['content']);
        $this->assertStringStartsWith('%PDF', $rendered['content']);
        $this->assertGreaterThan(0, $rendered['file_size']);
        $this->assertTrue(Storage::disk('local')->exists($rendered['file_path']));
    }

    public function test_download_zip_packages_multiple_reports(): void
    {
        $report1 = TeamReport::create([
            'team_id' => $this->team->id,
            'period_from' => '2026-01-01',
            'period_to' => '2026-01-31',
            'cutoff_date' => '2026-01-31',
            'data_version' => 'version_1',
            'status' => 'completed',
            'file_path' => 'reports/test_1.pdf',
            'file_hash' => hash('sha256', '%PDF-dummy-1'),
            'file_size' => 12,
        ]);
        Storage::disk('local')->put('reports/test_1.pdf', '%PDF-dummy-1');

        $report2 = TeamReport::create([
            'team_id' => $this->team->id,
            'period_from' => '2026-02-01',
            'period_to' => '2026-02-28',
            'cutoff_date' => '2026-02-28',
            'data_version' => 'version_2',
            'status' => 'completed',
            'file_path' => 'reports/test_2.pdf',
            'file_hash' => hash('sha256', '%PDF-dummy-2'),
            'file_size' => 12,
        ]);
        Storage::disk('local')->put('reports/test_2.pdf', '%PDF-dummy-2');

        $response = $this->actingAs($this->reportManager)
            ->post(route('reports.teams.download-zip'), [
                'report_ids' => [$report1->id, $report2->id],
            ]);

        $response->assertOk();
        $this->assertStringContainsString('zip', $response->headers->get('content-type'));
    }

    public function test_report_generation_with_custom_goals_propagates_to_metrics_and_pdf(): void
    {
        // Create surveys where NPS is 0.50 (above default 0.50 is meets_goal, but below custom 0.75)
        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9101,
            'survey_date' => '2026-01-10',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 0.60,
            'csat_score' => 0.80,
            'professionalism_score' => 0.90,
            'record_hash' => 'hash_9101',
        ]);

        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9102,
            'survey_date' => '2026-01-12',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 0.40,
            'csat_score' => 0.80,
            'professionalism_score' => 0.90,
            'record_hash' => 'hash_9102',
        ]);

        $customGoals = [
            'nps' => ['target_value' => 0.75, 'warning_threshold' => 0.40],
            'csat' => ['target_value' => 0.90, 'warning_threshold' => 0.80],
            'professionalism' => ['target_value' => 0.95, 'warning_threshold' => 0.85],
        ];

        // 1. Preview endpoint with custom goals
        $previewRes = $this->actingAs($this->reportManager)
            ->postJson(route('reports.teams.preview'), [
                'team_id' => $this->team->id,
                'period_from' => '2026-01-01',
                'period_to' => '2026-01-31',
                'goals' => $customGoals,
            ]);

        $previewRes->assertOk();
        $previewData = $previewRes->json('data');
        $this->assertEquals(0.75, $previewData['goals']['nps']['target_value']);
        $this->assertEquals(0.90, $previewData['goals']['csat']['target_value']);
        // With 1 promoter and 1 passive, NPS is (1-0)/2 = +0.50
        // Under default goal (0.50) it would meet goal, but under custom goal (0.75) it does NOT meet goal
        $this->assertFalse($previewData['metrics']['goals_comparison']['nps']['meets_goal']);

        // 2. Generate endpoint with custom goals
        Queue::fake([GenerateTeamReportJob::class]);

        $genRes = $this->actingAs($this->reportManager)
            ->postJson(route('reports.teams.generate'), [
                'team_ids' => [$this->team->id],
                'period_from' => '2026-01-01',
                'period_to' => '2026-01-31',
                'goals' => $customGoals,
            ]);

        $genRes->assertStatus(202);

        $report = TeamReport::where('team_id', $this->team->id)->latest('id')->first();
        $this->assertNotNull($report);
        $this->assertArrayHasKey('goals', $report->parameters);
        $this->assertEquals(0.75, $report->parameters['goals']['nps']['target_value']);

        // 3. Execute the job and verify saved metrics and generated PDF
        $job = new GenerateTeamReportJob($report);
        $job->handle(
            app(TeamReportDataService::class),
            app(AiTeamReportNarrativeService::class),
            app(TeamPdfRenderer::class)
        );

        $report->refresh();
        $this->assertEquals('completed', $report->status);
        $this->assertEquals(0.75, $report->metrics_data['goals']['nps']['target_value']);
        $this->assertFalse($report->metrics_data['metrics']['goals_comparison']['nps']['meets_goal']);
        $this->assertTrue(Storage::disk('local')->exists($report->file_path));
    }

    public function test_nps_sentiment_classification_handles_both_normalized_and_raw_scales(): void
    {
        // 1. Normalized [-1.0, 1.0] scale
        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9201,
            'survey_date' => '2026-01-20',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 1.0, // Promoter on [-1, 1]
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'verbatim' => 'Excelente servicio y resolucion',
            'record_hash' => 'hash_9201',
        ]);

        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9202,
            'survey_date' => '2026-01-21',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => -1.0, // Detractor on [-1, 1]
            'csat_score' => 0.0,
            'professionalism_score' => 0.0,
            'verbatim' => 'Muy mala experiencia',
            'record_hash' => 'hash_9202',
        ]);

        Survey::create([
            'import_id' => $this->import->id,
            'survey_id' => 9203,
            'survey_date' => '2026-01-22',
            'agent_id' => $this->agent->id,
            'agent_bms' => $this->agent->external_id,
            'agent_name' => $this->agent->name,
            'team_id' => $this->team->id,
            'supervisor_id' => $this->supervisor->id,
            'supervisor' => $this->supervisor->name,
            'nps_score' => 0.0, // Passive on [-1, 1]
            'csat_score' => 0.5,
            'professionalism_score' => 0.5,
            'verbatim' => 'Servicio neutral',
            'record_hash' => 'hash_9203',
        ]);

        $previewRes = $this->actingAs($this->reportManager)
            ->postJson(route('reports.teams.preview'), [
                'team_id' => $this->team->id,
                'period_from' => '2026-01-20',
                'period_to' => '2026-01-25',
                'include_verbatims' => true,
            ]);

        $previewRes->assertOk();
        $agentReviews = $previewRes->json('data.agent_reviews');
        $this->assertCount(1, $agentReviews);

        $agent = $agentReviews[0];
        $this->assertEquals(1, $agent['promoters_count'], 'NPS 1.0 must be counted as promoter');
        $this->assertEquals(1, $agent['detractors_count'], 'NPS -1.0 must be counted as detractor');
        $this->assertEquals(1, $agent['passives_count'], 'NPS 0.0 must be counted as passive');

        // Check sentiments on verbatims array
        $sentiments = collect($agent['verbatims'])->pluck('sentiment', 'nps_score')->toArray();
        $this->assertEquals('promoter', $sentiments['1']);
        $this->assertEquals('detractor', $sentiments['-1']);
        $this->assertEquals('passive', $sentiments['0']);
    }
}
