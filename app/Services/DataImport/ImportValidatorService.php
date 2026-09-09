<?php

namespace App\Services\DataImport;

use DateTime;

class ImportValidatorService
{
    /**
     * Validate a row against VOC attribute requirements and applied transformations.
     */
    public function validateRow(array $rowValues, array $columnMapping, array $transformations = []): array
    {
        $normalized = [];
        $errors = [];
        $warnings = [];

        // 1. survey_id (required, string)
        $surveyIdCol = $columnMapping['survey_id'] ?? null;
        $rawSurveyId = $surveyIdCol !== null ? ($rowValues[$surveyIdCol] ?? null) : null;
        if ($rawSurveyId === null || trim((string) $rawSurveyId) === '') {
            $errors[] = "Survey ID is required but was missing or empty.";
        } else {
            $normalized['survey_id'] = trim((string) $rawSurveyId);
        }

        // 2. nps_score (required, -1 to 1)
        $npsCol = $columnMapping['nps_score'] ?? null;
        $rawNps = $npsCol !== null ? ($rowValues[$npsCol] ?? null) : null;
        if ($rawNps === null || trim((string) $rawNps) === '') {
            $errors[] = "NPS Score is required.";
        } else {
            $val = $this->parseNumeric($rawNps);
            if ($val === null) {
                $errors[] = "NPS Score '{$rawNps}' is not a valid number.";
            } else {
                // Apply percentage transformation only if explicitly requested in transformations
                $isPercentage = !empty($transformations['nps_score']['percentage']);
                if ($isPercentage && abs($val) > 1.0) {
                    $val = $val / 100.0;
                    $warnings[] = "NPS converted from percentage ({$rawNps} → {$val}).";
                }

                if ($val < -1.0 || $val > 1.0) {
                    $errors[] = "NPS Score ({$val}) is out of allowed range [-1, 1].";
                } else {
                    $normalized['nps_score'] = round($val, 4);
                }
            }
        }

        // 3. csat_score (required, -1 to 1)
        $csatCol = $columnMapping['csat_score'] ?? null;
        $rawCsat = $csatCol !== null ? ($rowValues[$csatCol] ?? null) : null;
        if ($rawCsat === null || trim((string) $rawCsat) === '') {
            $errors[] = "CSAT Score is required.";
        } else {
            $val = $this->parseNumeric($rawCsat);
            if ($val === null) {
                $errors[] = "CSAT Score '{$rawCsat}' is not a valid number.";
            } else {
                $isPercentage = !empty($transformations['csat_score']['percentage']);
                if ($isPercentage && abs($val) > 1.0) {
                    $val = $val / 100.0;
                    $warnings[] = "CSAT converted from percentage ({$rawCsat} → {$val}).";
                }

                if ($val < -1.0 || $val > 1.0) {
                    $errors[] = "CSAT Score ({$val}) is out of allowed range [-1, 1].";
                } else {
                    $normalized['csat_score'] = round($val, 4);
                }
            }
        }

        // 4. professionalism_score (required, -1 to 1)
        $profCol = $columnMapping['professionalism_score'] ?? null;
        $rawProf = $profCol !== null ? ($rowValues[$profCol] ?? null) : null;
        if ($rawProf === null || trim((string) $rawProf) === '') {
            $errors[] = "Professionalism Score is required.";
        } else {
            $val = $this->parseNumeric($rawProf);
            if ($val === null) {
                $errors[] = "Professionalism Score '{$rawProf}' is not a valid number.";
            } else {
                $isPercentage = !empty($transformations['professionalism_score']['percentage']);
                if ($isPercentage && abs($val) > 1.0) {
                    $val = $val / 100.0;
                    $warnings[] = "Professionalism converted from percentage ({$rawProf} → {$val}).";
                }

                if ($val < -1.0 || $val > 1.0) {
                    $errors[] = "Professionalism Score ({$val}) is out of allowed range [-1, 1].";
                } else {
                    $normalized['professionalism_score'] = round($val, 4);
                }
            }
        }

        // 5. verbatim (nullable text)
        $verbatimCol = $columnMapping['verbatim'] ?? null;
        $rawVerbatim = $verbatimCol !== null ? ($rowValues[$verbatimCol] ?? null) : null;
        $normalized['verbatim'] = $rawVerbatim !== null && trim((string) $rawVerbatim) !== ''
            ? trim((string) $rawVerbatim)
            : null;

        // 6. agent_name (nullable string)
        $agentNameCol = $columnMapping['agent_name'] ?? null;
        $rawAgentName = $agentNameCol !== null ? ($rowValues[$agentNameCol] ?? null) : null;
        $normalized['agent_name'] = $rawAgentName !== null && trim((string) $rawAgentName) !== ''
            ? trim((string) $rawAgentName)
            : null;

        // 7. agent_bms (required string)
        $agentCol = $columnMapping['agent_bms'] ?? null;
        $rawAgent = $agentCol !== null ? ($rowValues[$agentCol] ?? null) : null;
        if ($rawAgent === null || trim((string) $rawAgent) === '') {
            $errors[] = "Agent BMS identifier is required.";
        } else {
            $normalized['agent_bms'] = trim((string) $rawAgent);
        }

        // 7. supervisor (required string)
        $supCol = $columnMapping['supervisor'] ?? null;
        $rawSup = $supCol !== null ? ($rowValues[$supCol] ?? null) : null;
        if ($rawSup === null || trim((string) $rawSup) === '') {
            $errors[] = "Supervisor is required.";
        } else {
            $normalized['supervisor'] = trim((string) $rawSup);
        }

        // 8. survey_date (required date)
        $dateCol = $columnMapping['survey_date'] ?? null;
        $rawDate = $dateCol !== null ? ($rowValues[$dateCol] ?? null) : null;
        if ($rawDate === null || trim((string) $rawDate) === '') {
            $errors[] = "Survey Date is required.";
        } else {
            $parsedDate = $this->parseDate($rawDate);
            if (!$parsedDate) {
                $errors[] = "Survey Date '{$rawDate}' is not a recognized date format.";
            } else {
                $normalized['survey_date'] = $parsedDate;
            }
        }

        // 9. wave (nullable string)
        $waveCol = $columnMapping['wave'] ?? null;
        $rawWave = $waveCol !== null ? ($rowValues[$waveCol] ?? null) : null;
        $normalized['wave'] = $rawWave !== null && trim((string) $rawWave) !== ''
            ? trim((string) $rawWave)
            : null;

        // 10. tenure_days (nullable integer >= 0)
        $tenureCol = $columnMapping['tenure_days'] ?? null;
        $rawTenure = $tenureCol !== null ? ($rowValues[$tenureCol] ?? null) : null;
        if ($rawTenure !== null && trim((string) $rawTenure) !== '') {
            if (!is_numeric($rawTenure) || (int) $rawTenure < 0) {
                $errors[] = "Tenure Days must be an integer >= 0 (got '{$rawTenure}').";
            } else {
                $normalized['tenure_days'] = (int) $rawTenure;
            }
        } else {
            $normalized['tenure_days'] = null;
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'normalized' => $normalized,
        ];
    }

    protected function parseNumeric(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $clean = str_replace(['%', '$', ','], '', trim($value));
            if (is_numeric($clean)) {
                return (float) $clean;
            }
        }

        return null;
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        // Excel serial date number
        if (is_numeric($value) && (float) $value > 25000 && (float) $value < 65000) {
            $unixDate = ($value - 25569) * 86400;
            return gmdate('Y-m-d', (int) $unixDate);
        }

        $str = trim((string) $value);
        $time = strtotime($str);
        if ($time !== false) {
            return date('Y-m-d', $time);
        }

        // Try common formats
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'] as $fmt) {
            $d = DateTime::createFromFormat($fmt, $str);
            if ($d && $d->format($fmt) === $str) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }
}
