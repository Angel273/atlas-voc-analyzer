<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Forecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric',
        'dimension',
        'dimension_value',
        'model',
        'status',
        'reliability',
        'selection_reason',
        'parameters',
        'training_period_start',
        'training_period_end',
        'forecast_horizon',
        'mae',
        'rmse',
        'r2',
        'ai_interpretation',
        'generated_by',
    ];

    protected $appends = [
        'historical_points',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'training_period_start' => 'date:Y-m-d',
            'training_period_end' => 'date:Y-m-d',
            'forecast_horizon' => 'integer',
            'mae' => 'float',
            'rmse' => 'float',
            'r2' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ForecastResult::class)->orderBy('date', 'asc');
    }

    public function getHistoricalPointsAttribute(): array
    {
        if (! empty($this->parameters['historical_points'])) {
            return $this->parameters['historical_points'];
        }

        if (! $this->training_period_start || ! $this->training_period_end) {
            return [];
        }

        $sourceCol = match ($this->metric) {
            'csat' => 'csat_score',
            'professionalism' => 'professionalism_score',
            default => 'nps_score',
        };

        $query = DB::table('surveys')
            ->whereDate('survey_date', '>=', $this->training_period_start->format('Y-m-d'))
            ->whereDate('survey_date', '<=', $this->training_period_end->format('Y-m-d'));

        if ($this->dimension && $this->dimension_value) {
            $col = match ($this->dimension) {
                'supervisor' => 'supervisor',
                'agent', 'agent_bms' => 'agent_bms',
                'wave' => 'wave',
                default => $this->dimension,
            };
            $query->where($col, $this->dimension_value);
        }

        if (in_array($this->metric, ['csat', 'professionalism'], true)) {
            $metricExpr = "AVG(CASE WHEN {$sourceCol} = 1 THEN 1.0 WHEN {$sourceCol} > 0 THEN {$sourceCol} ELSE 0.0 END)";
        } else {
            $metricExpr = "AVG({$sourceCol})";
        }

        return $query
            ->selectRaw("DATE(survey_date) as date, {$metricExpr} as value, COUNT(*) as sample_count")
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->date,
                'value' => round((float) $r->value, 4),
                'sample_count' => (int) $r->sample_count,
            ])
            ->toArray();
    }
}
