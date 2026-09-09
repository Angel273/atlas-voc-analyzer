<?php

namespace App\Services\Forecasting;

class ForecastEligibilityService
{
    protected int $minHistoryPeriods;

    protected int $minSurveysPerPeriod;

    protected float $maxHorizonRatio;

    public function __construct(
        ?int $minHistoryPeriods = null,
        ?int $minSurveysPerPeriod = null,
        ?float $maxHorizonRatio = null
    ) {
        $hasConfig = function_exists('config') && function_exists('app') && app()->has('config');
        $this->minHistoryPeriods = $minHistoryPeriods ?? ($hasConfig ? (int) config('forecasting.min_history_periods', 28) : 28);
        $this->minSurveysPerPeriod = $minSurveysPerPeriod ?? ($hasConfig ? (int) config('forecasting.min_surveys_per_period', 20) : 20);
        $this->maxHorizonRatio = $maxHorizonRatio ?? ($hasConfig ? (float) config('forecasting.max_horizon_ratio', 1 / 3) : 1 / 3);
    }

    /**
     * Evaluate historical series against data quality and horizon safety gates.
     *
     * @param  array<int, array{date: string, value: float, sample_count: int}>  $rawSeries
     * @return array{
     *     is_eligible: bool,
     *     status: string,
     *     reason: string,
     *     total_periods: int,
     *     eligible_periods: int,
     *     excluded_periods_count: int,
     *     max_allowed_horizon: int,
     *     annotated_observations: array,
     *     training_series: float[],
     *     training_dates: string[]
     * }
     */
    public function evaluate(array $rawSeries, int $requestedHorizon): array
    {
        $annotated = [];
        $trainingValues = [];
        $trainingDates = [];
        $excludedCount = 0;

        foreach ($rawSeries as $point) {
            $date = $point['date'];
            $val = (float) $point['value'];
            $count = (int) $point['sample_count'];

            $isLowSample = ($count < $this->minSurveysPerPeriod);
            $isExcluded = false;
            $exclusionReason = null;

            if ($isLowSample) {
                $isExcluded = true;
                $exclusionReason = "LOW_SAMPLE (Encuestas: {$count} < {$this->minSurveysPerPeriod} requeridas)";
                $excludedCount++;
            } else {
                $trainingValues[] = $val;
                $trainingDates[] = $date;
            }

            $annotated[] = [
                'date' => $date,
                'value' => round($val, 4),
                'sample_count' => $count,
                'is_low_sample' => $isLowSample,
                'sample_status' => $isLowSample ? 'LOW_SAMPLE' : 'NORMAL',
                'is_excluded' => $isExcluded,
                'exclusion_reason' => $exclusionReason,
            ];
        }

        $totalPeriods = count($rawSeries);
        $eligiblePeriods = count($trainingValues);
        $maxAllowedHorizon = max(1, (int) floor($eligiblePeriods * $this->maxHorizonRatio));

        // Gate 1: Check minimum historical periods
        if ($eligiblePeriods < $this->minHistoryPeriods) {
            return [
                'is_eligible' => false,
                'status' => 'INSUFFICIENT_HISTORY',
                'reason' => "Atlas does not have enough historical observations to produce a reliable forecast. {$eligiblePeriods} valid historical periods available; {$this->minHistoryPeriods} required.",
                'total_periods' => $totalPeriods,
                'eligible_periods' => $eligiblePeriods,
                'excluded_periods_count' => $excludedCount,
                'max_allowed_horizon' => $maxAllowedHorizon,
                'annotated_observations' => $annotated,
                'training_series' => $trainingValues,
                'training_dates' => $trainingDates,
            ];
        }

        // Gate 2: Check horizon safety limit
        if ($requestedHorizon > $maxAllowedHorizon) {
            return [
                'is_eligible' => false,
                'status' => 'EXCESSIVE_HORIZON',
                'reason' => "Requested forecast horizon ({$requestedHorizon} days) exceeds maximum safe projection of {$maxAllowedHorizon} days based on available valid history ({$eligiblePeriods} periods / 3).",
                'total_periods' => $totalPeriods,
                'eligible_periods' => $eligiblePeriods,
                'excluded_periods_count' => $excludedCount,
                'max_allowed_horizon' => $maxAllowedHorizon,
                'annotated_observations' => $annotated,
                'training_series' => $trainingValues,
                'training_dates' => $trainingDates,
            ];
        }

        // Gate 3: Check metric variance
        $variance = $this->calculateVariance($trainingValues);
        if ($variance <= 1e-7 && $eligiblePeriods > 1) {
            // Constant metric series (0 variance)
            return [
                'is_eligible' => true,
                'status' => 'LOW_VARIANCE',
                'reason' => 'Historical series has zero variance. A flat projection will be applied.',
                'total_periods' => $totalPeriods,
                'eligible_periods' => $eligiblePeriods,
                'excluded_periods_count' => $excludedCount,
                'max_allowed_horizon' => $maxAllowedHorizon,
                'annotated_observations' => $annotated,
                'training_series' => $trainingValues,
                'training_dates' => $trainingDates,
            ];
        }

        return [
            'is_eligible' => true,
            'status' => 'ELIGIBLE',
            'reason' => 'Historical data satisfies sample volume, temporal history and horizon safety rules.',
            'total_periods' => $totalPeriods,
            'eligible_periods' => $eligiblePeriods,
            'excluded_periods_count' => $excludedCount,
            'max_allowed_horizon' => $maxAllowedHorizon,
            'annotated_observations' => $annotated,
            'training_series' => $trainingValues,
            'training_dates' => $trainingDates,
        ];
    }

    protected function calculateVariance(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $sqDiff = array_map(fn ($x) => pow($x - $mean, 2), $values);

        return array_sum($sqDiff) / ($n - 1);
    }
}
