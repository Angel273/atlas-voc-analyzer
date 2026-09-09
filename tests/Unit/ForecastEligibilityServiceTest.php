<?php

namespace Tests\Unit;

use App\Services\Forecasting\ForecastEligibilityService;
use PHPUnit\Framework\TestCase;

class ForecastEligibilityServiceTest extends TestCase
{
    protected ForecastEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // 28 min history periods, 20 min surveys per period, 1/3 max horizon ratio
        $this->service = new ForecastEligibilityService(28, 20, 1 / 3);
    }

    public function test_rejects_insufficient_history_below_28_periods(): void
    {
        // 10 periods with 50 surveys each
        $series = [];
        for ($i = 1; $i <= 10; $i++) {
            $series[] = [
                'date' => sprintf('2026-09-%02d', $i),
                'value' => 0.5,
                'sample_count' => 50,
            ];
        }

        $result = $this->service->evaluate($series, 7);

        $this->assertFalse($result['is_eligible']);
        $this->assertEquals('INSUFFICIENT_HISTORY', $result['status']);
        $this->assertStringContainsString('Atlas does not have enough historical observations', $result['reason']);
        $this->assertEquals(10, $result['eligible_periods']);
    }

    public function test_identifies_and_excludes_low_sample_periods_below_20_surveys(): void
    {
        // 30 periods total, but 5 have only 5 surveys (low sample)
        $series = [];
        for ($i = 1; $i <= 30; $i++) {
            $count = ($i <= 5) ? 5 : 30;
            $series[] = [
                'date' => sprintf('2026-08-%02d', $i),
                'value' => 0.6,
                'sample_count' => $count,
            ];
        }

        $result = $this->service->evaluate($series, 7);

        // 30 - 5 = 25 eligible periods < 28 required -> Insufficient history
        $this->assertFalse($result['is_eligible']);
        $this->assertEquals('INSUFFICIENT_HISTORY', $result['status']);
        $this->assertEquals(5, $result['excluded_periods_count']);
        $this->assertEquals(25, $result['eligible_periods']);

        // Check low sample annotations
        $this->assertTrue($result['annotated_observations'][0]['is_low_sample']);
        $this->assertEquals('LOW_SAMPLE', $result['annotated_observations'][0]['sample_status']);
        $this->assertFalse($result['annotated_observations'][6]['is_low_sample']);
    }

    public function test_accepts_eligible_series_above_thresholds(): void
    {
        // 30 periods with 25 surveys each
        $series = [];
        for ($i = 1; $i <= 30; $i++) {
            $series[] = [
                'date' => sprintf('2026-08-%02d', $i),
                'value' => 0.4 + ($i * 0.005),
                'sample_count' => 25,
            ];
        }

        $result = $this->service->evaluate($series, 7);

        $this->assertTrue($result['is_eligible']);
        $this->assertEquals('ELIGIBLE', $result['status']);
        $this->assertEquals(30, $result['eligible_periods']);
        $this->assertEquals(0, $result['excluded_periods_count']);
        $this->assertCount(30, $result['training_series']);
    }

    public function test_enforces_forecast_horizon_safety_ratio(): void
    {
        // 30 periods -> max safe horizon = floor(30 / 3) = 10
        $series = [];
        for ($i = 1; $i <= 30; $i++) {
            $series[] = [
                'date' => sprintf('2026-08-%02d', $i),
                'value' => 0.5,
                'sample_count' => 30,
            ];
        }

        // Requesting 14 days should fail
        $result = $this->service->evaluate($series, 14);

        $this->assertFalse($result['is_eligible']);
        $this->assertEquals('EXCESSIVE_HORIZON', $result['status']);
        $this->assertStringContainsString('exceeds maximum safe projection of 10 days', $result['reason']);
    }
}
