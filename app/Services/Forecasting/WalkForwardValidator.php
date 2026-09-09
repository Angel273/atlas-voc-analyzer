<?php

namespace App\Services\Forecasting;

use App\Services\Forecasting\Contracts\ForecastModelInterface;
use App\Services\Forecasting\Models\HoltLinearTrendModel;
use App\Services\Forecasting\Models\LinearTrendModel;
use App\Services\Forecasting\Models\MovingAverageModel;
use App\Services\Forecasting\Models\NaiveForecastModel;

class WalkForwardValidator
{
    /**
     * Build the default initial candidate model suite.
     *
     * @return ForecastModelInterface[]
     */
    public function getDefaultCandidateModels(): array
    {
        $hasConfig = function_exists('config') && function_exists('app') && app()->has('config');
        $windows = $hasConfig
            ? (array) config('forecasting.moving_average_windows', [3, 5, 7])
            : [3, 5, 7];

        return [
            new NaiveForecastModel,
            new MovingAverageModel(null, $windows),
            new LinearTrendModel,
            new HoltLinearTrendModel,
        ];
    }

    /**
     * Perform rolling-origin out-of-sample walk-forward validation and deterministically select the best model.
     *
     * @param  float[]  $series  Chronological valid observations
     * @param  ForecastModelInterface[]|null  $candidates
     * @return array{
     *     selected_model: ForecastModelInterface,
     *     selected_code: string,
     *     selected_name: string,
     *     selected_mae: float,
     *     selected_rmse: float,
     *     selection_reason: string,
     *     candidate_comparison: array<int, array{
     *         code: string,
     *         name: string,
     *         mae: float,
     *         rmse: float,
     *         validation_predictions: int,
     *         is_selected: bool,
     *         complexity_order: int,
     *         parameters: array,
     *         description: string
     *     }>
     * }
     */
    public function evaluateAndSelect(array $series, ?array $candidates = null): array
    {
        $series = array_values($series);
        $n = count($series);
        $candidates = $candidates ?? $this->getDefaultCandidateModels();

        // Determine validation window (rolling origin)
        // Reserve between 5 and 14 steps, or ~25% of history, ensuring at least 10 training points
        $validationSteps = max(3, min(14, (int) round($n * 0.25)));
        if ($n - $validationSteps < 5) {
            $validationSteps = max(2, $n - 5);
        }
        $startOrigin = $n - $validationSteps;

        // Structure to track errors per model
        $modelStats = [];
        foreach ($candidates as $model) {
            $modelStats[$model->code()] = [
                'model' => $model,
                'abs_errors' => [],
                'sq_errors' => [],
            ];
        }

        // Walk-forward rolling origin loop
        for ($t = $startOrigin; $t < $n; $t++) {
            $trainSlice = array_slice($series, 0, $t);
            $actual = $series[$t];

            foreach ($candidates as $templateModel) {
                // Instantiate fresh clone/instance for this step
                $stepModel = $this->cloneModel($templateModel);
                $stepModel->fit($trainSlice);
                $predictions = $stepModel->predict(1);
                $pred = $predictions[0] ?? end($trainSlice);

                $err = $actual - $pred;
                $modelStats[$templateModel->code()]['abs_errors'][] = abs($err);
                $modelStats[$templateModel->code()]['sq_errors'][] = pow($err, 2);
            }
        }

        // Calculate summary MAE & RMSE and fit final models on full history
        $comparison = [];
        foreach ($modelStats as $code => $stat) {
            $absErrors = $stat['abs_errors'];
            $sqErrors = $stat['sq_errors'];
            $count = count($absErrors);

            $mae = $count > 0 ? array_sum($absErrors) / $count : 0.0;
            $rmse = $count > 0 ? sqrt(array_sum($sqErrors) / $count) : 0.0;

            /** @var ForecastModelInterface $finalModel */
            $finalModel = $this->cloneModel($stat['model']);
            $finalModel->fit($series);

            $comparison[$code] = [
                'code' => $code,
                'name' => $finalModel->name(),
                'mae' => round($mae, 4),
                'rmse' => round($rmse, 4),
                'validation_predictions' => $count,
                'is_selected' => false,
                'complexity_order' => $finalModel->complexityOrder(),
                'parameters' => $finalModel->parameters(),
                'description' => $finalModel->description(),
                'fitted_model' => $finalModel,
            ];
        }

        // Deterministic Selection Algorithm:
        // 1. Primary: Lowest out-of-sample MAE
        // 2. Secondary: Lowest RMSE (if MAE within 0.001)
        // 3. Simplicity: Lowest complexityOrder (if both MAE and RMSE practically equivalent)
        uasort($comparison, function ($a, $b) {
            $maeDiff = $a['mae'] - $b['mae'];
            if (abs($maeDiff) > 0.001) {
                return $maeDiff <=> 0;
            }

            $rmseDiff = $a['rmse'] - $b['rmse'];
            if (abs($rmseDiff) > 0.001) {
                return $rmseDiff <=> 0;
            }

            return $a['complexity_order'] <=> $b['complexity_order'];
        });

        $sortedCodes = array_keys($comparison);
        $selectedCode = $sortedCodes[0];
        $comparison[$selectedCode]['is_selected'] = true;

        $selected = $comparison[$selectedCode];
        $selectedModel = $selected['fitted_model'];

        // Build explainable selection reason
        $selectionReason = "Selected {$selected['name']} based on lowest out-of-sample MAE ({$selected['mae']}) across {$selected['validation_predictions']} walk-forward validation windows.";
        if (count($sortedCodes) > 1) {
            $runnerUpCode = $sortedCodes[1];
            $runnerUp = $comparison[$runnerUpCode];
            $selectionReason .= " Outperformed {$runnerUp['name']} (MAE: {$runnerUp['mae']}, RMSE: {$runnerUp['rmse']}).";
        }

        // Clean internal object before returning table
        $cleanComparison = array_values(array_map(function ($row) {
            unset($row['fitted_model']);

            return $row;
        }, $comparison));

        return [
            'selected_model' => $selectedModel,
            'selected_code' => $selectedCode,
            'selected_name' => $selected['name'],
            'selected_mae' => $selected['mae'],
            'selected_rmse' => $selected['rmse'],
            'selection_reason' => $selectionReason,
            'candidate_comparison' => $cleanComparison,
        ];
    }

    protected function cloneModel(ForecastModelInterface $model): ForecastModelInterface
    {
        return match ($model->code()) {
            'naive' => new NaiveForecastModel,
            'moving_average' => new MovingAverageModel,
            'linear_trend' => new LinearTrendModel,
            'holt_trend' => new HoltLinearTrendModel,
            default => clone $model,
        };
    }
}
