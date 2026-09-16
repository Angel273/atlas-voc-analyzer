<?php

namespace App\Services\Privacy;

class PiiScrubberService
{
    public function __construct(
        protected PseudonymService $pseudonymService,
        protected ?EntityFuzzyMatcher $entityMatcher = null
    ) {
        $this->entityMatcher = $entityMatcher ?? app(EntityFuzzyMatcher::class);
    }

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

        // 4. Known Supervisors and Agents matching (Exact, Aliases, Natural Order, and Fuzzy Matching)
        $scrubbed = $this->entityMatcher->extractAndReplaceEntities(
            $scrubbed,
            $scopeId,
            $this->pseudonymService,
            $redactedMetadata
        );

        return $scrubbed;
    }
}
