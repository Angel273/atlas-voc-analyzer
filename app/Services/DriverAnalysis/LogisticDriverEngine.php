<?php

namespace App\Services\DriverAnalysis;

use App\Services\DriverAnalysis\Support\MatrixHelper;

class LogisticDriverEngine
{
    /**
     * Fit Logistic Regression via IRLS (Newton-Raphson) for binary VOC metrics (CSAT, Professionalism).
     *
     * @param array<int, array{
     *     target_value: float,
     *     category: ?string,
     *     tenure_days: ?int,
     *     wave: ?string,
     *     supervisor: ?string,
     *     survey_date: ?string
     * }> $surveys
     * @param  string  $metricName  'csat' or 'professionalism'
     */
    public function analyze(array $surveys, string $metricName = 'csat'): array
    {
        $n = count($surveys);
        if ($n < 15) {
            return [
                'status' => 'INSUFFICIENT_SAMPLE',
                'target_metric' => $metricName,
                'sample_size' => $n,
                'controlled_variables' => ['category', 'tenure_days', 'wave', 'supervisor', 'time'],
                'reference_categories' => [],
                'drivers' => [],
                'diagnostics' => ['reason' => "Se requieren al menos 15 encuestas para la regresión logística (disponibles: {$n})."],
            ];
        }

        // 1. Identify distinct categorical values and choose reference categories
        $categoryCounts = [];
        $waveCounts = [];
        $supervisorCounts = [];
        $dates = [];

        foreach ($surveys as $s) {
            $cat = trim($s['category'] ?? '') ?: 'Uncategorized';
            $wave = trim($s['wave'] ?? '') ?: 'Unknown Wave';
            $sup = trim($s['supervisor'] ?? '') ?: 'Unknown Sup';

            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
            $waveCounts[$wave] = ($waveCounts[$wave] ?? 0) + 1;
            $supervisorCounts[$sup] = ($supervisorCounts[$sup] ?? 0) + 1;
            if (! empty($s['survey_date'])) {
                $dates[] = substr((string) $s['survey_date'], 0, 10);
            }
        }

        arsort($categoryCounts);
        arsort($waveCounts);
        arsort($supervisorCounts);

        $refCategory = array_key_first($categoryCounts);
        $refWave = array_key_first($waveCounts);
        $refSupervisor = array_key_first($supervisorCounts);

        $minDate = ! empty($dates) ? min($dates) : now()->format('Y-m-d');
        $minTimestamp = strtotime($minDate);

        // 2. Build predictor column mapping
        $columnNames = ['Intercept'];
        $columnMetadata = [
            ['type' => 'intercept', 'name' => 'Intercept', 'group' => 'baseline', 'sample_size' => $n],
        ];

        foreach ($categoryCounts as $catName => $count) {
            if ($catName === $refCategory) {
                continue;
            }
            $columnNames[] = "Cat_{$catName}";
            $columnMetadata[] = [
                'type' => 'dummy',
                'variable' => 'category',
                'label' => $catName,
                'name' => "Categoría: {$catName}",
                'reference' => $refCategory,
                'sample_size' => $count,
            ];
        }

        foreach ($waveCounts as $waveName => $count) {
            if ($waveName === $refWave) {
                continue;
            }
            $columnNames[] = "Wave_{$waveName}";
            $columnMetadata[] = [
                'type' => 'dummy',
                'variable' => 'wave',
                'label' => $waveName,
                'name' => "Ola: {$waveName}",
                'reference' => $refWave,
                'sample_size' => $count,
            ];
        }

        $supLimit = 8;
        $activeSups = array_slice($supervisorCounts, 0, $supLimit, true);
        foreach ($activeSups as $supName => $count) {
            if ($supName === $refSupervisor) {
                continue;
            }
            $columnNames[] = "Sup_{$supName}";
            $columnMetadata[] = [
                'type' => 'dummy',
                'variable' => 'supervisor',
                'label' => $supName,
                'name' => "Supervisor: {$supName}",
                'reference' => $refSupervisor,
                'sample_size' => $count,
            ];
        }

        $columnNames[] = 'tenure_scaled';
        $columnMetadata[] = [
            'type' => 'numeric',
            'variable' => 'tenure',
            'label' => 'Antigüedad (+100 días)',
            'name' => 'Antigüedad (por cada 100 días)',
            'sample_size' => $n,
        ];

        $columnNames[] = 'time_scaled';
        $columnMetadata[] = [
            'type' => 'numeric',
            'variable' => 'time',
            'label' => 'Tiempo / Tendencia (+30 días)',
            'name' => 'Evolución temporal (meses transcurridos)',
            'sample_size' => $n,
        ];

        $p = count($columnNames);

        // 3. Assemble Design Matrix X and Binary Vector y
        $X = [];
        $y = [];

        foreach ($surveys as $s) {
            $cat = trim($s['category'] ?? '') ?: 'Uncategorized';
            $wave = trim($s['wave'] ?? '') ?: 'Unknown Wave';
            $sup = trim($s['supervisor'] ?? '') ?: 'Unknown Sup';
            $tenure = (float) ($s['tenure_days'] ?? 30);
            $sDate = substr((string) ($s['survey_date'] ?? $minDate), 0, 10);
            $daysSinceStart = (strtotime($sDate) - $minTimestamp) / 86400;

            $row = [1.0];

            foreach ($categoryCounts as $catName => $count) {
                if ($catName === $refCategory) {
                    continue;
                }
                $row[] = ($cat === $catName) ? 1.0 : 0.0;
            }

            foreach ($waveCounts as $waveName => $count) {
                if ($waveName === $refWave) {
                    continue;
                }
                $row[] = ($wave === $waveName) ? 1.0 : 0.0;
            }

            foreach ($activeSups as $supName => $count) {
                if ($supName === $refSupervisor) {
                    continue;
                }
                $row[] = ($sup === $supName) ? 1.0 : 0.0;
            }

            $row[] = $tenure / 100.0;
            $row[] = $daysSinceStart / 30.0;

            $X[] = $row;
            // Binarize (1 for positive satisfaction Top-Box, 0 otherwise)
            $rawVal = (float) ($s['target_value'] ?? 0);
            $y[] = ($rawVal >= 0.99 || ($rawVal > 0 && $rawVal <= 1.0 && $rawVal >= 0.5)) ? 1.0 : 0.0;
        }

        $meanY = array_sum($y) / $n;
        if ($meanY <= 0.01 || $meanY >= 0.99) {
            return [
                'status' => 'LOW_VARIANCE',
                'sample_size' => $n,
                'drivers' => [],
                'diagnostics' => ['reason' => 'La métrica presenta homogeneidad extrema (casi todos 1 o todos 0).'],
            ];
        }

        // 4. Fit via IRLS (Iteratively Reweighted Least Squares)
        $beta = array_fill(0, $p, 0.0);
        $beta[0] = log(max(0.05, min(0.95, $meanY)) / (1.0 - max(0.05, min(0.95, $meanY))));

        $maxIter = 15;
        $converged = false;
        $invHessian = null;

        for ($iter = 0; $iter < $maxIter; $iter++) {
            $pVec = [];
            $W = [];

            for ($i = 0; $i < $n; $i++) {
                $z = 0.0;
                for ($j = 0; $j < $p; $j++) {
                    $z += $X[$i][$j] * $beta[$j];
                }
                // Sigmoid with clipping for numerical stability
                $zClamped = max(-20.0, min(20.0, $z));
                $pi = 1.0 / (1.0 + exp(-$zClamped));
                $pi = max(1e-5, min(1.0 - 1e-5, $pi));
                $pVec[] = $pi;
                $W[] = $pi * (1.0 - $pi);
            }

            // Gradient: g = X^T (y - p)
            $g = array_fill(0, $p, 0.0);
            for ($i = 0; $i < $n; $i++) {
                $diff = $y[$i] - $pVec[$i];
                for ($j = 0; $j < $p; $j++) {
                    $g[$j] += $X[$i][$j] * $diff;
                }
            }

            // Hessian: H = X^T W X
            $H = array_fill(0, $p, array_fill(0, $p, 0.0));
            for ($i = 0; $i < $n; $i++) {
                $wi = $W[$i];
                for ($j = 0; $j < $p; $j++) {
                    $xij = $X[$i][$j];
                    if (abs($xij) < 1e-12) {
                        continue;
                    }
                    for ($k = 0; $k < $p; $k++) {
                        $H[$j][$k] += $xij * $wi * $X[$i][$k];
                    }
                }
            }

            // Solve delta = H^-1 g
            $invH = MatrixHelper::invert($H, 1e-5);
            if (! $invH) {
                break;
            }
            $invHessian = $invH;

            $delta = MatrixHelper::multiplyVector($invH, $g);

            $maxDelta = 0.0;
            for ($j = 0; $j < $p; $j++) {
                $beta[$j] += $delta[$j];
                $maxDelta = max($maxDelta, abs($delta[$j]));
            }

            if ($maxDelta < 1e-4) {
                $converged = true;
                break;
            }
        }

        if (! $invHessian) {
            return [
                'status' => 'NUMERICAL_INSTABILITY',
                'sample_size' => $n,
                'drivers' => [],
                'diagnostics' => ['reason' => 'No fue posible converger en el ajuste logístico debido a separación casi perfecta.'],
            ];
        }

        // 5. Build Structured Business-Readable Drivers
        $baseProb = $meanY;
        $baseVariance = $baseProb * (1.0 - $baseProb);

        $drivers = [];
        for ($j = 1; $j < $p; $j++) {
            $meta = $columnMetadata[$j];
            $coef = $beta[$j];
            $se = sqrt(max(1e-8, $invHessian[$j][$j]));
            $zStat = $se > 1e-7 ? $coef / $se : 0.0;
            $pVal = $this->approximatePValue(abs($zStat));

            $oddsRatio = exp($coef);
            // Marginal effect in probability (percentage points) evaluated at baseline mean
            $marginalProbDiff = $coef * $baseVariance;
            $percentagePoints = round($marginalProbDiff * 100, 1);

            $sampleCount = $meta['sample_size'];

            if ($sampleCount < 10) {
                $confidence = 'INSUFFICIENT';
            } elseif ($sampleCount < 30) {
                $confidence = 'LOW';
            } elseif ($pVal > 0.08) {
                $confidence = 'MODERATE';
            } else {
                $confidence = 'SUPPORTED';
            }

            $direction = $percentagePoints > 0.5 ? 'Positive' : ($percentagePoints < -0.5 ? 'Negative' : 'Neutral');

            $drivers[] = [
                'driver' => $meta['name'],
                'variable_group' => $meta['variable'] ?? 'factor',
                'label' => $meta['label'] ?? $meta['name'],
                'estimated_effect' => "{$percentagePoints} pp",
                'percentage_points' => $percentagePoints,
                'odds_ratio' => round($oddsRatio, 2),
                'direction' => $direction,
                'sample_size' => $sampleCount,
                'confidence' => $confidence,
                'standard_error' => round($se, 4),
                'z_statistic' => round($zStat, 2),
                'p_value' => round($pVal, 4),
                'reference_category' => $meta['reference'] ?? null,
                'explanation' => $this->buildLogisticExplanation($meta, $percentagePoints, $confidence, strtoupper($metricName)),
            ];
        }

        // Sort by absolute percentage points impact descending
        usort($drivers, fn ($a, $b) => abs($b['percentage_points']) <=> abs($a['percentage_points']));

        return [
            'status' => 'COMPLETED',
            'target_metric' => $metricName,
            'sample_size' => $n,
            'controlled_variables' => ['category', 'tenure_days', 'wave', 'supervisor', 'time'],
            'reference_categories' => [
                'category' => $refCategory,
                'wave' => $refWave,
                'supervisor' => $refSupervisor,
            ],
            'drivers' => $drivers,
            'diagnostics' => [
                'model_type' => 'Logistic Regression (Newton-Raphson / IRLS)',
                'sample_positive_rate' => round($meanY * 100, 1).'%',
                'parameters_count' => $p,
                'converged' => $converged,
            ],
        ];
    }

    protected function buildLogisticExplanation(array $meta, float $pp, string $confidence, string $metric): string
    {
        $ppStr = ($pp >= 0 ? "+{$pp}" : (string) $pp).' pp';
        $name = $meta['name'];
        $ref = $meta['reference'] ?? 'línea base';

        if ($confidence === 'INSUFFICIENT') {
            return "{$name} cuenta con un volumen muestral muy bajo ({$meta['sample_size']} encuestas). El impacto aparente de {$ppStr} carece de fiabilidad estadística.";
        }

        return "{$name} está asociado con una probabilidad estimada {$ppStr} de {$metric} positivo en comparación con {$ref}, controlando por antigüedad, ola, supervisor y tiempo.";
    }

    protected function approximatePValue(float $z): float
    {
        $x = $z / sqrt(2.0);
        $p = 0.3275911;
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;

        $tVal = 1.0 / (1.0 + ($p * $x));
        $erf = 1.0 - (((((($a5 * $tVal + $a4) * $tVal) + $a3) * $tVal + $a2) * $tVal + $a1) * $tVal * exp(-($x * $x)));

        $tailProb = 0.5 * (1.0 - $erf);

        return max(0.0001, min(1.0, 2.0 * $tailProb));
    }
}
