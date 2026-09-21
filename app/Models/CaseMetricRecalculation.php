<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseMetricRecalculation extends Model
{
    use HasFactory;

    protected $fillable = [
        'performance_case_id',
        'version_number',
        'recalculated_at',
        'triggered_by_user_id',
        'reason',
        'notes',
        'metadata',
    ];

    protected $appends = [
        'period_from',
        'period_to',
        'survey_volume',
        'nps_score',
        'csat_score',
        'professionalism_score',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'recalculated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function getPeriodFromAttribute(): ?string
    {
        if (! empty($this->metadata['from_date'])) {
            return (string) $this->metadata['from_date'];
        }

        $firstDay = $this->relationLoaded('dailyResults')
            ? $this->dailyResults->first()
            : $this->dailyResults()->orderBy('date', 'asc')->first();

        return $firstDay?->date?->toDateString() ?? $this->performanceCase?->opened_at?->toDateString();
    }

    public function getPeriodToAttribute(): ?string
    {
        if (! empty($this->metadata['to_date'])) {
            return (string) $this->metadata['to_date'];
        }

        $lastDay = $this->relationLoaded('dailyResults')
            ? $this->dailyResults->last()
            : $this->dailyResults()->orderBy('date', 'desc')->first();

        return $lastDay?->date?->toDateString() ?? now()->toDateString();
    }

    public function getSurveyVolumeAttribute(): int
    {
        if (isset($this->metadata['survey_volume'])) {
            return (int) $this->metadata['survey_volume'];
        }

        return (int) ($this->relationLoaded('dailyResults')
            ? $this->dailyResults->sum('survey_volume')
            : $this->dailyResults()->sum('survey_volume'));
    }

    public function getNpsScoreAttribute(): ?float
    {
        if (isset($this->metadata['nps_score'])) {
            return $this->metadata['nps_score'] !== null ? (float) $this->metadata['nps_score'] : null;
        }

        $results = $this->relationLoaded('dailyResults') ? $this->dailyResults : $this->dailyResults()->get();
        $sampled = $results->where('has_sample', true)->where('survey_volume', '>', 0);
        $totalVolume = (int) $sampled->sum('survey_volume');

        if ($totalVolume === 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($sampled as $row) {
            if ($row->nps_score !== null) {
                $sum += ((float) $row->nps_score) * $row->survey_volume;
            }
        }

        return round($sum / $totalVolume, 4);
    }

    public function getCsatScoreAttribute(): ?float
    {
        if (isset($this->metadata['csat_score'])) {
            return $this->metadata['csat_score'] !== null ? (float) $this->metadata['csat_score'] : null;
        }

        $results = $this->relationLoaded('dailyResults') ? $this->dailyResults : $this->dailyResults()->get();
        $sampled = $results->where('has_sample', true)->where('survey_volume', '>', 0);
        $totalVolume = (int) $sampled->sum('survey_volume');

        if ($totalVolume === 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($sampled as $row) {
            if ($row->csat_score !== null) {
                $sum += ((float) $row->csat_score) * $row->survey_volume;
            }
        }

        return round($sum / $totalVolume, 4);
    }

    public function getProfessionalismScoreAttribute(): ?float
    {
        if (isset($this->metadata['professionalism_score'])) {
            return $this->metadata['professionalism_score'] !== null ? (float) $this->metadata['professionalism_score'] : null;
        }

        $results = $this->relationLoaded('dailyResults') ? $this->dailyResults : $this->dailyResults()->get();
        $sampled = $results->where('has_sample', true)->where('survey_volume', '>', 0);
        $totalVolume = (int) $sampled->sum('survey_volume');

        if ($totalVolume === 0) {
            return null;
        }

        $sum = 0.0;
        foreach ($sampled as $row) {
            if ($row->professionalism_score !== null) {
                $sum += ((float) $row->professionalism_score) * $row->survey_volume;
            }
        }

        return round($sum / $totalVolume, 4);
    }

    public function performanceCase(): BelongsTo
    {
        return $this->belongsTo(PerformanceCase::class, 'performance_case_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function dailyResults(): HasMany
    {
        return $this->hasMany(CaseDailyResult::class)->orderBy('date', 'asc');
    }
}
