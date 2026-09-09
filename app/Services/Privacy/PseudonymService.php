<?php

namespace App\Services\Privacy;

use App\Models\PseudonymVault;
use Illuminate\Support\Str;

class PseudonymService
{
    /**
     * Get or create an opaque cryptographically random pseudonym for an entity in a given scope.
     */
    public function getOrCreatePseudonym(string $scopeId, string $entityType, string $entityInternalId): string
    {
        $existing = PseudonymVault::where('scope_id', $scopeId)
            ->where('entity_type', $entityType)
            ->where('entity_internal_id', $entityInternalId)
            ->first();

        if ($existing) {
            return $existing->pseudonym;
        }

        // Generate non-sequential random token with prefix
        $prefix = match (strtolower($entityType)) {
            'agent', 'agent_bms', 'agent_name' => 'AGT',
            'supervisor' => 'SUP',
            'survey', 'record' => 'REC',
            default => 'ENT',
        };

        // Cryptographically random 6-character alphanumeric uppercase token
        do {
            $random = strtoupper(Str::random(6));
            $candidate = "{$prefix}_{$random}";
            $exists = PseudonymVault::where('scope_id', $scopeId)
                ->where('pseudonym', $candidate)
                ->exists();
        } while ($exists);

        PseudonymVault::create([
            'scope_id' => $scopeId,
            'entity_type' => $entityType,
            'entity_internal_id' => $entityInternalId,
            'pseudonym' => $candidate,
            'created_at' => now(),
        ]);

        return $candidate;
    }

    /**
     * Resolve a pseudonym to its internal entity ID within a scope.
     */
    public function resolveToInternalId(string $scopeId, string $pseudonym): ?string
    {
        $entry = PseudonymVault::where('scope_id', $scopeId)
            ->where('pseudonym', $pseudonym)
            ->first();

        return $entry ? $entry->entity_internal_id : null;
    }

    /**
     * Resolve all pseudonyms in a given scope back to their internal IDs.
     */
    public function getMappingForScope(string $scopeId): array
    {
        return PseudonymVault::where('scope_id', $scopeId)
            ->get()
            ->pluck('entity_internal_id', 'pseudonym')
            ->toArray();
    }
}
