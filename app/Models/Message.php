<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'conversation_id',
        'sender_type',
        'display_content',
        'ai_content',
        'grounding_context',
        'tokens_used',
        'tool_calls',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'grounding_context' => 'array',
            'tokens_used' => 'integer',
            'tool_calls' => 'array',
            'metadata' => 'array',
        ];
    }

    public function getGroundingContextAttribute(?string $value): array
    {
        if ($value !== null) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $this->metadata['grounding_context'] ?? [];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
