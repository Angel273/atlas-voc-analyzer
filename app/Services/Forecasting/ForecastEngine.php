<?php

namespace App\Services\Forecasting;

use App\Models\Forecast;
use App\Models\ForecastResult;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Metrics\Registry\MetricRegistry;
use DateTime;
use Illuminate\Support\Facades\DB;

class ForecastEngine
{
    public function __construct(
        protected ?MetricRegistry $metricRegistry = null,
        protected ?ForecastEligibilityService $eligibilityService = null,
        protected ?WalkForwardValidator $validator = null,
        protected ?ForecastReliabilityEvaluator $reliabilityEvaluator = null,
        protected ?AuditService $auditService = null
    ) {
        $this->metricRegistry = $metricRegistry ?? app(MetricRegistry::class);
        $this->eligibilityService = $eligibilityService ?? app(ForecastEligibilityService::class);
        $this->validator = $validator ?? app(WalkForwardValidator::class);
        $this->reliabilityEvaluator = $reliabilityEvaluator ?? app(ForecastReliabilityEvaluator::class);
        $this->auditService = $auditService ?? app(AuditService::class);
    }

    /**
     * Compute a deterministic, explainable, and automatically validated forecast for a VOC metric.
     */
    public function calculateForecast(
        string $metric = 'nps',
        ?string $modelType = null,
        int $horizon = 7,
        ?string $dimension = null,
        ?string $dimensionValue = null,
        ?User $user = null
    ): Forecast {
        $metricDef = $this->metricRegistry->get($metric);
        $sourceCol = $metricDef && $metricDef->sourceColumn ? $metricDef->sourceColumn : 'nps_score';

        // Boundary constraints per metric
        [$minBound, $maxBound] = match ($metric) {
            'nps' => [-1.0, 1.0],
            'csat', 'professionalism' => [0.0, 1.0],
            default => [0.0, 100.0],
        };

        // Align metric aggregation (CSAT/Professionalism Top-Box positive ratio vs NPS continuous average)
        if (in_array($metric, ['csat', 'professionalism'], true)) {
            $metricExpr = "AVG(CASE WHEN {$sourceCol} = 1 THEN 1.0 WHEN {$sourceCol} > 0 THEN {$sourceCol} ELSE 0.0 END)";
        } else {
            $metricExpr = "AVG({$sourceCol})";
        }

        // Fetch historical daily aggregated time series with sample count
        $query = DB::table('surveys')
            ->selectRaw("DATE(survey_date) as survey_date, {$metricExpr} as metric_value, COUNT(*) as sample_count")
            ->groupBy(DB::raw('DATE(survey_date)'))
            ->orderBy('survey_date', 'asc');

        if ($dimension && $dimensionValue) {
            $col = match ($dimension) {
                'supervisor' => 'supervisor',
                'agent', 'agent_bms' => 'agent_bms',
                'wave' => 'wave',
                default => $dimension,
            };
            $query->where($col, $dimensionValue);
        }

        $rawSeries = $query->get()->map(fn ($r) => [
            'date' => substr((string) $r->survey_date, 0, 10),
            'value' => (float) $r->metric_value,
            'sample_count' => (int) $r->sample_count,
        ])->toArray();

        // 1. Data Quality Gate Evaluation
        $eligibility = $this->eligibilityService->evaluate($rawSeries, $horizon);

        $trainingStart = ! empty($rawSeries) ? $rawSeries[0]['date'] : now()->format('Y-m-d');
        $trainingEnd = ! empty($rawSeries) ? end($rawSeries)['date'] : now()->format('Y-m-d');

        // Case A: Insufficient History or Quality Gate Failure
        if (! $eligibility['is_eligible']) {
            $forecast = Forecast::create([
                'metric' => $metric,
                'dimension' => $dimension,
                'dimension_value' => $dimensionValue,
                'model' => 'none',
                'status' => strtolower($eligibility['status']), // e.g. insufficient_history
                'reliability' => 'INSUFFICIENT',
                'selection_reason' => $eligibility['reason'],
                'parameters' => [
                    'eligibility' => $eligibility,
                    'historical_points' => $eligibility['annotated_observations'],
                    'candidate_comparison' => [],
                    'bounds' => ['min' => $minBound, 'max' => $maxBound],
                ],
                'training_period_start' => $trainingStart,
                'training_period_end' => $trainingEnd,
                'forecast_horizon' => $horizon,
                'mae' => null,
                'rmse' => null,
                'r2' => null,
                'generated_by' => $user?->id,
            ]);

            // Audit record
            $this->auditService->record(
                eventType: 'forecast.insufficient_history',
                payload: [
                    'forecast_id' => $forecast->id,
                    'metric' => $metric,
                    'status' => $eligibility['status'],
                    'reason' => $eligibility['reason'],
                    'total_periods' => $eligibility['total_periods'],
                    'eligible_periods' => $eligibility['eligible_periods'],
                ],
                auditableType: Forecast::class,
                auditableId: (string) $forecast->id,
                userId: $user?->id
            );

            return $forecast->load('results');
        }

        // Case B: Data is eligible -> Run Walk-Forward Validation across candidates
        $trainingValues = $eligibility['training_series'];
        $validationResult = $this->validator->evaluateAndSelect($trainingValues);

        $selectedModel = $validationResult['selected_model'];
        $selectedCode = $validationResult['selected_code'];
        $selectedName = $validationResult['selected_name'];
        $selectionReason = $validationResult['selection_reason'];
        $candidateComparison = $validationResult['candidate_comparison'];

        // 2. Generate Future Projections
        $rawProjections = $selectedModel->predict($horizon);
        $confIntervals = $selectedModel->supportsConfidenceInterval()
            ? $selectedModel->confidenceIntervals($horizon, 0.95)
            : null;

        // 3. Evaluate Reliability deterministically
        $avgSurveys = $eligibility['eligible_periods'] > 0
            ? array_sum(array_column(array_filter($eligibility['annotated_observations'], fn ($p) => ! $p['is_excluded']), 'sample_count')) / $eligibility['eligible_periods']
            : 0;

        $reliability = $this->reliabilityEvaluator->evaluate(
            eligiblePeriods: $eligibility['eligible_periods'],
            avgSurveyVolume: $avgSurveys,
            validationMae: $validationResult['selected_mae'],
            horizon: $horizon,
            excludedPeriods: $eligibility['excluded_periods_count']
        );

        // Model diagnostics
        $modelDiagnostics = $selectedModel->diagnostics();
        $r2 = $modelDiagnostics['r2'] ?? null;

        $parameters = [
            'selected_model' => [
                'code' => $selectedCode,
                'name' => $selectedName,
                'parameters' => $selectedModel->parameters(),
                'diagnostics' => $modelDiagnostics,
                'description' => $selectedModel->description(),
            ],
            'candidate_comparison' => $candidateComparison,
            'selection_reason' => $selectionReason,
            'reliability_evaluation' => $reliability,
            'eligibility' => $eligibility,
            'historical_points' => $eligibility['annotated_observations'],
            'bounds' => ['min' => $minBound, 'max' => $maxBound],
        ];

        // 4. Create Forecast Record
        $forecast = Forecast::create([
            'metric' => $metric,
            'dimension' => $dimension,
            'dimension_value' => $dimensionValue,
            'model' => $selectedCode,
            'status' => 'completed',
            'reliability' => $reliability['rating'],
            'selection_reason' => $selectionReason,
            'parameters' => $parameters,
            'training_period_start' => $trainingStart,
            'training_period_end' => $trainingEnd,
            'forecast_horizon' => $horizon,
            'mae' => $validationResult['selected_mae'],
            'rmse' => $validationResult['selected_rmse'],
            'r2' => $r2 !== null ? round((float) $r2, 4) : null,
            'generated_by' => $user?->id,
        ]);

        // 5. Save Forecast Results with strict raw vs display bounding
        $lastDate = new DateTime($trainingEnd);
        foreach ($rawProjections as $idx => $rawProjVal) {
            $projDate = (clone $lastDate)->modify('+'.($idx + 1).' day')->format('Y-m-d');
            $clampedForecast = max($minBound, min($maxBound, (float) $rawProjVal));
            $wasBounded = (abs($rawProjVal - $clampedForecast) > 1e-5);

            $lowVal = null;
            $highVal = null;
            if ($confIntervals && isset($confIntervals[$idx])) {
                $rawLow = $confIntervals[$idx]['low'];
                $rawHigh = $confIntervals[$idx]['high'];
                $lowVal = round(max($minBound, min($maxBound, $rawLow)), 4);
                $highVal = round(max($minBound, min($maxBound, $rawHigh)), 4);
            }

            ForecastResult::create([
                'forecast_id' => $forecast->id,
                'date' => $projDate,
                'actual_value' => null,
                'raw_forecast_value' => round((float) $rawProjVal, 4),
                'forecast_value' => round($clampedForecast, 4),
                'confidence_low' => $lowVal,
                'confidence_high' => $highVal,
                'was_bounded' => $wasBounded,
            ]);
        }

        // 6. Record Audit Event
        $this->auditService->record(
            eventType: 'forecast.executed',
            payload: [
                'forecast_id' => $forecast->id,
                'metric' => $metric,
                'selected_model' => $selectedCode,
                'selected_name' => $selectedName,
                'mae' => $forecast->mae,
                'rmse' => $forecast->rmse,
                'reliability' => $forecast->reliability,
                'eligible_periods' => $eligibility['eligible_periods'],
                'excluded_periods' => $eligibility['excluded_periods_count'],
                'selection_reason' => $selectionReason,
            ],
            auditableType: Forecast::class,
            auditableId: (string) $forecast->id,
            userId: $user?->id
        );

        return $forecast->load('results');
    }
}
