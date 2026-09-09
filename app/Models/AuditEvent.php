<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'auditable_type',
        'auditable_id',
        'payload',
        'metadata',
        'previous_hash',
        'event_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Compute a tamper-evident SHA-256 hash chaining to previous hash.
     */
    public static function computeEventHash(?string $previousHash, string $eventType, ?int $userId, array $payload, ?array $metadata, string $createdAt): string
    {
        $canonical = [
            'previous_hash' => $previousHash ?? '',
            'event_type' => $eventType,
            'user_id' => $userId,
            'payload' => $payload,
            'metadata' => $metadata ?? [],
            'created_at' => $createdAt,
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
