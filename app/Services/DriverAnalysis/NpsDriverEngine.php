<?php

namespace App\Services\DriverAnalysis;

use App\Services\DriverAnalysis\Support\MatrixHelper;

class NpsDriverEngine
{
    /**
     * Fit Multiple Linear Regression (OLS) for NPS drivers.
     *
     * @param array<int, array{
     *     nps_score: float,
     *     category: ?string,
     *     tenure_days: ?int,
     *     wave: ?string,
     *     supervisor: ?string,
     *     survey_date: ?string
     * }> $surveys
     */
    public function analyze(array $surveys): array
    {
        $n = count($surveys);
        if ($n < 10) {
            return [
                'status' => 'INSUFFICIENT_SAMPLE',
                'target_metric' => 'nps',
                'sample_size' => $n,
                'controlled_variables' => ['category', 'tenure_days', 'wave', 'supervisor', 'time'],
                'reference_categories' => [],
                'drivers' => [],
                'diagnostics' => ['reason' => "Se requieren al menos 10 encuestas para el análisis de drivers (disponibles: {$n})."],
            ];
        }

        // 1. Identify distinct categorical values and choose reference categories (most frequent)
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

        // 2. Build predictor column mapping (excluding reference categories to avoid multicollinearity)
        $columnNames = ['Intercept'];
        $columnMetadata = [
            ['type' => 'intercept', 'name' => 'Intercept', 'group' => 'baseline', 'sample_size' => $n],
        ];

        // Categories (excluding reference)
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

        // Waves (excluding reference)
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

        // Supervisors (limit to top supervisors if many, excluding reference)
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

        // Continuous: Tenure (scaled by 100 days)
        $columnNames[] = 'tenure_scaled';
        $columnMetadata[] = [
            'type' => 'numeric',
            'variable' => 'tenure',
            'label' => 'Antigüedad (+100 días)',
            'name' => 'Antigüedad (por cada 100 días)',
            'sample_size' => $n,
        ];

        // Continuous: Time (scaled in months / 30 days)
        $columnNames[] = 'time_scaled';
        $columnMetadata[] = [
            'type' => 'numeric',
            'variable' => 'time',
            'label' => 'Tiempo / Tendencia (+30 días)',
            'name' => 'Evolución temporal (meses transcurridos)',
            'sample_size' => $n,
        ];

        $p = count($columnNames);

        // 3. Assemble Design Matrix X and Target Vector y
        $X = [];
        $y = [];

        foreach ($surveys as $s) {
            $cat = trim($s['category'] ?? '') ?: 'Uncategorized';
            $wave = trim($s['wave'] ?? '') ?: 'Unknown Wave';
            $sup = trim($s['supervisor'] ?? '') ?: 'Unknown Sup';
            $tenure = (float) ($s['tenure_days'] ?? 30);
            $sDate = substr((string) ($s['survey_date'] ?? $minDate), 0, 10);
            $daysSinceStart = (strtotime($sDate) - $minTimestamp) / 86400;

            $row = [1.0]; // Intercept

            // Category dummies
            foreach ($categoryCounts as $catName => $count) {
                if ($catName === $refCategory) {
                    continue;
                }
                $row[] = ($cat === $catName) ? 1.0 : 0.0;
            }

            // Wave dummies
            foreach ($waveCounts as $waveName => $count) {
                if ($waveName === $refWave) {
                    continue;
                }
                $row[] = ($wave === $waveName) ? 1.0 : 0.0;
            }

            // Supervisor dummies
            foreach ($activeSups as $supName => $count) {
                if ($supName === $refSupervisor) {
                    continue;
                }
                $row[] = ($sup === $supName) ? 1.0 : 0.0;
            }

            // Tenure scaled
            $row[] = $tenure / 100.0;

            // Time scaled
            $row[] = $daysSinceStart / 30.0;

            $X[] = $row;
            $y[] = (float) $s['nps_score'];
        }

        // 4. Solve Normal Equations (X^T X) beta = X^T y
        $XT = MatrixHelper::transpose($X);
        $XTX = MatrixHelper::multiply($XT, $X);
        $XTy = MatrixHelper::multiplyVector($XT, $y);

        $invXTX = MatrixHelper::invert($XTX, 1e-6);
        if (! $invXTX) {
            return [
                'status' => 'SINGULAR_MATRIX',
                'sample_size' => $n,
                'drivers' => [],
                'diagnostics' => ['reason' => 'La matriz de datos presenta colinealidad estricta o rango insuficiente.'],
            ];
        }

        $beta = MatrixHelper::multiplyVector($invXTX, $XTy);

        // 5. Calculate Residuals, Errors, R2, t-stats
        $fitted = MatrixHelper::multiplyVector($X, $beta);
        $meanY = array_sum($y) / $n;
        $sse = 0.0;
        $sst = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $err = $y[$i] - $fitted[$i];
            $sse += ($err * $err);
            $sst += pow($y[$i] - $meanY, 2);
        }

        $df = max(1, $n - $p);
        $residualVariance = $sse / $df;
        $r2 = $sst > 1e-7 ? max(0.0, min(1.0, 1.0 - ($sse / $sst))) : 0.0;

        // 6. Build Structured Driver Results
        $drivers = [];
        for ($j = 1; $j < $p; $j++) {
            $meta = $columnMetadata[$j];
            $coef = $beta[$j];
            $varJ = $residualVariance * max(1e-8, $invXTX[$j][$j]);
            $se = sqrt($varJ);
            $tStat = $se > 1e-7 ? $coef / $se : 0.0;
            $pVal = $this->approximatePValue(abs($tStat), $df);

            $sampleCount = $meta['sample_size'];

            // Sample classification rule
            if ($sampleCount < 10) {
                $confidence = 'INSUFFICIENT';
            } elseif ($sampleCount < 30) {
                $confidence = 'LOW';
            } elseif ($pVal > 0.08) {
                $confidence = 'MODERATE';
            } else {
                $confidence = 'SUPPORTED';
            }

            $direction = $coef > 0.01 ? 'Positive' : ($coef < -0.01 ? 'Negative' : 'Neutral');

            $drivers[] = [
                'driver' => $meta['name'],
                'variable_group' => $meta['variable'] ?? 'factor',
                'label' => $meta['label'] ?? $meta['name'],
                'estimated_effect' => round($coef, 3),
                'direction' => $direction,
                'sample_size' => $sampleCount,
                'confidence' => $confidence,
                'standard_error' => round($se, 4),
                't_statistic' => round($tStat, 2),
                'p_value' => round($pVal, 4),
                'reference_category' => $meta['reference'] ?? null,
                'ci_lower' => round($coef - (1.96 * $se), 3),
                'ci_upper' => round($coef + (1.96 * $se), 3),
                'explanation' => $this->buildNpsDriverExplanation($meta, $coef, $confidence),
            ];
        }

        // Sort drivers by absolute effect magnitude descending
        usort($drivers, fn ($a, $b) => abs($b['estimated_effect']) <=> abs($a['estimated_effect']));

        return [
            'status' => 'COMPLETED',
            'target_metric' => 'nps',
            'sample_size' => $n,
            'controlled_variables' => ['category', 'tenure_days', 'wave', 'supervisor', 'time'],
            'reference_categories' => [
                'category' => $refCategory,
                'wave' => $refWave,
                'supervisor' => $refSupervisor,
            ],
            'drivers' => $drivers,
            'diagnostics' => [
                'model_type' => 'Multiple Linear Regression (OLS)',
                'r_squared' => round($r2, 4),
                'residual_variance' => round($residualVariance, 4),
                'degrees_of_freedom' => $df,
                'parameters_count' => $p,
                'intercept' => round($beta[0], 4),
            ],
        ];
    }

    protected function buildNpsDriverExplanation(array $meta, float $coef, string $confidence): string
    {
        $effectStr = ($coef >= 0 ? '+' : '').round($coef, 2);
        $name = $meta['name'];
        $ref = $meta['reference'] ?? 'línea base';

        if ($confidence === 'INSUFFICIENT') {
            return "{$name} registra muy pocas observaciones ({$meta['sample_size']}). Su efecto aparente de {$effectStr} no es representativo.";
        }

        return "{$name} se asocia con un cambio estimado de {$effectStr} en NPS respecto a {$ref}, controlando por las demás variables operativas.";
    }

    protected function approximatePValue(float $t, int $df): float
    {
        // Standard normal / asymptotic approximation of two-tailed p-value
        // Abramowitz and Stegun formula 7.1.26
        $x = $t / sqrt(2.0);
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
