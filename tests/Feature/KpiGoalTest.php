<?php

namespace Tests\Feature;

use App\Models\KpiGoal;
use App\Models\User;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Metrics\Dsl\QueryDslValidator;
use App\Services\Metrics\Dsl\QueryEngine;
use App\Services\Metrics\Dsl\QueryPlanner;
use App\Services\Metrics\Registry\MetricRegistry;
use App\Services\Privacy\DataMinimizerService;
use App\Services\Privacy\PseudonymService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KpiGoalTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_or_update_kpi_goals(): void
    {
        $this->getJson('/kpi-goals')->assertUnauthorized();
        $this->putJson('/kpi-goals', ['goals' => []])->assertUnauthorized();
    }

    public function test_can_retrieve_default_kpi_goals_with_nps_scale_minus_one_to_one(): void
    {
        $response = $this->actingAs($this->user)->getJson('/kpi-goals');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'goals' => [
                    'nps' => ['metric', 'name', 'target_value', 'warning_threshold', 'scale'],
                    'csat' => ['metric', 'name', 'target_value', 'warning_threshold', 'scale'],
                    'professionalism' => ['metric', 'name', 'target_value', 'warning_threshold', 'scale'],
                ],
                'formatted_context',
            ]);

        $data = $response->json('goals');
        // Check scale
        $this->assertEquals('-1 a 1', $data['nps']['scale']);
        $this->assertEquals(0.50, $data['nps']['target_value']);
        $this->assertEquals(50.0, $data['nps']['target_percentage']);
    }

    public function test_can_update_kpi_goals_and_audits_change(): void
    {
        $payload = [
            'goals' => [
                'nps' => [
                    'target_value' => 0.65, // Scale -1 to 1 (+65%)
                    'warning_threshold' => 0.30,
                    'description' => 'Meta Q4 aprobada por VP.',
                ],
                'csat' => [
                    'target_value' => 88, // Passed as percentage 88 -> should normalize to 0.88
                    'warning_threshold' => 75, // 75 -> 0.75
                    'description' => 'Meta CSAT 88%.',
                ],
                'professionalism' => [
                    'target_value' => 0.90,
                    'warning_threshold' => 0.80,
                    'description' => 'Meta Profesionalismo 90%.',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->putJson('/kpi-goals', $payload);

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Check database
        $this->assertDatabaseHas('kpi_goals', [
            'metric' => 'nps',
            'target_value' => 0.65,
            'warning_threshold' => 0.30,
            'description' => 'Meta Q4 aprobada por VP.',
        ]);

        $this->assertDatabaseHas('kpi_goals', [
            'metric' => 'csat',
            'target_value' => 0.88,
            'warning_threshold' => 0.75,
        ]);

        $this->assertDatabaseHas('kpi_goals', [
            'metric' => 'professionalism',
            'target_value' => 0.90,
            'warning_threshold' => 0.80,
        ]);

        // Verify cryptographic audit event recorded
        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'KPI_GOALS_UPDATED',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_ai_gateway_system_prompt_includes_kpi_goals_context(): void
    {
        // Update goals
        KpiGoal::updateOrCreate(
            ['metric' => 'nps'],
            ['target_value' => 0.55, 'warning_threshold' => 0.25, 'description' => 'Target custom test']
        );

        $context = KpiGoal::getFormattedContext();

        $this->assertStringContainsString('METAS OPERACIONALES Y BENCHMARKS ACTIVOS', $context);
        $this->assertStringContainsString('Net Promoter Score (NPS): Meta = +0.55 (+55.0%)', $context);
        $this->assertStringContainsString('escala decimal de -1.0 a +1.0', $context);
        $this->assertStringContainsString('Customer Satisfaction (CSAT)', $context);
        $this->assertStringContainsString('Professionalism Score', $context);
    }

    public function test_tool_registry_executes_get_kpi_goals_tool(): void
    {
        $pseudonymService = new PseudonymService;
        $registry = new MetricRegistry;
        $validator = new QueryDslValidator($registry);
        $planner = new QueryPlanner($registry);
        $queryEngine = new QueryEngine($validator, $planner);
        $minimizer = new DataMinimizerService($pseudonymService);
        $forecastEngine = new ForecastEngine($registry);

        $toolRegistry = new ToolRegistry(
            $queryEngine,
            $registry,
            $pseudonymService,
            $minimizer,
            $forecastEngine
        );

        // Execute get_kpi_goals tool
        $result = $toolRegistry->executeToolWithGrounding(
            toolName: 'get_kpi_goals',
            arguments: ['metric' => 'all'],
            scopeId: 'scope_test',
            user: $this->user
        );

        $this->assertArrayHasKey('result', $result);
        $this->assertTrue($result['result']['success']);
        $this->assertArrayHasKey('goals', $result['result']);
        $this->assertArrayHasKey('citations', $result);
        $this->assertNotEmpty($result['citations']);
        $this->assertStringContainsString('Metas operacionales', $result['citations'][0]['title']);
    }
}
