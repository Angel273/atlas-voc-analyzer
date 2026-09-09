<?php

namespace App\Services\Privacy;

use App\Models\Survey;

class PiiScrubberService
{
    public function __construct(
        protected PseudonymService $pseudonymService
    ) {}

    /**
     * Scrub and pseudonymize text before it is sent to AI.
     * Replaces emails, phone numbers, account numbers, and known agent/supervisor identities with scoped pseudonyms.
     */
    public function scrubText(string $text, string $scopeId, array &$redactedMetadata = []): string
    {
        $scrubbed = $text;
        $redactedMetadata = [
            'emails' => 0,
            'phones' => 0,
            'accounts' => 0,
            'agents' => 0,
            'supervisors' => 0,
        ];

        // 1. Scrub Email Addresses
        $emailRegex = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        $scrubbed = preg_replace_callback($emailRegex, function () use (&$redactedMetadata) {
            $redactedMetadata['emails']++;
            return '[EMAIL_REDACTED]';
        }, $scrubbed);

        // 2. Scrub Phone numbers (common international and national formats)
        $phoneRegex = '/(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}/';
        $scrubbed = preg_replace_callback($phoneRegex, function () use (&$redactedMetadata) {
            $redactedMetadata['phones']++;
            return '[PHONE_REDACTED]';
        }, $scrubbed);

        // 3. Scrub Credit Cards / 16-digit Account Numbers
        $accountRegex = '/\b(?:\d{4}[-\s]?){3}\d{4}\b/';
        $scrubbed = preg_replace_callback($accountRegex, function () use (&$redactedMetadata) {
            $redactedMetadata['accounts']++;
            return '[ACCOUNT_REDACTED]';
        }, $scrubbed);

        // 4. Known Supervisors and Agents matching
        $knownSupervisors = Survey::distinct()->whereNotNull('supervisor')->pluck('supervisor')->toArray();
        foreach ($knownSupervisors as $supervisor) {
            if (empty(trim($supervisor))) {
                continue;
            }
            $pattern = '/\b' . preg_quote($supervisor, '/') . '\b/i';
            if (preg_match($pattern, $scrubbed)) {
                $pseudonym = $this->pseudonymService->getOrCreatePseudonym($scopeId, 'supervisor', $supervisor);
                $scrubbed = preg_replace($pattern, $pseudonym, $scrubbed);
                $redactedMetadata['supervisors']++;
            }
        }

        $knownAgents = Survey::distinct()->whereNotNull('agent_bms')->pluck('agent_bms')->toArray();
        foreach ($knownAgents as $agent) {
            if (empty(trim($agent))) {
                continue;
            }
            $pattern = '/\b' . preg_quote($agent, '/') . '\b/i';
            if (preg_match($pattern, $scrubbed)) {
                $pseudonym = $this->pseudonymService->getOrCreatePseudonym($scopeId, 'agent', $agent);
                $scrubbed = preg_replace($pattern, $pseudonym, $scrubbed);
                $redactedMetadata['agents']++;
            }
        }

        return $scrubbed;
    }
}
