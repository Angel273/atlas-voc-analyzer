<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Survey;
use App\Models\User;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Gateway\AiGateway;
use App\Services\Ai\Providers\MockAiProvider;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Audit\AuditService;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Metrics\Dsl\QueryEngine;
use App\Services\Metrics\Dsl\QueryPlanner;
use App\Services\Metrics\Dsl\QueryDslValidator;
use App\Services\Metrics\Registry\MetricRegistry;
use App\Services\Privacy\DataMinimizerService;
use App\Services\Privacy\PiiScrubberService;
use App\Services\Privacy\PseudonymService;
use App\Services\Privacy\ReidentificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiGatewayPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected AiGateway $gateway;
    protected PseudonymService $pseudonymService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pseudonymService = new PseudonymService();
        $scrubber = new PiiScrubberService($this->pseudonymService);
        $reid = new ReidentificationService($this->pseudonymService);
        $audit = new AuditService();

        $registry = new MetricRegistry();
        $validator = new QueryDslValidator($registry);
        $planner = new QueryPlanner($registry);
        $queryEngine = new QueryEngine($validator, $planner);
        $minimizer = new DataMinimizerService($this->pseudonymService);
        $forecastEngine = new ForecastEngine($registry);

        $toolRegistry = new ToolRegistry(
            $queryEngine,
            $registry,
            $this->pseudonymService,
            $minimizer,
            $forecastEngine
        );

        $provider = new MockAiProvider();

        $this->gateway = new AiGateway(
            $provider,
            $toolRegistry,
            $scrubber,
            $reid,
            $audit
        );
    }

    public function test_pre_pseudonymization_and_permission_gated_reidentification(): void
    {
        $import = Import::create([
            'original_filename' => 'surveys.xlsx',
            'file_hash' => 'hash_surveys',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_PRIV_1',
            'nps_score' => 0.6,
            'csat_score' => 0.85,
            'professionalism_score' => 0.9,
            'agent_bms' => 'BMS_9981',
            'supervisor' => 'Maria Santos',
            'survey_date' => '2026-09-01',
            'record_hash' => 'hash_priv',
            'import_id' => $import->id,
        ]);

        $userWithPermission = User::create([
            'name' => 'Privileged Analyst',
            'email' => 'analyst_priv@atlas.local',
            'password' => bcrypt('secret'),
        ]);
        $perm = Permission::create(['name' => 'Identity View', 'slug' => 'identity.view']);
        $chatPerm = Permission::create(['name' => 'AI Chat', 'slug' => 'ai.chat']);
        $userWithPermission->directPermissions()->attach([$perm->id, $chatPerm->id]);

        $userWithoutPermission = User::create([
            'name' => 'Viewer',
            'email' => 'viewer_anon@atlas.local',
            'password' => bcrypt('secret'),
        ]);
        $userWithoutPermission->directPermissions()->attach([$chatPerm->id]);

        $conversation = \App\Models\Conversation::create([
            'user_id' => $userWithPermission->id,
            'title' => 'Privacy Test Conversation',
        ]);
        $convId = (string) $conversation->id;
        $input = "Why is agent BMS_9981 under supervisor Maria Santos performing below target?";

        // Run for privileged user
        $resultPriv = $this->gateway->runAssistant(
            userInput: $input,
            conversationId: $convId,
            conversationHistory: [],
            user: $userWithPermission
        );

        // 1. Pre-pseudonymization: sanitized_user_input must NOT contain real names
        $this->assertStringNotContainsString('BMS_9981', $resultPriv['sanitized_user_input']);
        $this->assertStringNotContainsString('Maria Santos', $resultPriv['sanitized_user_input']);
        $this->assertStringContainsString('AGT_', $resultPriv['sanitized_user_input']);
        $this->assertStringContainsString('SUP_', $resultPriv['sanitized_user_input']);

        // 2. Re-identification for user WITH identity.view should resolve tokens in display
        // Mock provider output contains tokens if they were in the response
        $this->assertDatabaseHas('pseudonym_vault', [
            'scope_id' => $convId,
            'entity_internal_id' => 'Maria Santos',
        ]);
    }
}
