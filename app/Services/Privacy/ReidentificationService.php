<?php

namespace App\Services\Privacy;

use App\Models\User;

class ReidentificationService
{
    public function __construct(
        protected PseudonymService $pseudonymService
    ) {}

    /**
     * Conditionally re-identify pseudonyms in AI response based on user permission.
     */
    public function resolveResponse(string $aiText, string $scopeId, ?User $user = null): string
    {
        // If user is not authenticated or lacks 'identity.view', retain pseudonyms
        if (!$user || !$user->hasPermission('identity.view')) {
            return $aiText;
        }

        $mapping = $this->pseudonymService->getMappingForScope($scopeId);
        if (empty($mapping)) {
            return $aiText;
        }

        // Replace each pseudonym with the real identity
        $resolved = $aiText;
        foreach ($mapping as $pseudonym => $realIdentity) {
            $pattern = '/\b' . preg_quote($pseudonym, '/') . '\b/';
            $resolved = preg_replace($pattern, $realIdentity, $resolved);
        }

        return $resolved;
    }
}
