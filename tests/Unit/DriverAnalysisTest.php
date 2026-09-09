<?php

namespace Tests\Unit;

use App\Services\DriverAnalysis\LogisticDriverEngine;
use App\Services\DriverAnalysis\NpsDriverEngine;
use PHPUnit\Framework\TestCase;

class DriverAnalysisTest extends TestCase
{
    public function test_nps_driver_engine_estimates_effects_and_flags_sample_size(): void
    {
        $engine = new NpsDriverEngine;

        // Synthetic surveys:
        // Customer Service (reference, high NPS)
        // Billing & Payments (lower NPS, 50 surveys -> supported)
        // Technical Support (very low sample: 5 surveys -> insufficient)
        $surveys = [];

        for ($i = 0; $i < 60; $i++) {
            $surveys[] = [
                'nps_score' => 0.8,
                'category' => 'Customer Service',
                'tenure_days' => 120,
                'wave' => 'Wave 1',
                'supervisor' => 'Sup A',
                'survey_date' => '2026-09-01',
            ];
        }

        for ($i = 0; $i < 40; $i++) {
            $surveys[] = [
                'nps_score' => -0.4,
                'category' => 'Billing & Payments',
                'tenure_days' => 60,
                'wave' => 'Wave 2',
                'supervisor' => 'Sup B',
                'survey_date' => '2026-09-02',
            ];
        }

        for ($i = 0; $i < 6; $i++) {
            $surveys[] = [
                'nps_score' => -0.8,
                'category' => 'Technical Support',
                'tenure_days' => 20,
                'wave' => 'Wave 2',
                'supervisor' => 'Sup B',
                'survey_date' => '2026-09-03',
            ];
        }

        $result = $engine->analyze($surveys);

        $this->assertEquals('COMPLETED', $result['status']);
        $this->assertEquals('nps', $result['target_metric']);
        $this->assertEquals(106, $result['sample_size']);
        $this->assertNotEmpty($result['drivers']);

        // Find Billing & Payments driver
        $billing = null;
        $tech = null;
        foreach ($result['drivers'] as $d) {
            if (str_contains($d['driver'], 'Billing & Payments')) {
                $billing = $d;
            }
            if (str_contains($d['driver'], 'Technical Support')) {
                $tech = $d;
            }
        }

        $this->assertNotNull($billing);
        $this->assertLessThan(0.0, $billing['estimated_effect']);
        $this->assertEquals('Negative', $billing['direction']);
        $this->assertEquals(40, $billing['sample_size']);
        $this->assertContains($billing['confidence'], ['SUPPORTED', 'MODERATE']);

        // Technical Support has n = 6 -> MUST be INSUFFICIENT
        $this->assertNotNull($tech);
        $this->assertEquals(6, $tech['sample_size']);
        $this->assertEquals('INSUFFICIENT', $tech['confidence']);

        // Must not claim causality in explanation
        foreach ($result['drivers'] as $d) {
            $this->assertStringNotContainsStringIgnoringCase('causa', $d['explanation']);
            $this->assertStringNotContainsStringIgnoringCase('causes', $d['explanation']);
        }
    }

    public function test_logistic_driver_engine_calculates_odds_ratio_and_marginal_effects(): void
    {
        $engine = new LogisticDriverEngine;

        $surveys = [];
        // Positive CSAT: Customer Service
        for ($i = 0; $i < 50; $i++) {
            $surveys[] = [
                'target_value' => 1.0,
                'category' => 'Customer Service',
                'tenure_days' => 100,
                'wave' => 'Wave 1',
                'supervisor' => 'Sup Alfa',
                'survey_date' => '2026-09-01',
            ];
        }

        // Lower CSAT: Billing
        for ($i = 0; $i < 50; $i++) {
            $surveys[] = [
                'target_value' => ($i % 3 === 0) ? 1.0 : 0.0,
                'category' => 'Billing & Payments',
                'tenure_days' => 45,
                'wave' => 'Wave 1',
                'supervisor' => 'Sup Alfa',
                'survey_date' => '2026-09-02',
            ];
        }

        $result = $engine->analyze($surveys, 'csat');

        $this->assertEquals('COMPLETED', $result['status']);
        $this->assertEquals('csat', $result['target_metric']);
        $this->assertNotEmpty($result['drivers']);

        $billing = null;
        foreach ($result['drivers'] as $d) {
            if (str_contains($d['driver'], 'Billing & Payments')) {
                $billing = $d;
            }
        }

        $this->assertNotNull($billing);
        $this->assertArrayHasKey('odds_ratio', $billing);
        $this->assertArrayHasKey('percentage_points', $billing);
        $this->assertLessThan(0, $billing['percentage_points']); // Negative impact
        $this->assertStringContainsString('pp', (string) $billing['estimated_effect']);
    }

    public function test_driver_engine_respects_custom_reference_categories(): void
    {
        $engine = new NpsDriverEngine;

        $surveys = [];
        // Wave 1 has 70 surveys (default largest)
        for ($i = 0; $i < 70; $i++) {
            $surveys[] = [
                'nps_score' => 0.5,
                'category' => 'Customer Service',
                'tenure_days' => 100,
                'wave' => 'Wave 1',
                'supervisor' => 'Sup Alfa',
                'survey_date' => '2026-09-01',
            ];
        }

        // Wave 2 has 30 surveys
        for ($i = 0; $i < 30; $i++) {
            $surveys[] = [
                'nps_score' => -0.2,
                'category' => 'Billing & Payments',
                'tenure_days' => 40,
                'wave' => 'Wave 2',
                'supervisor' => 'Sup Beta',
                'survey_date' => '2026-09-02',
            ];
        }

        // 1. Default run -> Wave 1 is reference
        $defaultResult = $engine->analyze($surveys);
        $this->assertEquals('Wave 1', $defaultResult['reference_categories']['wave']);
        $this->assertArrayHasKey('available_references', $defaultResult);
        $this->assertCount(2, $defaultResult['available_references']['waves']);

        // 2. Custom run: Request Wave 2 as reference
        $customResult = $engine->analyze($surveys, [
            'wave' => 'Wave 2',
            'category' => 'Billing & Payments',
        ]);

        $this->assertEquals('Wave 2', $customResult['reference_categories']['wave']);
        $this->assertEquals('Billing & Payments', $customResult['reference_categories']['category']);

        // Wave 1 must now be in the driver list evaluated against Wave 2
        $wave1Driver = null;
        foreach ($customResult['drivers'] as $d) {
            if ($d['driver'] === 'Ola: Wave 1') {
                $wave1Driver = $d;
                break;
            }
        }
        $this->assertNotNull($wave1Driver);
        $this->assertEquals('Wave 2', $wave1Driver['reference_category']);
        $this->assertStringContainsString('respecto a Wave 2', $wave1Driver['explanation']);
    }
}
