<?php

namespace App\Services\Forecasting;

class ForecastReliabilityEvaluator
{
    /**
     * Compute a deterministic forecast reliability rating from objective statistical signals.
     *
     * @return array{
     *     rating: string,
     *     score: int,
     *     signals: array<string, mixed>,
     *     explanation: string
     * }
     */
    public function evaluate(
        int $eligiblePeriods,
        float $avgSurveyVolume,
        float $validationMae,
        int $horizon,
        int $excludedPeriods
    ): array {
        $totalPeriods = $eligiblePeriods + $excludedPeriods;
        $horizonRatio = $eligiblePeriods > 0 ? $horizon / $eligiblePeriods : 1.0;
        $excludedRatio = $totalPeriods > 0 ? $excludedPeriods / $totalPeriods : 0.0;

        $signals = [
            'eligible_periods' => $eligiblePeriods,
            'avg_daily_surveys' => round($avgSurveyVolume, 1),
            'validation_mae' => round($validationMae, 4),
            'forecast_horizon_days' => $horizon,
            'horizon_ratio' => round($horizonRatio, 3),
            'excluded_periods_ratio' => round($excludedRatio, 3),
        ];

        // Insufficient if below baseline eligibility
        if ($eligiblePeriods < 28) {
            return [
                'rating' => 'INSUFFICIENT',
                'score' => 10,
                'signals' => $signals,
                'explanation' => "Fiabilidad Insuficiente: Se cuentan con {$eligiblePeriods} periodos válidos, por debajo del umbral mínimo de 28 periodos.",
            ];
        }

        // Score based on multi-factor weighted scoring (0 to 100)
        $score = 0;

        // 1. Historical Depth (max 30 pts)
        if ($eligiblePeriods >= 60) {
            $score += 30;
        } elseif ($eligiblePeriods >= 45) {
            $score += 24;
        } elseif ($eligiblePeriods >= 35) {
            $score += 18;
        } else {
            $score += 12;
        }

        // 2. Average Survey Volume (max 25 pts)
        if ($avgSurveyVolume >= 50) {
            $score += 25;
        } elseif ($avgSurveyVolume >= 30) {
            $score += 20;
        } elseif ($avgSurveyVolume >= 20) {
            $score += 15;
        } else {
            $score += 5;
        }

        // 3. Validation Out-of-Sample MAE (max 25 pts)
        if ($validationMae <= 0.035) {
            $score += 25;
        } elseif ($validationMae <= 0.060) {
            $score += 18;
        } elseif ($validationMae <= 0.090) {
            $score += 12;
        } else {
            $score += 5;
        }

        // 4. Horizon Conservation (max 20 pts)
        if ($horizonRatio <= 0.20) {
            $score += 20;
        } elseif ($horizonRatio <= 0.28) {
            $score += 15;
        } elseif ($horizonRatio <= 0.34) {
            $score += 10;
        } else {
            $score += 0;
        }

        // Penalty for high exclusion ratio (> 20% excluded)
        if ($excludedRatio > 0.20) {
            $score = max(0, $score - 15);
        }

        // Map score to categorical rating
        if ($score >= 78) {
            $rating = 'HIGH';
            $explanation = "Fiabilidad Alta (Score {$score}/100): Serie histórica robusta ({$eligiblePeriods} periodos), alto volumen diario promedio ({$signals['avg_daily_surveys']} encuestas) y bajo error de validación out-of-sample.";
        } elseif ($score >= 55) {
            $rating = 'MODERATE';
            $explanation = "Fiabilidad Moderada (Score {$score}/100): Datos históricos suficientes para respaldar la tendencia proyectada con precisión operativa aceptable.";
        } else {
            $rating = 'LOW';
            $explanation = "Fiabilidad Baja (Score {$score}/100): Dispersión residual significativa o muestra cercana al límite mínimo; interpretar las proyecciones con cautela.";
        }

        return [
            'rating' => $rating,
            'score' => $score,
            'signals' => $signals,
            'explanation' => $explanation,
        ];
    }
}
