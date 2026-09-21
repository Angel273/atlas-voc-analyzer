<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'nps_score',
        'csat_score',
        'professionalism_score',
        'verbatim',
        'agent_bms',
        'agent_name',
        'agent_id',
        'supervisor',
        'supervisor_id',
        'team_id',
        'survey_date',
        'wave',
        'tenure_days',
        'record_hash',
        'import_id',
    ];

    protected function casts(): array
    {
        return [
            'nps_score' => 'float',
            'csat_score' => 'float',
            'professionalism_score' => 'float',
            'survey_date' => 'date',
            'tenure_days' => 'integer',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(WorkforceMember::class, 'agent_id');
    }

    public function supervisorMember(): BelongsTo
    {
        return $this->belongsTo(WorkforceMember::class, 'supervisor_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function verbatimAnalysis(): HasOne
    {
        return $this->hasOne(VerbatimAnalysis::class, 'survey_id', 'survey_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SurveyVersion::class, 'survey_id', 'survey_id');
    }

    /**
     * Compute a canonical deterministic hash for idempotency and change tracking.
     */
    public static function computeHash(array $attributes): string
    {
        $normalized = [
            'survey_id' => (string) ($attributes['survey_id'] ?? ''),
            'nps_score' => number_format((float) ($attributes['nps_score'] ?? 0), 4, '.', ''),
            'csat_score' => number_format((float) ($attributes['csat_score'] ?? 0), 4, '.', ''),
            'professionalism_score' => number_format((float) ($attributes['professionalism_score'] ?? 0), 4, '.', ''),
            'verbatim' => trim((string) ($attributes['verbatim'] ?? '')),
            'agent_bms' => trim((string) ($attributes['agent_bms'] ?? '')),
            'agent_name' => trim((string) ($attributes['agent_name'] ?? '')),
            'supervisor' => trim((string) ($attributes['supervisor'] ?? '')),
            'survey_date' => substr((string) ($attributes['survey_date'] ?? ''), 0, 10),
            'wave' => trim((string) ($attributes['wave'] ?? '')),
            'tenure_days' => isset($attributes['tenure_days']) && $attributes['tenure_days'] !== '' && $attributes['tenure_days'] !== null ? (int) $attributes['tenure_days'] : null,
        ];

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE));
    }
}
