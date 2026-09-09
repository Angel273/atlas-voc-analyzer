<?php

namespace App\Services\Forecasting\Models;

use App\Services\Forecasting\Contracts\ForecastModelInterface;

class MovingAverageModel implements ForecastModelInterface
{
    protected ?int $window = null;

    protected array $candidateWindows = [3, 5, 7];

    protected ?int $selectedWindow = null;

    protected ?float $lastMovingAverage = null;

    protected array $history = [];

    protected array $windowEvaluations = [];

    public function __construct(?int $window = null, array $candidateWindows = [3, 5, 7])
    {
        $this->window = $window;
        $this->candidateWindows = $candidateWindows;
    }

    public function fit(array $history): void
    {
        $this->history = array_values($history);
        $n = count($this->history);

        if ($n === 0) {
            $this->selectedWindow = 1;
            $this->lastMovingAverage = 0.0;

            return;
        }

        if ($this->window !== null) {
            $this->selectedWindow = max(1, min($n, $this->window));
        } else {
            // Deterministically evaluate candidate windows on available history
            $validCandidates = array_filter($this->candidateWindows, fn ($k) => $k < $n && $k >= 2);
            if (empty($validCandidates)) {
                $validCandidates = [max(1, min(3, (int) floor($n / 2)))];
            }

            $bestWindow = null;
            $bestMae = PHP_FLOAT_MAX;
            $this->windowEvaluations = [];

            foreach ($validCandidates as $k) {
                $errors = [];
                for ($i = $k; $i < $n; $i++) {
                    $slice = array_slice($this->history, $i - $k, $k);
                    $pred = array_sum($slice) / $k;
                    $errors[] = abs($this->history[$i] - $pred);
                }
                $mae = ! empty($errors) ? array_sum($errors) / count($errors) : 0.0;
                $this->windowEvaluations[$k] = round($mae, 4);

                if ($mae < $bestMae) {
                    $bestMae = $mae;
                    $bestWindow = $k;
                }
            }

            $this->selectedWindow = $bestWindow ?? $validCandidates[0];
        }

        // Calculate the moving average of the most recent K periods
        $k = $this->selectedWindow;
        $recentSlice = array_slice($this->history, max(0, $n - $k));
        $this->lastMovingAverage = ! empty($recentSlice) ? array_sum($recentSlice) / count($recentSlice) : 0.0;
    }

    public function predict(int $horizon): array
    {
        $val = $this->lastMovingAverage ?? 0.0;

        return array_fill(0, max(1, $horizon), round($val, 4));
    }

    public function name(): string
    {
        return 'Moving Average';
    }

    public function code(): string
    {
        return 'moving_average';
    }

    public function description(): string
    {
        return 'The forecast represents the recent historical average and reduces short-term noise.';
    }

    public function parameters(): array
    {
        return [
            'window_k' => $this->selectedWindow,
            'last_moving_average' => $this->lastMovingAverage !== null ? round($this->lastMovingAverage, 4) : null,
            'candidate_windows_evaluated' => $this->windowEvaluations,
        ];
    }

    public function diagnostics(): array
    {
        return [
            'sample_size' => count($this->history),
            'selected_window' => $this->selectedWindow,
            'window_candidate_errors' => $this->windowEvaluations,
        ];
    }

    public function complexityOrder(): int
    {
        return 2;
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
