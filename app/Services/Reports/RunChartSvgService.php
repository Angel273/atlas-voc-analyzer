<?php

namespace App\Services\Reports;

use Carbon\Carbon;

class RunChartSvgService
{
    /**
     * Render a vector SVG run chart showing daily evolution of NPS, CSAT, and survey volume.
     *
     * @param  array<int, array{date: string, volume: int, nps: float|null, csat: float|null, professionalism?: float|null}>  $trends
     */
    public static function render(
        array $trends,
        float $npsTarget = 0.50,
        float $csatTarget = 0.80,
        int $width = 520,
        int $height = 115,
        bool $isAgent = false
    ): string {
        if (empty($trends)) {
            return self::renderEmptyState($width, $height);
        }

        // Sort trends chronologically
        usort($trends, fn ($a, $b) => strcmp($a['date'], $b['date']));

        $hasNegative = false;
        $maxVol = 1;
        foreach ($trends as $t) {
            if (($t['nps'] ?? null) !== null && (float) $t['nps'] < -0.01) {
                $hasNegative = true;
            }
            $v = (int) ($t['volume'] ?? 0);
            if ($v > $maxVol) {
                $maxVol = $v;
            }
        }

        $minY = $hasNegative ? -1.0 : 0.0;
        $maxY = 1.0;
        $rangeY = $maxY - $minY; // 2.0 or 1.0

        $pL = 36;
        $pR = 16;
        $pT = 18;
        $pB = $isAgent ? 18 : 22;
        $plotW = max(50, $width - $pL - $pR);
        $plotH = max(30, $height - $pT - $pB);

        $n = count($trends);

        // Target reference lines
        $npsTargetClamped = min($maxY, max($minY, $npsTarget));
        $targetY = $pT + (1.0 - ($npsTargetClamped - $minY) / $rangeY) * $plotH;

        $zeroY = null;
        if ($hasNegative) {
            $zeroY = $pT + (1.0 - (0.0 - $minY) / $rangeY) * $plotH;
        }

        // Grid lines HTML
        $gridLines = '';
        $gridLines .= sprintf(
            '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#16a34a" stroke-width="0.8" stroke-dasharray="3,2"/>',
            $pL,
            (int) round($targetY),
            $pL + $plotW,
            (int) round($targetY)
        );

        if (! $isAgent) {
            $gridLines .= sprintf(
                '<text x="%d" y="%d" font-size="5.5" font-family="Helvetica, Arial, sans-serif" fill="#16a34a" font-weight="bold">Meta NPS: %+.2f</text>',
                $pL + 3,
                (int) round(max($pT + 7, $targetY - 2)),
                $npsTarget
            );
        }

        if ($zeroY !== null) {
            $gridLines .= sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#94a3b8" stroke-width="0.7"/>',
                $pL,
                (int) round($zeroY),
                $pL + $plotW,
                (int) round($zeroY)
            );
        }

        // Y-axis labels
        $yLabels = '';
        $topLabel = $hasNegative ? '+1.0' : '100%';
        $yLabels .= sprintf(
            '<text x="%d" y="%d" font-size="6" font-family="Helvetica, Arial, sans-serif" text-anchor="end" fill="#64748b">%s</text>',
            $pL - 4,
            $pT + 4,
            $topLabel
        );

        if ($hasNegative && $zeroY !== null) {
            $yLabels .= sprintf(
                '<text x="%d" y="%d" font-size="6" font-family="Helvetica, Arial, sans-serif" text-anchor="end" fill="#64748b">0.0</text>',
                $pL - 4,
                (int) round($zeroY + 2)
            );
            $yLabels .= sprintf(
                '<text x="%d" y="%d" font-size="6" font-family="Helvetica, Arial, sans-serif" text-anchor="end" fill="#64748b">-1.0</text>',
                $pL - 4,
                $pT + $plotH + 2
            );
        } else {
            $midY = (int) round($pT + $plotH * 0.5);
            $yLabels .= sprintf(
                '<text x="%d" y="%d" font-size="6" font-family="Helvetica, Arial, sans-serif" text-anchor="end" fill="#64748b">50%%</text>',
                $pL - 4,
                $midY + 2
            );
            $yLabels .= sprintf(
                '<text x="%d" y="%d" font-size="6" font-family="Helvetica, Arial, sans-serif" text-anchor="end" fill="#64748b">0%%</text>',
                $pL - 4,
                $pT + $plotH + 2
            );
        }

        // Compute points, bars, and X labels
        $npsPoints = [];
        $csatPoints = [];
        $circles = '';
        $bars = '';
        $xLabels = '';

        // Step for dates if too many
        $dateStep = 1;
        if ($n > 16) {
            $dateStep = 3;
        } elseif ($n > 9) {
            $dateStep = 2;
        }

        foreach ($trends as $i => $t) {
            $x = ($n === 1) ? ($pL + $plotW / 2) : ($pL + ($i / ($n - 1)) * $plotW);

            // Volume bar
            $vol = (int) ($t['volume'] ?? 0);
            $barH = $maxVol > 0 ? ($vol / $maxVol) * ($plotH * 0.32) : 0;
            $barY = $pT + $plotH - $barH;
            $barW = max(3, min(12, ($plotW / max(1, $n)) * 0.45));

            $bars .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="0.5" rx="1"/>',
                $x - $barW / 2,
                $barY,
                $barW,
                $barH
            );

            if ($n <= 10 && $vol > 0 && ! $isAgent) {
                $bars .= sprintf(
                    '<text x="%.1f" y="%.1f" font-size="5" font-family="Helvetica, Arial, sans-serif" text-anchor="middle" fill="#94a3b8">%d</text>',
                    $x,
                    max($pT + 5, $barY - 2),
                    $vol
                );
            }

            // X-axis date label
            $isLast = ($i === $n - 1);
            if ($i % $dateStep === 0 || $isLast) {
                try {
                    $dtObj = Carbon::parse($t['date']);
                    $dateLabel = $dtObj->format('d/m');
                } catch (\Throwable) {
                    $dateLabel = substr((string) $t['date'], 5);
                }
                $xLabels .= sprintf(
                    '<text x="%.1f" y="%d" font-size="6" font-family="Helvetica, Arial, sans-serif" text-anchor="middle" fill="#64748b">%s</text>',
                    $x,
                    $pT + $plotH + 11,
                    $dateLabel
                );
            }

            // NPS Point
            if (($t['nps'] ?? null) !== null) {
                $npsVal = (float) $t['nps'];
                $npsClamped = min($maxY, max($minY, $npsVal));
                $ny = $pT + (1.0 - ($npsClamped - $minY) / $rangeY) * $plotH;
                $npsPoints[] = sprintf('%.1f,%.1f', $x, $ny);
                $circles .= sprintf(
                    '<rect x="%.1f" y="%.1f" width="4" height="4" rx="2" ry="2" fill="#166534" stroke="#ffffff" stroke-width="0.6"/>',
                    $x - 2.0,
                    $ny - 2.0
                );
            }

            // CSAT Point
            if (($t['csat'] ?? null) !== null) {
                $csatVal = (float) $t['csat'];
                $csatClamped = min($maxY, max($minY, $csatVal));
                $cy = $pT + (1.0 - ($csatClamped - $minY) / $rangeY) * $plotH;
                $csatPoints[] = sprintf('%.1f,%.1f', $x, $cy);
                $circles .= sprintf(
                    '<rect x="%.1f" y="%.1f" width="3.6" height="3.6" rx="1.8" ry="1.8" fill="#0284c7" stroke="#ffffff" stroke-width="0.6"/>',
                    $x - 1.8,
                    $cy - 1.8
                );
            }
        }

        // Connecting polylines
        $polylines = '';
        if (count($npsPoints) > 1) {
            $polylines .= sprintf(
                '<polyline points="%s" fill="none" stroke="#166534" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
                implode(' ', $npsPoints)
            );
        }
        if (count($csatPoints) > 1) {
            $polylines .= sprintf(
                '<polyline points="%s" fill="none" stroke="#0284c7" stroke-width="1.3" stroke-dasharray="4,1.5" stroke-linecap="round" stroke-linejoin="round"/>',
                implode(' ', $csatPoints)
            );
        }

        // Header / Legend
        $legend = '';
        $legendW = 150;
        $startX = max($pL + 60, $width - $pR - $legendW);
        $legend .= sprintf(
            '<g font-size="6" font-family="Helvetica, Arial, sans-serif">'
            .'<line x1="%d" y1="9" x2="%d" y2="9" stroke="#166534" stroke-width="1.8"/>'
            .'<rect x="%.1f" y="7" width="4" height="4" rx="2" ry="2" fill="#166534" stroke="#ffffff" stroke-width="0.5"/>'
            .'<text x="%d" y="11" fill="#166534" font-weight="bold">NPS</text>'
            .'<line x1="%d" y1="9" x2="%d" y2="9" stroke="#0284c7" stroke-width="1.3" stroke-dasharray="3,1.5"/>'
            .'<rect x="%.1f" y="7" width="4" height="4" rx="2" ry="2" fill="#0284c7" stroke="#ffffff" stroke-width="0.5"/>'
            .'<text x="%d" y="11" fill="#0284c7" font-weight="bold">CSAT</text>'
            .'<rect x="%d" y="6" width="8" height="6" fill="#e2e8f0" stroke="#cbd5e1" stroke-width="0.5" rx="1" ry="1"/>'
            .'<text x="%d" y="11" fill="#64748b">Encuestas (Vol)</text>'
            .'</g>',
            $startX, $startX + 14,
            $startX + 5.0,
            $startX + 18,
            $startX + 40, $startX + 54,
            $startX + 45.0,
            $startX + 58,
            $startX + 85,
            $startX + 97
        );

        return sprintf(
            '<svg viewBox="0 0 %d %d" width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">'
            .'<rect x="%d" y="%d" width="%d" height="%d" fill="#f8fafc" stroke="#e2e8f0" stroke-width="0.5" rx="3"/>'
            .'%s%s%s%s%s%s%s'
            .'</svg>',
            $width,
            $height,
            $width,
            $height,
            $pL,
            $pT,
            $plotW,
            $plotH,
            $legend,
            $gridLines,
            $bars,
            $polylines,
            $circles,
            $yLabels,
            $xLabels
        );
    }

    /**
     * Render the run chart as an inline HTML <img> tag with a base64-encoded SVG data URI.
     * Required for DomPDF which only renders SVGs when embedded inside an <img> tag.
     *
     * @param  array<int, array{date: string, volume: int, nps: float|null, csat: float|null, professionalism?: float|null}>  $trends
     */
    public static function renderImg(
        array $trends,
        float $npsTarget = 0.50,
        float $csatTarget = 0.80,
        int $width = 520,
        int $height = 110,
        bool $isAgent = false,
        string $extraStyle = ''
    ): string {
        $svg = self::render($trends, $npsTarget, $csatTarget, $width, $height, $isAgent);
        $base64 = base64_encode($svg);
        $dataUri = 'data:image/svg+xml;base64,'.$base64;

        return sprintf(
            '<img src="%s" width="%d" height="%d" style="width: 100%%; max-width: %dpx; height: %dpx; display: block; margin: 0 auto; %s" />',
            $dataUri,
            $width,
            $height,
            $width,
            $height,
            $extraStyle
        );
    }

    /**
     * Render a neat empty state SVG when no trend data exists.
     */
    protected static function renderEmptyState(int $width, int $height): string
    {
        return sprintf(
            '<svg viewBox="0 0 %d %d" width="%d" height="%d" xmlns="http://www.w3.org/2000/svg">'
            .'<rect x="20" y="15" width="%d" height="%d" fill="#f8fafc" stroke="#e2e8f0" stroke-width="0.5" stroke-dasharray="3,2" rx="3"/>'
            .'<text x="%d" y="%d" font-size="7.5" font-family="Helvetica, Arial, sans-serif" text-anchor="middle" fill="#94a3b8">Sin registros diarios suficientes para graficar la secuencia temporal</text>'
            .'</svg>',
            $width,
            $height,
            $width,
            $height,
            $width - 40,
            $height - 30,
            (int) round($width / 2),
            (int) round($height / 2 + 2)
        );
    }
}
