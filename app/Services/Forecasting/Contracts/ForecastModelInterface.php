<?php

namespace App\Services\Forecasting\Contracts;

interface ForecastModelInterface
{
    /**
     * Fit the model using sequential historical float values (chronological order).
     *
     * @param  float[]  $history
     */
    public function fit(array $history): void;

    /**
     * Generate step-ahead predictions for the given forecast horizon.
     *
     * @param  int  $horizon  Number of periods ahead to forecast
     * @return float[] Predicted values for steps 1..$horizon
     */
    public function predict(int $horizon): array;

    /**
     * Human-readable display name (e.g., "Holt Linear Trend").
     */
    public function name(): string;

    /**
     * Canonical machine-readable model identifier (e.g., "holt_trend").
     */
    public function code(): string;

    /**
     * Clear, business-friendly explanation of how the model calculates forecasts.
     */
    public function description(): string;

    /**
     * Fitted parameters (e.g., alpha, beta, slope, window size).
     */
    public function parameters(): array;

    /**
     * Model diagnostics (in-sample fit, R2 when applicable, levels, trends).
     */
    public function diagnostics(): array;

    /**
     * Relative complexity score used for deterministic tie-breaking (1 = simplest).
     * Order: Naive (1) -> Moving Average (2) -> Linear Trend (3) -> Holt Linear Trend (4).
     */
    public function complexityOrder(): int;

    /**
     * Whether this model provides statistically justified confidence intervals.
     */
    public function supportsConfidenceInterval(): bool;

    /**
     * Compute confidence bounds for the predictions if supported.
     *
     * @return array<int, array{low: float, high: float}>|null
     */
    public function confidenceIntervals(int $horizon, float $level = 0.95): ?array;
}
