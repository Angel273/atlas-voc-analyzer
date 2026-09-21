<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseDailyResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_metric_recalculation_id',
        'performance_case_id',
        'date',
        'nps_score',
        'csat_score',
        'professionalism_score',
        'survey_volume',
        'has_sample',
        'applicable_goals',
        'effective_team_id',
        'effective_team_name',
        'effective_supervisor_id',
        'effective_supervisor_name',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'nps_score' => 'float',
            'csat_score' => 'float',
            'professionalism_score' => 'float',
            'survey_volume' => 'integer',
            'has_sample' => 'boolean',
            'applicable_goals' => 'array',
        ];
    }

    public function recalculation(): BelongsTo
    {
        return $this->belongsTo(CaseMetricRecalculation::class, 'case_metric_recalculation_id');
    }

    public function performanceCase(): BelongsTo
    {
        return $this->belongsTo(PerformanceCase::class, 'performance_case_id');
    }

    public function effectiveTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'effective_team_id');
    }

    public function effectiveSupervisor(): BelongsTo
    {
        return $this->belongsTo(WorkforceMember::class, 'effective_supervisor_id');
    }
}
