<?php

namespace App\Services\Forecasting\Models;

use App\Services\Forecasting\Contracts\ForecastModelInterface;

class HoltLinearTrendModel implements ForecastModelInterface
{
    protected ?float $alpha = null;

    protected ?float $beta = null;

    protected ?float $finalLevel = null;

    protected ?float $finalTrend = null;

    protected float $residualVariance = 0.0;

    protected array $history = [];

    protected array $fitted = [];

    public function __construct(?float $alpha = null, ?float $beta = null)
    {
        $this->alpha = $alpha;
        $this->beta = $beta;
    }

    public function fit(array $history): void
    {
        $this->history = array_values($history);
        $n = count($this->history);

        if ($n < 2) {
            $val = $this->history[0] ?? 0.0;
            $this->finalLevel = $val;
            $this->finalTrend = 0.0;
            $this->alpha = $this->alpha ?? 0.3;
            $this->beta = $this->beta ?? 0.1;
            $this->residualVariance = 0.001;

            return;
        }

        // If alpha or beta not fixed, deterministically optimize on a grid
        if ($this->alpha === null || $this->beta === null) {
            $this->optimizeParameters();
        }

        $this->runHolt($this->alpha, $this->beta);
    }

    protected function optimizeParameters(): void
    {
        $alphas = [0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9];
        $betas = [0.05, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6];

        $bestSse = PHP_FLOAT_MAX;
        $bestAlpha = 0.3;
        $bestBeta = 0.1;

        foreach ($alphas as $a) {
            foreach ($betas as $b) {
                $sse = $this->evaluateSse($a, $b);
                if ($sse < $bestSse) {
                    $bestSse = $sse;
                    $bestAlpha = $a;
                    $bestBeta = $b;
                }
            }
        }

        $this->alpha = $bestAlpha;
        $this->beta = $bestBeta;
    }

    protected function evaluateSse(float $alpha, float $beta): float
    {
        $n = count($this->history);
        $level = $this->history[0];
        $trend = $this->history[1] - $this->history[0];
        $sse = 0.0;

        for ($t = 1; $t < $n; $t++) {
            $pred = $level + $trend;
            $actual = $this->history[$t];
            $err = $actual - $pred;
            $sse += ($err * $err);

            $prevLevel = $level;
            $level = ($alpha * $actual) + ((1 - $alpha) * ($level + $trend));
            $trend = ($beta * ($level - $prevLevel)) + ((1 - $beta) * $trend);
        }

        return $sse;
    }

    protected function runHolt(float $alpha, float $beta): void
    {
        $n = count($this->history);
        $level = $this->history[0];
        $trend = $this->history[1] - $this->history[0];

        $this->fitted = [$level];
        $squaredErrors = [];

        for ($t = 1; $t < $n; $t++) {
            $pred = $level + $trend;
            $actual = $this->history[$t];
            $squaredErrors[] = pow($actual - $pred, 2);
            $this->fitted[] = $pred;

            $prevLevel = $level;
            $level = ($alpha * $actual) + ((1 - $alpha) * ($level + $trend));
            $trend = ($beta * ($level - $prevLevel)) + ((1 - $beta) * $trend);
        }

        $this->finalLevel = $level;
        $this->finalTrend = $trend;
        $errCount = count($squaredErrors);
        $this->residualVariance = $errCount > 0 ? array_sum($squaredErrors) / $errCount : 0.001;
    }

    public function predict(int $horizon): array
    {
        $level = $this->finalLevel ?? 0.0;
        $trend = $this->finalTrend ?? 0.0;

        $predictions = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $predictions[] = round($level + ($h * $trend), 4);
        }

        return $predictions;
    }

    public function name(): string
    {
        return 'Holt Linear Trend';
    }

    public function code(): string
    {
        return 'holt_trend';
    }

    public function description(): string
    {
        return 'Holt estimates the current level of the metric and its recent rate of change, then extends that trend into the forecast horizon.';
    }

    public function parameters(): array
    {
        return [
            'alpha' => $this->alpha !== null ? round($this->alpha, 4) : null,
            'beta' => $this->beta !== null ? round($this->beta, 4) : null,
            'final_level' => $this->finalLevel !== null ? round($this->finalLevel, 4) : null,
            'final_trend' => $this->finalTrend !== null ? round($this->finalTrend, 4) : null,
        ];
    }

    public function diagnostics(): array
    {
        return [
            'sample_size' => count($this->history),
            'final_level' => $this->finalLevel,
            'final_trend' => $this->finalTrend,
            'residual_variance' => round($this->residualVariance, 6),
            'alpha' => $this->alpha,
            'beta' => $this->beta,
        ];
    }

    public function complexityOrder(): int
    {
        return 4;
    }

    public function supportsConfidenceInterval(): bool
    {
        return true;
    }

    public function confidenceIntervals(int $horizon, float $level = 0.95): ?array
    {
        $sigma = sqrt(max(0.0001, $this->residualVariance));
        $z = 1.96; // 95% normal quantile
        $alpha = $this->alpha ?? 0.3;
        $beta = $this->beta ?? 0.1;
        $preds = $this->predict($horizon);

        $intervals = [];
        $sumMultiplier = 0.0;

        for ($h = 1; $h <= $horizon; $h++) {
            if ($h > 1) {
                $j = $h - 1;
                $sumMultiplier += pow($alpha + ($j * $alpha * $beta), 2);
            }
            $stdErrH = $sigma * sqrt(1.0 + $sumMultiplier);
            $halfWidth = $z * $stdErrH;

            $intervals[] = [
                'low' => round($preds[$h - 1] - $halfWidth, 4),
                'high' => round($preds[$h - 1] + $halfWidth, 4),
            ];
        }

        return $intervals;
    }
}
