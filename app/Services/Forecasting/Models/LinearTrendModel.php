<?php

namespace App\Services\Forecasting\Models;

use App\Services\Forecasting\Contracts\ForecastModelInterface;

class LinearTrendModel implements ForecastModelInterface
{
    protected ?float $slope = null;

    protected ?float $intercept = null;

    protected ?float $r2 = null;

    protected float $residualVariance = 0.0;

    protected array $history = [];

    protected float $meanX = 0.0;

    protected float $ssX = 0.0;

    public function fit(array $history): void
    {
        $this->history = array_values($history);
        $n = count($this->history);

        if ($n < 2) {
            $val = $this->history[0] ?? 0.0;
            $this->slope = 0.0;
            $this->intercept = $val;
            $this->r2 = 1.0;
            $this->residualVariance = 0.001;

            return;
        }

        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = (float) $i;
            $y = (float) $this->history[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumXX += ($x * $x);
        }

        $this->meanX = $sumX / $n;
        $meanY = $sumY / $n;
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $this->ssX = max(0.0001, $sumXX - ($sumX * $sumX / $n));

        $this->slope = $denominator != 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0.0;
        $this->intercept = $meanY - ($this->slope * $this->meanX);

        $ssTot = 0.0;
        $ssRes = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $pred = $this->intercept + ($this->slope * $i);
            $actual = $this->history[$i];
            $ssRes += pow($actual - $pred, 2);
            $ssTot += pow($actual - $meanY, 2);
        }

        $df = max(1, $n - 2);
        $this->residualVariance = $ssRes / $df;
        $this->r2 = $ssTot > 0.00001 ? max(0.0, min(1.0, 1.0 - ($ssRes / $ssTot))) : 1.0;
    }

    public function predict(int $horizon): array
    {
        $n = count($this->history);
        $slope = $this->slope ?? 0.0;
        $intercept = $this->intercept ?? 0.0;

        $predictions = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $timeIndex = ($n - 1) + $h;
            $predictions[] = round($intercept + ($slope * $timeIndex), 4);
        }

        return $predictions;
    }

    public function name(): string
    {
        return 'Linear Trend Regression';
    }

    public function code(): string
    {
        return 'linear_trend';
    }

    public function description(): string
    {
        return 'A straight trend line is fitted to the historical metric and extended into the future.';
    }

    public function parameters(): array
    {
        return [
            'slope' => $this->slope !== null ? round($this->slope, 6) : null,
            'intercept' => $this->intercept !== null ? round($this->intercept, 4) : null,
            'r2' => $this->r2 !== null ? round($this->r2, 4) : null,
        ];
    }

    public function diagnostics(): array
    {
        return [
            'sample_size' => count($this->history),
            'slope' => $this->slope,
            'intercept' => $this->intercept,
            'r2' => $this->r2,
            'residual_variance' => round($this->residualVariance, 6),
        ];
    }

    public function complexityOrder(): int
    {
        return 3;
    }

    public function supportsConfidenceInterval(): bool
    {
        return true;
    }

    public function confidenceIntervals(int $horizon, float $level = 0.95): ?array
    {
        $n = count($this->history);
        if ($n < 3) {
            return null;
        }

        $se = sqrt(max(0.0001, $this->residualVariance));
        $tVal = 1.96; // asymptotic normal / t approximation
        $preds = $this->predict($horizon);
        $intervals = [];

        for ($h = 1; $h <= $horizon; $h++) {
            $xh = ($n - 1) + $h;
            $leverage = (1.0 / $n) + (pow($xh - $this->meanX, 2) / $this->ssX);
            $predSe = $se * sqrt(1.0 + $leverage);
            $margin = $tVal * $predSe;

            $intervals[] = [
                'low' => round($preds[$h - 1] - $margin, 4),
                'high' => round($preds[$h - 1] + $margin, 4),
            ];
        }

        return $intervals;
    }
}
