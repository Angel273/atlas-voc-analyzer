<?php

namespace App\Services\Forecasting\Models;

use App\Services\Forecasting\Contracts\ForecastModelInterface;

class NaiveForecastModel implements ForecastModelInterface
{
    protected ?float $lastValue = null;

    protected array $history = [];

    public function fit(array $history): void
    {
        $this->history = array_values($history);
        $this->lastValue = ! empty($this->history) ? (float) end($this->history) : 0.0;
    }

    public function predict(int $horizon): array
    {
        $val = $this->lastValue ?? 0.0;

        return array_fill(0, max(1, $horizon), round($val, 4));
    }

    public function name(): string
    {
        return 'Naive Baseline';
    }

    public function code(): string
    {
        return 'naive';
    }

    public function description(): string
    {
        return 'The most recent valid observation is used as the expected future value.';
    }

    public function parameters(): array
    {
        return [
            'last_observed_value' => $this->lastValue !== null ? round($this->lastValue, 4) : null,
        ];
    }

    public function diagnostics(): array
    {
        return [
            'sample_size' => count($this->history),
            'method' => 'persistence_baseline',
        ];
    }

    public function complexityOrder(): int
    {
        return 1;
    }

    public function supportsConfidenceInterval(): bool
    {
        return false;
    }

    public function confidenceIntervals(int $horizon, float $level = 0.95): ?array
    {
        return null;
    }
}
