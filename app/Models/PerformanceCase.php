<?php

namespace App\Models;

use Database\Factories\PerformanceCaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PerformanceCase extends Model
{
    /** @use HasFactory<PerformanceCaseFactory> */
    use HasFactory;

    protected $fillable = [
        'case_number',
        'workforce_member_id',
        'target_type',
        'type',
        'reason',
        'priority',
        'status',
        'opened_at',
        'closed_at',
        'assigned_to_user_id',
        'baseline',
        'objectives',
        'next_review_at',
    ];

    protected $appends = [
        'baseline_metrics',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'closed_at' => 'date',
            'next_review_at' => 'date',
            'baseline' => 'array',
            'objectives' => 'array',
        ];
    }

    public function getBaselineMetricsAttribute(): ?array
    {
        if (empty($this->baseline)) {
            return null;
        }

        return [
            'nps_score' => isset($this->baseline['nps']) ? (float) $this->baseline['nps'] : null,
            'csat_score' => isset($this->baseline['csat']) ? (float) $this->baseline['csat'] : null,
            'professionalism_score' => isset($this->baseline['professionalism']) ? (float) $this->baseline['professionalism'] : null,
            'survey_volume' => (int) ($this->baseline['volume'] ?? 0),
            'period_from' => $this->baseline['period_from'] ?? null,
            'period_to' => $this->baseline['period_to'] ?? null,
        ];
    }

    public function workforceMember(): BelongsTo
    {
        return $this->belongsTo(WorkforceMember::class, 'workforce_member_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PerformanceCaseUpdate::class)->orderBy('created_at', 'desc');
    }

    public function recalculations(): HasMany
    {
        return $this->hasMany(CaseMetricRecalculation::class)->orderBy('version_number', 'desc');
    }

    public function latestRecalculation(): HasOne
    {
        return $this->hasOne(CaseMetricRecalculation::class)->latestOfMany('version_number');
    }

    public function dailyResults(): HasMany
    {
        return $this->hasMany(CaseDailyResult::class);
    }

    public static function generateCaseNumber(): string
    {
        $year = now()->format('Y');
        $count = static::whereYear('created_at', now()->year)->count() + 1;
        $candidate = sprintf('CAS-%s-%04d', $year, $count);

        while (static::where('case_number', $candidate)->exists()) {
            $count++;
            $candidate = sprintf('CAS-%s-%04d', $year, $count);
        }

        return $candidate;
    }
}
