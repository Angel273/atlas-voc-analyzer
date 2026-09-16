<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Import;
use App\Models\Permission;
use App\Models\Survey;
use App\Models\User;
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
}
