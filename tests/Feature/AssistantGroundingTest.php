<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\Import;
use App\Models\Permission;
use App\Models\Survey;
use App\Models\User;
use App\Models\VerbatimAnalysis;
use App\Services\Ai\Gateway\AiGateway;
use App\Services\Ai\Providers\MockAiProvider;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Audit\AuditService;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Metrics\Dsl\QueryDslValidator;
use App\Services\Metrics\Dsl\QueryEngine;
use App\Services\Metrics\Dsl\QueryPlanner;
use App\Services\Metrics\Registry\MetricRegistry;
use App\Services\Privacy\DataMinimizerService;
use App\Services\Privacy\PiiScrubberService;
use App\Services\Privacy\PseudonymService;
use App\Services\Privacy\ReidentificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantGroundingTest extends TestCase
{
    use RefreshDatabase;

    protected AiGateway $gateway;

    protected User $user;

    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $pseudonymService = new PseudonymService;
        $scrubber = new PiiScrubberService($pseudonymService);
        $reid = new ReidentificationService($pseudonymService);
        $audit = new AuditService;

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

        $provider = new MockAiProvider;

        $this->gateway = new AiGateway(
            $provider,
            $toolRegistry,
            $scrubber,
            $reid,
            $audit
        );

        $this->user = User::create([
            'name' => 'Analyst User',
            'email' => 'analyst@atlas.local',
            'password' => bcrypt('password'),
        ]);

        $chatPerm = Permission::create(['name' => 'AI Chat', 'slug' => 'ai.chat']);
        $this->user->directPermissions()->attach([$chatPerm->id]);

        $this->conversation = Conversation::create([
            'user_id' => $this->user->id,
            'title' => 'Grounding Test Chat',
        ]);
    }

    public function test_gateway_generates_grounding_citations_and_tool_durations(): void
    {
        $import = Import::create([
            'original_filename' => 'grounding.xlsx',
            'file_hash' => 'hash_grounding',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_G1',
            'nps_score' => 0.8,
            'csat_score' => 0.9,
            'professionalism_score' => 0.85,
            'agent_bms' => 'BMS_1001',
            'supervisor' => 'Laura Gomez',
            'survey_date' => '2026-09-01',
            'record_hash' => 'hash_g1',
            'import_id' => $import->id,
        ]);

        $result = $this->gateway->runAssistant(
            userInput: 'Compara el CSAT y el NPS de los supervisores',
            conversationId: (string) $this->conversation->id,
            conversationHistory: [],
            user: $this->user
        );

        // Verify grounding citations
        $this->assertNotEmpty($result['grounding_context'], 'Grounding citations should be populated');
        $citation = $result['grounding_context'][0];
        $this->assertArrayHasKey('title', $citation);
        $this->assertArrayHasKey('factType', $citation);
        $this->assertArrayHasKey('queryHash', $citation);
        $this->assertContains($citation['factType'], ['fact', 'calculation', 'structure', 'interpretation']);

        // Verify tool executions telemetry
        $this->assertNotEmpty($result['tool_executions']);
        $toolLog = $result['tool_executions'][0];
        $this->assertEquals('query_data', $toolLog['tool_name']);
        $this->assertArrayHasKey('duration_ms', $toolLog);
        $this->assertArrayHasKey('parameters_redacted', $toolLog);
        $this->assertEquals('success', $toolLog['status']);

        // Verify database persistence in ai_tool_calls
        $this->assertDatabaseHas('ai_tool_calls', [
            'ai_run_id' => $result['ai_run_id'],
            'tool_name' => 'query_data',
            'status' => 'success',
        ]);
    }

    public function test_controller_sends_message_and_persists_grounding_context(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson("/assistant/conversations/{$this->conversation->id}/messages", [
            'content' => '¿Cuál es el NPS promedio?',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'user_message' => ['id', 'display_content', 'sender_type'],
            'assistant_message' => ['id', 'display_content', 'sender_type', 'grounding_context', 'tokens_used'],
            'ai_run_id',
        ]);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender_type' => 'assistant',
        ]);

        $this->assertDatabaseHas('audit_events', [
            'event_type' => 'AI_MESSAGE_SENT',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_delete_own_conversation(): void
    {
        $this->actingAs($this->user);

        $response = $this->deleteJson("/assistant/conversations/{$this->conversation->id}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('conversations', [
            'id' => $this->conversation->id,
        ]);
    }

    public function test_query_raw_data_returns_scrubbed_verbatims_and_pseudonymized_identities(): void
    {
        $import = Import::create([
            'original_filename' => 'raw_test.xlsx',
            'file_hash' => 'hash_raw_test',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_RAW_001',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS_2001',
            'agent_name' => 'Carlos Perez',
            'supervisor' => 'Maria Gonzalez',
            'verbatim' => 'Excelente soporte. Mi email es cliente@example.com y teléfono 555-123-4567.',
            'survey_date' => '2026-09-02',
            'wave' => 'W1',
            'tenure_days' => 45,
            'record_hash' => 'hash_raw_001',
            'import_id' => $import->id,
        ]);

        $pseudonymService = new PseudonymService;
        $minimizer = new DataMinimizerService($pseudonymService);
        $registry = new MetricRegistry;
        $validator = new QueryDslValidator($registry);
        $planner = new QueryPlanner($registry);
        $queryEngine = new QueryEngine($validator, $planner);
        $forecastEngine = new ForecastEngine($registry);

        $toolRegistry = new ToolRegistry(
            $queryEngine,
            $registry,
            $pseudonymService,
            $minimizer,
            $forecastEngine
        );

        $result = $toolRegistry->executeToolWithGrounding(
            toolName: 'query_raw_data',
            arguments: ['limit' => 5],
            scopeId: (string) $this->conversation->id
        );

        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('records', $result['result']);
        $this->assertNotEmpty($result['result']['records']);

        $record = $result['result']['records'][0];
        $this->assertArrayHasKey('record_ref', $record);
        $this->assertArrayHasKey('verbatim', $record);
        $this->assertArrayHasKey('supervisor_ref', $record);

        // Verify PII was scrubbed from verbatim
        $this->assertStringNotContainsString('cliente@example.com', $record['verbatim']);
        $this->assertStringContainsString('[EMAIL_REDACTED]', $record['verbatim']);
        $this->assertStringNotContainsString('555-123-4567', $record['verbatim']);

        // Verify supervisor was pseudonymized
        $this->assertStringNotContainsString('Maria Gonzalez', $record['supervisor_ref']);
        $this->assertStringStartsWith('SUP_', $record['supervisor_ref']);

        // Verify citation was generated
        $this->assertNotEmpty($result['citations']);
        $this->assertStringContainsString('Extracción RAW de datos', $result['citations'][0]['title']);
    }

    public function test_analyze_categories_returns_total_and_percentages(): void
    {
        $cat1 = Category::create(['name' => 'Policy Issues', 'active' => true]);
        $cat2 = Category::create(['name' => 'Communication', 'active' => true]);

        $import = Import::create([
            'original_filename' => 'cat_test.xlsx',
            'file_hash' => 'hash_cat_test',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        // 3 surveys in Policy Issues, 1 survey in Communication
        for ($i = 1; $i <= 3; $i++) {
            $s = Survey::create([
                'survey_id' => "SRV_CAT_P{$i}",
                'nps_score' => -1.0,
                'csat_score' => -1.0,
                'professionalism_score' => 0.0,
                'agent_bms' => "BMS_{$i}",
                'supervisor' => 'Sup Test',
                'survey_date' => '2026-09-03',
                'record_hash' => "hash_cat_p{$i}",
                'import_id' => $import->id,
            ]);

            VerbatimAnalysis::create([
                'survey_id' => $s->survey_id,
                'category_id' => $cat1->id,
                'confidence' => 0.95,
                'status' => 'completed',
            ]);
        }

        $s4 = Survey::create([
            'survey_id' => 'SRV_CAT_C1',
            'nps_score' => 1.0,
            'csat_score' => 1.0,
            'professionalism_score' => 1.0,
            'agent_bms' => 'BMS_4',
            'supervisor' => 'Sup Test',
            'survey_date' => '2026-09-03',
            'record_hash' => 'hash_cat_c1',
            'import_id' => $import->id,
        ]);

        VerbatimAnalysis::create([
            'survey_id' => $s4->survey_id,
            'category_id' => $cat2->id,
            'confidence' => 0.90,
            'status' => 'completed',
        ]);

        $pseudonymService = new PseudonymService;
        $minimizer = new DataMinimizerService($pseudonymService);
        $registry = new MetricRegistry;
        $validator = new QueryDslValidator($registry);
        $planner = new QueryPlanner($registry);
        $queryEngine = new QueryEngine($validator, $planner);
        $forecastEngine = new ForecastEngine($registry);

        $toolRegistry = new ToolRegistry(
            $queryEngine,
            $registry,
            $pseudonymService,
            $minimizer,
            $forecastEngine
        );

        $result = $toolRegistry->executeTool(
            toolName: 'analyze_categories',
            arguments: [],
            scopeId: (string) $this->conversation->id
        );

        $this->assertArrayHasKey('total_surveys_analyzed', $result);
        $this->assertEquals(4, $result['total_surveys_analyzed']);
        $this->assertArrayHasKey('category_distribution', $result);
        $this->assertNotEmpty($result['category_distribution']);

        $dist = collect($result['category_distribution'])->keyBy('category');
        $this->assertTrue($dist->has('Policy Issues'));
        $this->assertTrue($dist->has('Communication'));

        // Policy Issues should be 3/4 = 75%
        $this->assertEquals(75.0, $dist['Policy Issues']['percentage']);
        $this->assertEquals('75%', $dist['Policy Issues']['formatted_percentage']);

        // Communication should be 1/4 = 25%
        $this->assertEquals(25.0, $dist['Communication']['percentage']);
        $this->assertEquals('25%', $dist['Communication']['formatted_percentage']);
    }

    public function test_query_data_returns_percentage_of_total_when_grouped(): void
    {
        $import = Import::create([
            'original_filename' => 'grouped_test.xlsx',
            'file_hash' => 'hash_grouped_test',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        for ($i = 1; $i <= 6; $i++) {
            Survey::create([
                'survey_id' => "SRV_GRP_A{$i}",
                'nps_score' => 1.0,
                'csat_score' => 1.0,
                'professionalism_score' => 1.0,
                'agent_bms' => 'BMS_GRP_A',
                'supervisor' => 'Supervisor Alpha',
                'survey_date' => '2026-09-04',
                'record_hash' => "hash_grp_a{$i}",
                'import_id' => $import->id,
            ]);
        }

        for ($i = 1; $i <= 4; $i++) {
            Survey::create([
                'survey_id' => "SRV_GRP_B{$i}",
                'nps_score' => -1.0,
                'csat_score' => -1.0,
                'professionalism_score' => 0.0,
                'agent_bms' => 'BMS_GRP_B',
                'supervisor' => 'Supervisor Beta',
                'survey_date' => '2026-09-04',
                'record_hash' => "hash_grp_b{$i}",
                'import_id' => $import->id,
            ]);
        }

        $pseudonymService = new PseudonymService;
        $minimizer = new DataMinimizerService($pseudonymService);
        $registry = new MetricRegistry;
        $validator = new QueryDslValidator($registry);
        $planner = new QueryPlanner($registry);
        $queryEngine = new QueryEngine($validator, $planner);
        $forecastEngine = new ForecastEngine($registry);

        $toolRegistry = new ToolRegistry(
            $queryEngine,
            $registry,
            $pseudonymService,
            $minimizer,
            $forecastEngine
        );

        $result = $toolRegistry->executeTool(
            toolName: 'query_data',
            arguments: [
                'metric' => 'survey_volume',
                'group_by' => ['supervisor'],
            ],
            scopeId: (string) $this->conversation->id
        );

        $this->assertArrayHasKey('results', $result);
        $this->assertCount(2, $result['results']);

        $rows = $result['results'];
        $first = $rows[0];
        $this->assertArrayHasKey('percentage_of_total', $first);
        $this->assertArrayHasKey('formatted_percentage', $first);

        // Alpha is 6/10 = 60%, Beta is 4/10 = 40%
        $this->assertEquals(60.0, $first['percentage_of_total']);
        $this->assertEquals('60%', $first['formatted_percentage']);

        $second = $rows[1];
        $this->assertEquals(40.0, $second['percentage_of_total']);
        $this->assertEquals('40%', $second['formatted_percentage']);
    }
}
