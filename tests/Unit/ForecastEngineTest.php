<?php

namespace Tests\Unit;

use App\Models\Forecast;
use App\Models\Import;
use App\Models\Survey;
use App\Services\Forecasting\ForecastEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastEngineTest extends TestCase
{
    use RefreshDatabase;

    protected ForecastEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = $this->app->make(ForecastEngine::class);
    }

    public function test_forecast_returns_insufficient_history_when_below_28_periods(): void
    {
        $import = Import::create([
            'original_filename' => 'series.xlsx',
            'file_hash' => 'hash_series',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        // Create only 5 daily periods (below the 28 required by Data Quality Gate)
        for ($i = 1; $i <= 5; $i++) {
            Survey::create([
                'survey_id' => "SRV_{$i}",
                'nps_score' => $i * 0.1,
                'csat_score' => 0.8,
                'professionalism_score' => 0.8,
                'agent_bms' => 'AGT_1',
                'supervisor' => 'SUP_1',
                'survey_date' => sprintf('2026-09-%02d', $i),
                'record_hash' => "h_{$i}",
                'import_id' => $import->id,
            ]);
        }

        $forecast = $this->engine->calculateForecast(
            metric: 'nps',
            horizon: 3
        );

        $this->assertEquals('insufficient_history', $forecast->status);
        $this->assertEquals('INSUFFICIENT', $forecast->reliability);
        $this->assertCount(0, $forecast->results); // Production forecast withheld
        $this->assertNotEmpty($forecast->historical_points); // Historical data retained
        $this->assertStringContainsString('Atlas does not have enough historical observations', $forecast->selection_reason);
    }

    public function test_automatic_model_selection_and_bounding_on_eligible_history(): void
    {
        $import = Import::create([
            'original_filename' => 'eligible_series.xlsx',
            'file_hash' => 'hash_eligible',
            'sheet_name' => 'Sheet1',
            'header_row' => 1,
            'used_mapping' => [],
            'status' => 'completed',
        ]);

        // Create 30 days of data, 25 surveys per day (>= 28 days and >= 20 surveys/day)
        // Trending upward from 0.5 towards 0.95
        for ($day = 1; $day <= 30; $day++) {
            $val = min(0.95, 0.40 + ($day * 0.018));
            for ($s = 1; $s <= 25; $s++) {
                Survey::create([
                    'survey_id' => "SRV_D{$day}_S{$s}",
                    'nps_score' => $val,
                    'csat_score' => $val,
                    'professionalism_score' => $val,
                    'agent_bms' => 'AGT_1',
                    'supervisor' => 'SUP_1',
                    'survey_date' => sprintf('2026-08-%02d', $day),
                    'record_hash' => "h_d{$day}_s{$s}",
                    'import_id' => $import->id,
                ]);
            }
        }

        // Horizon 7 <= 30 / 3 = 10 (Safe)
        $forecast = $this->engine->calculateForecast(
            metric: 'nps',
            horizon: 7
        );

        $this->assertEquals('completed', $forecast->status);
        $this->assertContains($forecast->model, ['linear_trend', 'holt_trend', 'moving_average', 'naive']);
        $this->assertCount(7, $forecast->results);
        $this->assertNotNull($forecast->mae);
        $this->assertNotNull($forecast->rmse);
        $this->assertNotEmpty($forecast->parameters['candidate_comparison']);
        $this->assertCount(4, $forecast->parameters['candidate_comparison']);

        // Check bounding constraints
        foreach ($forecast->results as $r) {
            $this->assertLessThanOrEqual(1.0, $r->forecast_value);
            $this->assertGreaterThanOrEqual(-1.0, $r->forecast_value);
            $this->assertNotNull($r->raw_forecast_value);
        }
    }
}
