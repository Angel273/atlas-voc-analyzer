<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric',
        'target_value',
        'warning_threshold',
        'unit',
        'description',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'target_value' => 'float',
            'warning_threshold' => 'float',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Get default fallback goals structure.
     * Note: NPS is on the scale -1 to 1 (e.g. 0.50 = +50%).
     */
    public static function getDefaults(): array
    {
        return [
            'nps' => [
                'metric' => 'nps',
                'name' => 'Net Promoter Score (NPS)',
                'target_value' => 0.50,
                'warning_threshold' => 0.20,
                'unit' => 'score',
                'scale' => '-1 a 1',
                'description' => 'Meta de lealtad neta NPS (+0.50 en escala -1 a 1, equivalente a +50%).',
            ],
            'csat' => [
                'metric' => 'csat',
                'name' => 'Customer Satisfaction (CSAT)',
                'target_value' => 0.80,
                'warning_threshold' => 0.70,
                'unit' => 'score',
                'scale' => '-1 a 1 / 0 a 1',
                'description' => 'Meta de satisfacción global CSAT (0.80 en escala de -1 a 1 / 0 a 1, equivalente a 80%).',
            ],
            'professionalism' => [
                'metric' => 'professionalism',
                'name' => 'Professionalism Score',
                'target_value' => 0.85,
                'warning_threshold' => 0.75,
                'unit' => 'score',
                'scale' => '-1 a 1 / 0 a 1',
                'description' => 'Meta de profesionalismo en la atención (0.85 en escala de -1 a 1 / 0 a 1, equivalente a 85%).',
            ],
        ];
    }

    /**
     * Returns an associative map of all goals keyed by metric.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getGoalsMap(): array
    {
        $defaults = static::getDefaults();
        $records = static::all()->keyBy('metric');

        $result = [];
        foreach ($defaults as $key => $default) {
            $record = $records->get($key);
            $target = $record ? (float) $record->target_value : (float) $default['target_value'];
            $warning = $record && $record->warning_threshold !== null
                ? (float) $record->warning_threshold
                : (float) $default['warning_threshold'];

            $result[$key] = [
                'metric' => $key,
                'name' => $default['name'],
                'target_value' => $target,
                'target_percentage' => round($target * 100, 1),
                'warning_threshold' => $warning,
                'warning_percentage' => round($warning * 100, 1),
                'unit' => $record->unit ?? $default['unit'],
                'scale' => $default['scale'],
                'description' => $record->description ?? $default['description'],
                'updated_at' => $record?->updated_at?->toIso8601String(),
            ];
        }

        return $result;
    }

    /**
     * Generate structured context for AI Assistant instruction.
     *
     * @param  array<string, array<string, mixed>>|null  $customGoals
     */
    public static function getFormattedContext(?array $customGoals = null): string
    {
        $defaults = static::getDefaults();
        $goals = $customGoals ?: static::getGoalsMap();

        $lines = [];
        $lines[] = '=== METAS OPERACIONALES Y BENCHMARKS ACTIVOS (KPI GOALS) ===';
        $lines[] = 'Nota fundamental sobre las escalas: Las métricas VOC (NPS, CSAT, Profesionalismo) se calculan en escala decimal de -1.0 a +1.0 (donde 1.0 = 100%, 0.50 = +50%, 0.0 = 0%, -1.0 = -100%).';
        $lines[] = '';

        foreach ($goals as $k => $g) {
            $name = $g['name'] ?? ($defaults[$k]['name'] ?? strtoupper($k));
            $targetVal = (float) ($g['target_value'] ?? ($defaults[$k]['target_value'] ?? 0));
            $targetPct = isset($g['target_percentage']) ? (float) $g['target_percentage'] : round($targetVal * 100, 1);
            $warnVal = isset($g['warning_threshold']) && $g['warning_threshold'] !== null ? (float) $g['warning_threshold'] : (float) ($defaults[$k]['warning_threshold'] ?? 0);
            $warnPct = isset($g['warning_percentage']) ? (float) $g['warning_percentage'] : round($warnVal * 100, 1);
            $desc = $g['description'] ?? ($defaults[$k]['description'] ?? null);

            $targetFmt = sprintf('%+.2f', $targetVal);
            $targetPctStr = sprintf('%+.1f%%', $targetPct);
            $warnFmt = sprintf('%+.2f', $warnVal);
            $warnPctStr = sprintf('%+.1f%%', $warnPct);

            $lines[] = sprintf(
                '- %s: Meta = %s (%s). Umbral de atención = %s (%s). %s',
                $name,
                $targetFmt,
                $targetPctStr,
                $warnFmt,
                $warnPctStr,
                $desc ? "({$desc})" : ''
            );
        }

        $lines[] = '';
        $lines[] = 'DIRECTRICES PARA EL ASISTENTE DE IA FRENTE A LAS METAS:';
        $lines[] = '1. EVALUACIÓN CONTINUA: Cada vez que calcules o analices NPS, CSAT o Profesionalismo (por agente, supervisor, categoría o periodo), compáralo explícitamente con estas metas operacionales vigentes.';
        $lines[] = '2. CALIFICACIÓN OPERATIVA: Describe si el resultado está "Sobre la meta" (>= meta), "En zona de atención" (entre umbral y meta) o "Crítico / Debajo de meta" (< umbral).';
        $lines[] = '3. CÁLCULO DE BRECHA (GAP): Informa la diferencia exacta respecto a la meta (ej. "+0.04 (+4.0%) sobre la meta de CSAT", o "-0.12 (-12.0%) por debajo de la meta de NPS").';
        $lines[] = '4. PLANES DE ACCIÓN: Identifica a los agentes o supervisores que presenten brechas negativas y recomienda acciones correctivas focalizadas.';

        return implode("\n", $lines);
    }
}
