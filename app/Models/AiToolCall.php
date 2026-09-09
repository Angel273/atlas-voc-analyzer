<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolCall extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'ai_run_id',
        'tool_name',
        'tool_version',
        'arguments_sanitized',
        'query_dsl',
        'result_summary',
        'status',
        'duration_ms',
        'citations',
        'requested_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments_sanitized' => 'array',
            'query_dsl' => 'array',
            'duration_ms' => 'integer',
            'citations' => 'array',
            'requested_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function aiRun(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'ai_run_id');
    }
}
