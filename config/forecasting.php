<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data Quality Gate Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the minimum historical periods and observations required before
    | Atlas will calculate a production predictive forecast.
    |
    */
    'min_history_periods' => (int) env('FORECAST_MIN_HISTORY_PERIODS', 28),
    'min_surveys_per_period' => (int) env('FORECAST_MIN_SURVEYS_PER_PERIOD', 20),

    /*
    |--------------------------------------------------------------------------
    | Forecast Horizon Safety
    |--------------------------------------------------------------------------
    |
    | Maximum forecast horizon allowed as a ratio of eligible historical periods.
    | Default is 1/3 (e.g., 42 historical days allow at most 14-day forecast).
    |
    */
    'max_horizon_ratio' => (float) env('FORECAST_MAX_HORIZON_RATIO', 1 / 3),

    /*
    |--------------------------------------------------------------------------
    | Supported Forecasting Models
    |--------------------------------------------------------------------------
    |
    | Atlas supports exactly these deterministic, explainable models.
    | Selection is made automatically via walk-forward validation out-of-sample MAE.
    |
    */
    'candidate_models' => [
        'naive',
        'moving_average',
        'holt_trend',
        'linear_trend',
    ],

    /*
    |--------------------------------------------------------------------------
    | Moving Average Candidate Windows
    |--------------------------------------------------------------------------
    |
    | Evaluated during walk-forward validation rather than chosen blindly.
    |
    */
    'moving_average_windows' => [3, 5, 7],

    /*
    |--------------------------------------------------------------------------
    | Driver Analysis Sample Thresholds
    |--------------------------------------------------------------------------
    |
    | Reliability classifications based on survey sample size per driver group.
    |
    */
    'driver_sample_thresholds' => [
        'insufficient' => 10,
        'low' => 30,
        'moderate' => 100,
    ],
];
