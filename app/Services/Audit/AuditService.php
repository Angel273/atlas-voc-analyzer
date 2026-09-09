<?php

namespace App\Services\Audit;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Record an audit event with chained cryptographic hashing.
     */
    public function record(
        string $eventType,
        array $payload,
        ?string $auditableType = null,
        ?string $auditableId = null,
        ?array $metadata = null,
        ?int $userId = null
    ): AuditEvent {
        $userId = $userId ?? Auth::id();
        $createdAt = now()->format('Y-m-d H:i:s');

        // Get the hash of the latest event in the chain
        $latestEvent = AuditEvent::orderBy('id', 'desc')->first();
        $previousHash = $latestEvent ? $latestEvent->event_hash : null;

        $eventHash = AuditEvent::computeEventHash(
            $previousHash,
            $eventType,
            $userId,
            $payload,
            $metadata,
            $createdAt
        );

        return AuditEvent::create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'payload' => $payload,
            'metadata' => $metadata,
            'previous_hash' => $previousHash,
            'event_hash' => $eventHash,
            'created_at' => $createdAt,
        ]);
    }

    /**
     * Verify the tamper-evident integrity of the entire audit event chain.
     */
    public function verifyChainIntegrity(): array
    {
        $events = AuditEvent::orderBy('id', 'asc')->get();
        $tamperedEvents = [];
        $expectedPreviousHash = null;

        foreach ($events as $index => $event) {
            if ($event->previous_hash !== $expectedPreviousHash) {
                $tamperedEvents[] = [
                    'id' => $event->id,
                    'reason' => 'previous_hash mismatch',
                    'expected' => $expectedPreviousHash,
                    'actual' => $event->previous_hash,
                ];
            }

            $dateStr = $event->created_at instanceof \DateTimeInterface
                ? $event->created_at->format('Y-m-d H:i:s')
                : (string) $event->created_at;

            $recomputed = AuditEvent::computeEventHash(
                $event->previous_hash,
                $event->event_type,
                $event->user_id,
                $event->payload,
                $event->metadata,
                $dateStr
            );

            if ($recomputed !== $event->event_hash) {
                $tamperedEvents[] = [
                    'id' => $event->id,
                    'reason' => 'event_hash payload mismatch',
                    'expected' => $recomputed,
                    'actual' => $event->event_hash,
                ];
            }

            $expectedPreviousHash = $event->event_hash;
        }

        return [
            'valid' => count($tamperedEvents) === 0,
            'total_events' => $events->count(),
            'tampered_count' => count($tamperedEvents),
            'tampered_events' => $tamperedEvents,
        ];
    }
}
