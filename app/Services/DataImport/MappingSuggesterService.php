<?php

namespace App\Services\DataImport;

class MappingSuggesterService
{
    /**
     * Map of internal VOC fields to list of known aliases and patterns.
     */
    protected const ALIASES = [
        'survey_id' => [
            'survey_id', 'survey id', 'surveyid', 'survey identifier', 'survey_identifier',
            'id', 'record_id', 'response_id', 'response id', 'encuesta_id', 'id_encuesta',
        ],
        'nps_score' => [
            'nps_score', 'nps score', 'nps', 'net promoter score', 'net_promoter_score',
            'nps %', 'nps_val', 'score_nps',
        ],
        'csat_score' => [
            'csat_score', 'csat score', 'csat', 'csat %', 'customer satisfaction',
            'satisfaction', 'satisfaction_score', 'satisfaccion',
        ],
        'professionalism_score' => [
            'professionalism_score', 'professionalism score', 'professionalism', 'prof',
            'prof score', 'prof_score', 'professionalism %', 'profesionalismo',
        ],
        'verbatim' => [
            'verbatim', 'comment', 'customer comment', 'customer comments', 'comments',
            'feedback', 'text', 'open_text', 'comentarios', 'comentario', 'opinion',
        ],
        'agent_bms' => [
            'agent_bms', 'agent bms', 'bms', 'agent id', 'agent_id', 'employee id',
            'employee_id', 'rep id', 'rep_id', 'bms_id', 'id_agente', 'id agente',
        ],
        'agent_name' => [
            'agent_name', 'agent name', 'nombre del agente', 'nombre agente', 'nombre_agente',
            'advisor name', 'advisor', 'rep name', 'representative', 'agent', 'ejecutivo', 'nombre',
        ],
        'supervisor' => [
            'supervisor', 'supervisor name', 'team leader', 'team_leader', 'sup',
            'leader', 'tl', 'sup_name', 'supervisor_name', 'lider',
        ],
        'survey_date' => [
            'survey_date', 'survey date', 'date', 'response_date', 'response date',
            'fecha', 'fecha_encuesta', 'created_date', 'timestamp',
        ],
        'wave' => [
            'wave', 'wave name', 'wave_name', 'cohort', 'batch', 'oleada', 'grupo',
        ],
        'tenure_days' => [
            'tenure_days', 'tenure days', 'tenure', 'days in production', 'days_in_production',
            'antiguedad', 'dias_produccion', 'dias',
        ],
    ];

    /**
     * Suggest mapping for given headers from an Excel file.
     * Returns an array mapping internal VOC attribute => matched Excel column header.
     */
    public function suggestMapping(array $excelHeaders): array
    {
        $suggestions = [];
        $usedExcelColumns = [];

        foreach (self::ALIASES as $internalField => $aliases) {
            foreach ($excelHeaders as $columnKey => $headerName) {
                if (in_array($columnKey, $usedExcelColumns, true)) {
                    continue;
                }

                $normalizedHeader = $this->normalizeString($headerName);

                foreach ($aliases as $alias) {
                    if ($normalizedHeader === $this->normalizeString($alias)) {
                        $suggestions[$internalField] = [
                            'column_key' => $columnKey,
                            'header_name' => $headerName,
                            'confidence' => 'exact',
                        ];
                        $usedExcelColumns[] = $columnKey;
                        break 2;
                    }
                }
            }
        }

        // Second pass: partial/contains match for unmapped fields
        foreach (self::ALIASES as $internalField => $aliases) {
            if (isset($suggestions[$internalField])) {
                continue;
            }

            foreach ($excelHeaders as $columnKey => $headerName) {
                if (in_array($columnKey, $usedExcelColumns, true)) {
                    continue;
                }

                $normalizedHeader = $this->normalizeString($headerName);

                foreach ($aliases as $alias) {
                    $normAlias = $this->normalizeString($alias);
                    if (str_contains($normalizedHeader, $normAlias) || str_contains($normAlias, $normalizedHeader)) {
                        $suggestions[$internalField] = [
                            'column_key' => $columnKey,
                            'header_name' => $headerName,
                            'confidence' => 'fuzzy',
                        ];
                        $usedExcelColumns[] = $columnKey;
                        break 2;
                    }
                }
            }
        }

        return $suggestions;
    }

    protected function normalizeString(string $val): string
    {
        $val = mb_strtolower($val, 'UTF-8');
        $val = preg_replace('/[_\-\.\s%]+/', ' ', $val);
        return trim($val);
    }
}
