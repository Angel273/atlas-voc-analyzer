<?php

namespace App\Models;

use Database\Factories\PerformanceCaseUpdateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceCaseUpdate extends Model
{
    /** @use HasFactory<PerformanceCaseUpdateFactory> */
    use HasFactory;

    protected $fillable = [
        'performance_case_id',
        'created_by_user_id',
        'previous_status',
        'resulting_status',
        'summary',
        'observations',
        'actions',
        'commitments',
        'next_review_at',
        'metrics_snapshot',
        'disciplinary_details',
    ];

    /**
     * Disciplinary details must be hidden by default from general listings and serialized payloads.
     */
    protected $hidden = [
        'disciplinary_details',
    ];

    protected function casts(): array
    {
        return [
            'next_review_at' => 'date',
            'metrics_snapshot' => 'array',
            'disciplinary_details' => 'encrypted',
        ];
    }

    public function performanceCase(): BelongsTo
    {
        return $this->belongsTo(PerformanceCase::class, 'performance_case_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
