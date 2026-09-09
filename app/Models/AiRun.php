<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiRun extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'conversation_id',
        'user_prompt',
        'user_id',
        'provider',
        'model',
        'prompt_version',
        'tool_schema_version',
        'privacy_policy_version',
        'sanitized_payload',
        'status',
        'tokens_used',
        'latency_ms',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sanitized_payload' => 'array',
            'tokens_used' => 'integer',
            'latency_ms' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class, 'ai_run_id')->orderBy('requested_at', 'asc');
    }
}
