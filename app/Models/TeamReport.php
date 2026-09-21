<?php

namespace App\Models;

use Database\Factories\TeamReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamReport extends Model
{
    /** @use HasFactory<TeamReportFactory> */
    use HasFactory;

    protected $fillable = [
        'team_id',
        'period_from',
        'period_to',
        'cutoff_date',
        'data_version',
        'prompt_version',
        'model',
        'parameters',
        'status',
        'progress',
        'stage',
        'file_path',
        'file_hash',
        'file_size',
        'created_by_user_id',
        'tokens_used',
        'error_message',
        'narrative',
        'metrics_data',
        'previous_report_id',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'cutoff_date' => 'date',
            'parameters' => 'array',
            'progress' => 'integer',
            'narrative' => 'array',
            'metrics_data' => 'array',
            'tokens_used' => 'integer',
            'file_size' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function previousReport(): BelongsTo
    {
        return $this->belongsTo(TeamReport::class, 'previous_report_id');
    }

    public function regenerations(): HasMany
    {
        return $this->hasMany(TeamReport::class, 'previous_report_id');
    }
}
