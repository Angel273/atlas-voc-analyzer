<?php

namespace Tests\Unit;

use App\Services\Forecasting\WalkForwardValidator;
use Tests\TestCase;

class WalkForwardValidatorTest extends TestCase
{
    protected WalkForwardValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new WalkForwardValidator;
    }

    public function test_walk_forward_selects_linear_trend_on_purely_linear_data(): void
    {
        // Pure linear series with small slope: 0.1, 0.12, 0.14, ... 20 points
        $series = [];
        for ($i = 0; $i < 20; $i++) {
            $series[] = 0.1 + ($i * 0.02);
        }

        $result = $this->validator->evaluateAndSelect($series);

        $this->assertArrayHasKey('selected_model', $result);
        $this->assertArrayHasKey('selected_code', $result);
        $this->assertArrayHasKey('selected_mae', $result);
        $this->assertArrayHasKey('selected_rmse', $result);
        $this->assertArrayHasKey('candidate_comparison', $result);

        // Linear trend should be selected or Holt with low error
        $this->assertContains($result['selected_code'], ['linear_trend', 'holt_trend']);
        $this->assertCount(4, $result['candidate_comparison']);

        // Check candidate comparison structure
        $first = $result['candidate_comparison'][0];
        $this->assertArrayHasKey('mae', $first);
        $this->assertArrayHasKey('rmse', $first);
        $this->assertArrayHasKey('validation_predictions', $first);
        $this->assertTrue($first['is_selected']);
    }

    public function test_walk_forward_selects_naive_on_flat_series(): void
    {
        // Flat constant series: 0.75 for all points
        $series = array_fill(0, 20, 0.75);

        $result = $this->validator->evaluateAndSelect($series);

        // On completely flat series, all models have MAE = 0.0, so tie-breaker picks Naive (order = 1)
        $this->assertEquals('naive', $result['selected_code']);
        $this->assertEquals(0.0, $result['selected_mae']);
    }
}
