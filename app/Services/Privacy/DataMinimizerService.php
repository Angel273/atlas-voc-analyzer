<?php

namespace App\Services\Privacy;

class DataMinimizerService
{
    public function __construct(
        protected PseudonymService $pseudonymService
    ) {}

    /**
     * Minimize and pseudonymize query or tool result data before delivering to AI.
     */
    public function minimizeToolResult(array $data, string $scopeId, array $allowedFields = []): array
    {
        // If the result contains a list of records or aggregations, minimize each item
        return array_map(function ($item) use ($scopeId, $allowedFields) {
            if (!is_array($item)) {
                return $item;
            }

            // Filter down to allowed fields if specified
            if (!empty($allowedFields)) {
                $item = array_intersect_key($item, array_flip($allowedFields));
            }

            // Pseudonymize known identity fields
            if (isset($item['agent_bms'])) {
                $item['agent_ref'] = $this->pseudonymService->getOrCreatePseudonym($scopeId, 'agent', (string) $item['agent_bms']);
                unset($item['agent_bms']);
            }
            if (isset($item['agent'])) {
                $item['agent_ref'] = $this->pseudonymService->getOrCreatePseudonym($scopeId, 'agent', (string) $item['agent']);
                unset($item['agent']);
            }
            if (isset($item['agent_name'])) {
                $item['agent_name_ref'] = $this->pseudonymService->getOrCreatePseudonym($scopeId, 'agent_name', (string) $item['agent_name']);
                unset($item['agent_name']);
            }
            if (isset($item['supervisor'])) {
                $item['supervisor_ref'] = $this->pseudonymService->getOrCreatePseudonym($scopeId, 'supervisor', (string) $item['supervisor']);
                unset($item['supervisor']);
            }
            if (isset($item['survey_id'])) {
                $item['record_ref'] = $this->pseudonymService->getOrCreatePseudonym($scopeId, 'survey', (string) $item['survey_id']);
                unset($item['survey_id']);
            }

            // Never leak raw customer verbatims inside aggregated tool outputs
            if (isset($item['verbatim']) && !in_array('verbatim', $allowedFields, true)) {
                unset($item['verbatim']);
            }

            return $item;
        }, $data);
    }
}
