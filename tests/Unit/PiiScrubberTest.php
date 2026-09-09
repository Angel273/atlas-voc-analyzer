<?php

namespace Tests\Unit;

use App\Models\Import;
use App\Models\Survey;
use App\Services\Privacy\PiiScrubberService;
use App\Services\Privacy\PseudonymService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiiScrubberTest extends TestCase
{
    use RefreshDatabase;

    protected PiiScrubberService $scrubber;
    protected PseudonymService $pseudonyms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pseudonyms = new PseudonymService();
        $this->scrubber = new PiiScrubberService($this->pseudonyms);
    }

    public function test_scrubs_emails_phone_numbers_and_known_supervisors(): void
    {
        $import = Import::create([
            'original_filename' => 'test.xlsx',
            'file_hash' => 'hash1',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        Survey::create([
            'survey_id' => 'SRV_1',
            'nps_score' => 0.8,
            'csat_score' => 0.9,
            'professionalism_score' => 0.95,
            'agent_bms' => 'BMS_9981',
            'supervisor' => 'Maria Santos',
            'survey_date' => '2026-09-01',
            'record_hash' => 'hash_srv1',
            'import_id' => $import->id,
        ]);

        $text = "Customer john.doe@example.com called 555-123-4567 complaining about Maria Santos and agent BMS_9981.";
        $metadata = [];
        $sanitized = $this->scrubber->scrubText($text, 'scope_test', $metadata);

        $this->assertStringNotContainsString('john.doe@example.com', $sanitized);
        $this->assertStringNotContainsString('555-123-4567', $sanitized);
        $this->assertStringNotContainsString('Maria Santos', $sanitized);
        $this->assertStringNotContainsString('BMS_9981', $sanitized);

        $this->assertStringContainsString('[EMAIL_REDACTED]', $sanitized);
        $this->assertStringContainsString('[PHONE_REDACTED]', $sanitized);
        $this->assertStringContainsString('SUP_', $sanitized);
        $this->assertStringContainsString('AGT_', $sanitized);
    }
}
