<?php

namespace Tests\Unit;

use App\Services\Forecasting\Models\HoltLinearTrendModel;
use App\Services\Forecasting\Models\LinearTrendModel;
use App\Services\Forecasting\Models\MovingAverageModel;
use App\Services\Forecasting\Models\NaiveForecastModel;
use PHPUnit\Framework\TestCase;

class ForecastModelsTest extends TestCase
{
    public function test_naive_forecast_predicts_last_observed_value(): void
    {
        $model = new NaiveForecastModel;
        $model->fit([0.2, 0.4, 0.6, 0.8]);

        $this->assertEquals('naive', $model->code());
        $this->assertEquals(1, $model->complexityOrder());
        $this->assertFalse($model->supportsConfidenceInterval());
        $this->assertEquals('The most recent valid observation is used as the expected future value.', $model->description());

        $preds = $model->predict(5);
        $this->assertCount(5, $preds);
        foreach ($preds as $p) {
            $this->assertEquals(0.8, $p);
        }

        $this->assertEquals(0.8, $model->parameters()['last_observed_value']);
    }

    public function test_moving_average_evaluates_candidate_windows_and_averages(): void
    {
        $series = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7];
        $model = new MovingAverageModel(null, [3, 5]);
        $model->fit($series);

        $this->assertEquals('moving_average', $model->code());
        $this->assertEquals(2, $model->complexityOrder());
        $this->assertFalse($model->supportsConfidenceInterval());
        $this->assertArrayHasKey('window_k', $model->parameters());
        $this->assertContains($model->parameters()['window_k'], [3, 5]);

        $preds = $model->predict(4);
        $this->assertCount(4, $preds);
        // All forecast periods should equal the last moving average
        $this->assertEquals($model->parameters()['last_moving_average'], $preds[0]);
    }

    public function test_holt_linear_trend_estimates_level_and_trend(): void
    {
        // Upward trending series: 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8
        $series = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8];
        $model = new HoltLinearTrendModel;
        $model->fit($series);

        $this->assertEquals('holt_trend', $model->code());
        $this->assertEquals(4, $model->complexityOrder());
        $this->assertTrue($model->supportsConfidenceInterval());

        $params = $model->parameters();
        $this->assertArrayHasKey('alpha', $params);
        $this->assertArrayHasKey('beta', $params);
        $this->assertArrayHasKey('final_level', $params);
        $this->assertArrayHasKey('final_trend', $params);
        $this->assertGreaterThan(0.0, $params['final_trend']);

        // Projections should trend upwards
        $preds = $model->predict(3);
        $this->assertCount(3, $preds);
        $this->assertGreaterThan($params['final_level'], $preds[0]);
        $this->assertGreaterThan($preds[0], $preds[1]);
        $this->assertGreaterThan($preds[1], $preds[2]);

        // Confidence intervals should expand
        $intervals = $model->confidenceIntervals(3);
        $this->assertNotNull($intervals);
        $this->assertCount(3, $intervals);
        $width1 = $intervals[0]['high'] - $intervals[0]['low'];
        $width3 = $intervals[2]['high'] - $intervals[2]['low'];
        $this->assertGreaterThanOrEqual($width1, $width3);
    }

    public function test_linear_trend_computes_ols_slope_intercept_and_r2(): void
    {
        // Perfectly linear series: y = 0.2 + 0.1 * x
        $series = [0.2, 0.3, 0.4, 0.5, 0.6];
        $model = new LinearTrendModel;
        $model->fit($series);

        $this->assertEquals('linear_trend', $model->code());
        $this->assertEquals(3, $model->complexityOrder());
        $this->assertTrue($model->supportsConfidenceInterval());

        $params = $model->parameters();
        $this->assertEqualsWithDelta(0.1, $params['slope'], 0.001);
        $this->assertEqualsWithDelta(0.2, $params['intercept'], 0.001);
        $this->assertEqualsWithDelta(1.0, $params['r2'], 0.001);

        $preds = $model->predict(2);
        // x = 5 -> y = 0.2 + 0.5 = 0.7; x = 6 -> y = 0.8
        $this->assertEqualsWithDelta(0.7, $preds[0], 0.01);
        $this->assertEqualsWithDelta(0.8, $preds[1], 0.01);
    }
}
