<?php

namespace Tests\Feature;

use App\Jobs\CategorizeVerbatimsJob;
use App\Models\Category;
use App\Models\Import;
use App\Models\Survey;
use App\Models\VerbatimAnalysis;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Privacy\PiiScrubberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchCategorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_categorization_job_processes_multiple_surveys_in_single_prompt(): void
    {
        $cat1 = Category::create([
            'name' => 'Customer Service',
            'slug' => 'customer-service',
            'description' => 'Feedback on support agents and service quality',
            'active' => true,
        ]);
        $cat2 = Category::create([
            'name' => 'Technical Support',
            'slug' => 'technical-support',
            'description' => 'Issues with platform bugs or system downtime',
            'active' => true,
        ]);

        $import = Import::create([
            'original_filename' => 'test.xlsx',
            'file_hash' => hash('sha256', 'test'),
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        $surveyIds = [];
        for ($i = 1; $i <= 5; $i++) {
            $sId = "SRV_BATCH_{$i}";
            Survey::create([
                'survey_id' => $sId,
                'nps_score' => 0.8,
                'csat_score' => 0.9,
                'professionalism_score' => 0.95,
                'agent_bms' => 'BMS_001',
                'supervisor' => 'SUP_001',
                'survey_date' => '2026-09-01',
                'record_hash' => hash('sha256', $sId),
                'import_id' => $import->id,
                'verbatim' => "Comentario de prueba {$i} sobre atención y soporte",
            ]);
            $surveyIds[] = $sId;
        }

        // Mock AiProvider to simulate Gemini returning batch JSON array
        $mockAiProvider = new class implements AiProvider
        {
            public int $callCount = 0;

            public function providerName(): string
            {
                return 'gemini_mock';
            }

            public function generate(array $messages, array $tools = [], array $options = []): array
            {
                $this->callCount++;
                $responseItems = [];
                for ($i = 1; $i <= 5; $i++) {
                    $responseItems[] = [
                        'id' => "SRV_BATCH_{$i}",
                        'category' => $i % 2 === 0 ? 'Technical Support' : 'Customer Service',
                        'confidence' => 0.95,
                    ];
                }

                return [
                    'content' => json_encode($responseItems),
                    'model' => 'gemini-3.5-flash-lite',
                ];
            }
        };

        $job = new CategorizeVerbatimsJob($surveyIds);
        $job->handle($mockAiProvider, app(PiiScrubberService::class));

        // Ensure only 1 batch call was made for all 5 surveys
        $this->assertEquals(1, $mockAiProvider->callCount);

        // Verify all 5 surveys were successfully categorized
        $this->assertEquals(5, VerbatimAnalysis::where('status', 'completed')->count());

        $srv1 = VerbatimAnalysis::where('survey_id', 'SRV_BATCH_1')->first();
        $this->assertNotNull($srv1);
        $this->assertEquals($cat1->id, $srv1->category_id);
        $this->assertEquals(0.95, $srv1->confidence);

        $srv2 = VerbatimAnalysis::where('survey_id', 'SRV_BATCH_2')->first();
        $this->assertNotNull($srv2);
        $this->assertEquals($cat2->id, $srv2->category_id);
    }

    public function test_batch_categorization_handles_fallback_when_item_missed_in_batch(): void
    {
        $cat = Category::create([
            'name' => 'Billing & Payments',
            'slug' => 'billing-payments',
            'description' => 'Invoices and payments',
            'active' => true,
        ]);

        $import = Import::create([
            'original_filename' => 'test2.xlsx',
            'file_hash' => hash('sha256', 'test2'),
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_FB_1',
            'nps_score' => 0.5,
            'csat_score' => 0.5,
            'professionalism_score' => 0.5,
            'agent_bms' => 'BMS_FB',
            'supervisor' => 'SUP_FB',
            'survey_date' => '2026-09-01',
            'record_hash' => 'hash_fb_1',
            'import_id' => $import->id,
            'verbatim' => 'Comentario con cobro indebido',
        ]);

        // Mock returns empty batch, triggering fallback single call
        $mockAiProvider = new class implements AiProvider
        {
            public int $callCount = 0;

            public function providerName(): string
            {
                return 'gemini_mock';
            }

            public function generate(array $messages, array $tools = [], array $options = []): array
            {
                $this->callCount++;
                if (($options['prompt_version'] ?? '') === 'cat_v2_fallback') {
                    return [
                        'content' => '{"category": "Billing & Payments", "confidence": 0.92}',
                        'model' => 'gemini-3.5-flash-lite',
                    ];
                }

                // Batch returns empty array, missing the item
                return [
                    'content' => '[]',
                    'model' => 'gemini-3.5-flash-lite',
                ];
            }
        };

        $job = new CategorizeVerbatimsJob(['SRV_FB_1']);
        $job->handle($mockAiProvider, app(PiiScrubberService::class));

        // Should have called batch (1) + fallback (1) = 2 calls
        $this->assertEquals(2, $mockAiProvider->callCount);

        $res = VerbatimAnalysis::where('survey_id', 'SRV_FB_1')->first();
        $this->assertNotNull($res);
        $this->assertEquals('completed', $res->status);
        $this->assertEquals($cat->id, $res->category_id);
    }
}
