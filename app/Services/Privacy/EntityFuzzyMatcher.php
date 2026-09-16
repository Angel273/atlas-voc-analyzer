<?php

namespace App\Services\Privacy;

use App\Models\Survey;

class EntityFuzzyMatcher
{
    /**
     * Common Spanish and analytical domain stopwords that should never trigger an entity match.
     */
    protected const STOPWORDS = [
        'de', 'del', 'la', 'las', 'el', 'los', 'un', 'una', 'unos', 'unas',
        'y', 'o', 'e', 'en', 'para', 'por', 'con', 'sin', 'sobre', 'a', 'al',
        'equipo', 'supervisor', 'supervisora', 'supervisores', 'sup',
        'agente', 'agentes', 'asesor', 'asesora', 'asesores', 'rep', 'ejecutivo',
        'analisis', 'datos', 'metricas', 'metrica', 'nps', 'csat', 'profesionalismo',
        'encuesta', 'encuestas', 'cuenta', 'cliente', 'clientes',
        'verbatim', 'verbatims', 'comentarios', 'comentario',
        'team', 'leader', 'tl', 'promedio', 'desempeno', 'rendimiento', 'calidad',
        'todo', 'todos', 'todas', 'hola', 'como', 'cual', 'cuales', 'quien', 'quienes',
        'que', 'haz', 'hacer', 'dame', 'muestra', 'mostrar', 'ver', 'quiero', 'favor',
        'buenos', 'dias', 'tardes', 'noches', 'informacion', 'detalle', 'completo',
        'general', 'total', 'grupo', 'ola', 'wave', 'mes', 'semana', 'dia', 'fecha',
        'bajo', 'alto', 'mejor', 'peor', 'ranking', 'tabla', 'grafico', 'comparativa',
    ];

    /**
     * Normalize a string: lowercase, unaccented, stripped of special chars, normalized spaces.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $text
        );
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Convert an alias into an accent-insensitive regex pattern.
     */
    public function toAccentInsensitivePattern(string $str): string
    {
        $normalized = $this->normalize($str);
        $words = preg_split('/\s+/', $normalized);
        $patternWords = [];

        $charMap = [
            'a' => '[aáÁA]',
            'e' => '[eéÉE]',
            'i' => '[iíÍI]',
            'o' => '[oóÓO]',
            'u' => '[uúüÚÜU]',
            'n' => '[nñÑN]',
        ];

        foreach ($words as $word) {
            if (empty($word)) {
                continue;
            }
            $pWord = '';
            $len = mb_strlen($word);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($word, $i, 1);
                $pWord .= $charMap[$char] ?? preg_quote($char, '/');
            }
            $patternWords[] = $pWord;
        }

        // Allow comma, dot, or whitespace between words
        return implode('[,.\s]+', $patternWords);
    }

    /**
     * Parse an entity name into structured aliases, natural order permutations, and tokens.
     */
    public function parseEntityProfile(string $canonicalName, string $entityType = 'supervisor'): array
    {
        $normalizedFull = $this->normalize($canonicalName);

        // Check if DB format is "Surnames, GivenNames"
        $parts = explode(',', $canonicalName, 2);
        if (count($parts) === 2) {
            $surnamesNorm = $this->normalize($parts[0]);
            $givenNorm = $this->normalize($parts[1]);
            $naturalFull = trim("{$givenNorm} {$surnamesNorm}");

            $givenTokens = array_filter(explode(' ', $givenNorm), fn ($t) => mb_strlen($t) >= 2);
            $surnameTokens = array_filter(explode(' ', $surnamesNorm), fn ($t) => mb_strlen($t) >= 2);

            $naturalShort = '';
            if (! empty($givenTokens) && ! empty($surnameTokens)) {
                $naturalShort = reset($givenTokens).' '.reset($surnameTokens);
            }
        } else {
            $surnamesNorm = '';
            $givenNorm = '';
            $naturalFull = $normalizedFull;
            $naturalShort = $normalizedFull;
            $givenTokens = [];
            $surnameTokens = [];
        }

        $allTokens = array_values(array_filter(
            explode(' ', $normalizedFull),
            fn ($t) => mb_strlen($t) >= 2
        ));

        // Key aliases from longest to shortest
        $rawAliases = [
            $normalizedFull,
            $naturalFull,
            $naturalShort,
            $surnamesNorm,
            $givenNorm,
            ...$allTokens,
        ];

        $cleanAliases = [];
        foreach ($rawAliases as $alias) {
            $trimmed = trim((string) $alias);
            if (mb_strlen($trimmed) >= 3 && ! in_array($trimmed, self::STOPWORDS, true)) {
                $cleanAliases[$trimmed] = true;
            }
        }

        $aliases = array_keys($cleanAliases);
        usort($aliases, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return [
            'canonical' => $canonicalName,
            'entity_type' => $entityType,
            'normalized_full' => $normalizedFull,
            'natural_full' => $naturalFull,
            'surnames_norm' => $surnamesNorm,
            'given_norm' => $givenNorm,
            'given_tokens' => array_values($givenTokens),
            'surname_tokens' => array_values($surnameTokens),
            'tokens' => $allTokens,
            'aliases' => $aliases,
        ];
    }

    /**
     * Find the best matching canonical supervisor name for a query string.
     */
    public function findBestSupervisorMatch(string $query, ?array $knownSupervisors = null): ?string
    {
        $supervisors = $knownSupervisors ?? Survey::distinct()->whereNotNull('supervisor')->pluck('supervisor')->toArray();
        if (empty($supervisors)) {
            return null;
        }

        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return null;
        }

        // 1. Exact match in canonical names
        foreach ($supervisors as $sup) {
            if (strcasecmp($cleanQuery, $sup) === 0) {
                return $sup;
            }
        }

        $normQuery = $this->normalize($cleanQuery);
        if (empty($normQuery) || in_array($normQuery, self::STOPWORDS, true)) {
            return null;
        }

        // 2. Exact match on normalized full or aliases
        $profiles = [];
        foreach ($supervisors as $sup) {
            $profile = $this->parseEntityProfile($sup, 'supervisor');
            $profiles[$sup] = $profile;

            if ($normQuery === $profile['normalized_full'] || $normQuery === $profile['natural_full']) {
                return $sup;
            }

            foreach ($profile['aliases'] as $alias) {
                if ($normQuery === $alias) {
                    return $sup;
                }
            }
        }

        // 3. Token containment (if all query tokens are present in supervisor tokens)
        $queryTokens = array_filter(explode(' ', $normQuery), fn ($t) => mb_strlen($t) >= 3 && ! in_array($t, self::STOPWORDS, true));
        if (! empty($queryTokens)) {
            $tokenMatches = [];
            foreach ($profiles as $sup => $profile) {
                $matchedTokens = 0;
                foreach ($queryTokens as $qt) {
                    if (in_array($qt, $profile['tokens'], true)) {
                        $matchedTokens++;
                    }
                }
                if ($matchedTokens === count($queryTokens)) {
                    $tokenMatches[$sup] = $matchedTokens;
                }
            }
            if (count($tokenMatches) === 1) {
                return array_key_first($tokenMatches);
            }
        }

        // 4. Fuzzy / Typo matching using Levenshtein, similar_text, and soundex
        $bestMatch = null;
        $bestScore = 0.0;
        $secondBestScore = 0.0;

        foreach ($profiles as $sup => $profile) {
            $maxEntityScore = 0.0;

            // Test against each supervisor token
            foreach ($profile['tokens'] as $tok) {
                if (mb_strlen($tok) < 3) {
                    continue;
                }

                // Check Levenshtein distance
                $dist = levenshtein($normQuery, $tok);
                $maxLen = max(mb_strlen($normQuery), mb_strlen($tok));
                $levScore = 1.0 - ($dist / max(1, $maxLen));

                // Check text similarity percentage
                similar_text($normQuery, $tok, $simPercent);
                $simScore = $simPercent / 100.0;

                // Check phonetic match
                $soundexMatch = (soundex($normQuery) === soundex($tok)) ? 0.85 : 0.0;

                $compositeScore = max($levScore, $simScore, $soundexMatch);

                // For short words (4-5 chars), require distance <= 1; for 6+ chars, distance <= 2
                if ($maxLen <= 5 && $dist > 1 && ! ($soundexMatch >= 0.85 && $simScore >= 0.70)) {
                    $compositeScore *= 0.5;
                } elseif ($maxLen > 5 && $dist > 2 && ! ($soundexMatch >= 0.85 && $simScore >= 0.70)) {
                    $compositeScore *= 0.6;
                }

                if ($compositeScore > $maxEntityScore) {
                    $maxEntityScore = $compositeScore;
                }
            }

            // Also test against full natural short name (e.g. "Michael Majano")
            if (! empty($profile['aliases'])) {
                foreach ($profile['aliases'] as $alias) {
                    similar_text($normQuery, $alias, $aliasSim);
                    $aliasScore = $aliasSim / 100.0;
                    if ($aliasScore > $maxEntityScore) {
                        $maxEntityScore = $aliasScore;
                    }
                }
            }

            if ($maxEntityScore > $bestScore) {
                $secondBestScore = $bestScore;
                $bestScore = $maxEntityScore;
                $bestMatch = $sup;
            } elseif ($maxEntityScore > $secondBestScore) {
                $secondBestScore = $maxEntityScore;
            }
        }

        // Accept match if bestScore >= 0.78 and sufficiently distinct from second best
        if ($bestMatch && $bestScore >= 0.78 && ($bestScore - $secondBestScore >= 0.12 || $bestScore >= 0.90)) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Find the best matching canonical agent name for a query string.
     */
    public function findBestAgentMatch(string $query, ?array $knownAgents = null): ?string
    {
        $agents = $knownAgents ?? Survey::distinct()->whereNotNull('agent_name')->pluck('agent_name')->toArray();
        if (empty($agents)) {
            return null;
        }

        $cleanQuery = trim($query);
        if (empty($cleanQuery)) {
            return null;
        }

        // Exact match
        foreach ($agents as $ag) {
            if (strcasecmp($cleanQuery, $ag) === 0) {
                return $ag;
            }
        }

        $normQuery = $this->normalize($cleanQuery);
        if (empty($normQuery) || in_array($normQuery, self::STOPWORDS, true)) {
            return null;
        }

        // Exact match on aliases
        $profiles = [];
        foreach ($agents as $ag) {
            $profile = $this->parseEntityProfile($ag, 'agent');
            $profiles[$ag] = $profile;

            if ($normQuery === $profile['normalized_full'] || $normQuery === $profile['natural_full']) {
                return $ag;
            }

            foreach ($profile['aliases'] as $alias) {
                if ($normQuery === $alias) {
                    return $ag;
                }
            }
        }

        // Fuzzy match
        $bestMatch = null;
        $bestScore = 0.0;
        $secondBestScore = 0.0;

        foreach ($profiles as $ag => $profile) {
            $maxEntityScore = 0.0;
            foreach ($profile['tokens'] as $tok) {
                if (mb_strlen($tok) < 3) {
                    continue;
                }
                $dist = levenshtein($normQuery, $tok);
                $maxLen = max(mb_strlen($normQuery), mb_strlen($tok));
                $levScore = 1.0 - ($dist / max(1, $maxLen));
                similar_text($normQuery, $tok, $simPercent);
                $simScore = $simPercent / 100.0;

                $score = max($levScore, $simScore);
                if ($maxLen <= 5 && $dist > 1) {
                    $score *= 0.5;
                } elseif ($maxLen > 5 && $dist > 2) {
                    $score *= 0.6;
                }
                if ($score > $maxEntityScore) {
                    $maxEntityScore = $score;
                }
            }

            if ($maxEntityScore > $bestScore) {
                $secondBestScore = $bestScore;
                $bestScore = $maxEntityScore;
                $bestMatch = $ag;
            } elseif ($maxEntityScore > $secondBestScore) {
                $secondBestScore = $maxEntityScore;
            }
        }

        if ($bestMatch && $bestScore >= 0.80 && ($bestScore - $secondBestScore >= 0.15 || $bestScore >= 0.90)) {
            return $bestMatch;
        }

        return null;
    }

    /**
     * Extract supervisor and agent mentions from text (exact, partial, and fuzzy)
     * and replace them with scoped pseudonyms.
     */
    public function extractAndReplaceEntities(
        string $text,
        string $scopeId,
        PseudonymService $pseudonymService,
        array &$redactedMetadata
    ): string {
        $scrubbed = $text;

        // 1. Numeric Agent BMS replacement first
        $knownAgentBms = Survey::distinct()->whereNotNull('agent_bms')->pluck('agent_bms')->toArray();
        foreach ($knownAgentBms as $bms) {
            if (empty(trim($bms))) {
                continue;
            }
            $pattern = '/\b'.preg_quote($bms, '/').'\b/i';
            if (preg_match($pattern, $scrubbed)) {
                $canonicalName = Survey::where('agent_bms', $bms)->whereNotNull('agent_name')->value('agent_name') ?: $bms;
                $pseudonym = $pseudonymService->getOrCreatePseudonym($scopeId, 'agent', $canonicalName);
                $scrubbed = preg_replace($pattern, $pseudonym, $scrubbed);
                $redactedMetadata['agents'] = ($redactedMetadata['agents'] ?? 0) + 1;
            }
        }

        // 2. Supervisor exact and alias matching (Longest aliases first)
        $knownSupervisors = Survey::distinct()->whereNotNull('supervisor')->pluck('supervisor')->toArray();
        $supervisorProfiles = [];
        $supervisorAliases = [];

        foreach ($knownSupervisors as $supervisor) {
            if (empty(trim($supervisor))) {
                continue;
            }
            $profile = $this->parseEntityProfile($supervisor, 'supervisor');
            $supervisorProfiles[$supervisor] = $profile;

            foreach ($profile['aliases'] as $alias) {
                $supervisorAliases[] = [
                    'alias' => $alias,
                    'canonical' => $supervisor,
                    'length' => mb_strlen($alias),
                ];
            }
        }

        // Sort all supervisor aliases across all supervisors by length descending
        usort($supervisorAliases, fn ($a, $b) => $b['length'] <=> $a['length']);

        foreach ($supervisorAliases as $item) {
            $aliasPattern = $this->toAccentInsensitivePattern($item['alias']);
            $pattern = '/\b'.$aliasPattern.'\b/iu';

            if (preg_match($pattern, $scrubbed)) {
                $pseudonym = $pseudonymService->getOrCreatePseudonym($scopeId, 'supervisor', $item['canonical']);
                $scrubbed = preg_replace($pattern, $pseudonym, $scrubbed);
                $redactedMetadata['supervisors'] = ($redactedMetadata['supervisors'] ?? 0) + 1;
            }
        }

        // 3. Supervisor Fuzzy / Typo matching on individual words in remaining text
        if (preg_match_all('/\b[\p{L}\p{N}]{4,}\b/u', $scrubbed, $wordMatches)) {
            $uniqueWords = array_unique($wordMatches[0]);
            foreach ($uniqueWords as $word) {
                // Skip if already a pseudonym token or stopword
                if (str_starts_with($word, 'SUP_') || str_starts_with($word, 'AGT_') || str_starts_with($word, 'REC_')) {
                    continue;
                }
                $normWord = $this->normalize($word);
                if (in_array($normWord, self::STOPWORDS, true)) {
                    continue;
                }

                $matchedSup = $this->findBestSupervisorMatch($normWord, $knownSupervisors);
                if ($matchedSup) {
                    $pseudonym = $pseudonymService->getOrCreatePseudonym($scopeId, 'supervisor', $matchedSup);
                    $wordPattern = '/\b'.preg_quote($word, '/').'\b/u';
                    $scrubbed = preg_replace($wordPattern, $pseudonym, $scrubbed);
                    $redactedMetadata['supervisors'] = ($redactedMetadata['supervisors'] ?? 0) + 1;
                }
            }
        }

        // 4. Agent exact and alias matching
        $knownAgents = Survey::distinct()->whereNotNull('agent_name')->pluck('agent_name')->toArray();
        $agentAliases = [];

        foreach ($knownAgents as $agent) {
            if (empty(trim($agent))) {
                continue;
            }
            $profile = $this->parseEntityProfile($agent, 'agent');

            foreach ($profile['aliases'] as $alias) {
                $agentAliases[] = [
                    'alias' => $alias,
                    'canonical' => $agent,
                    'length' => mb_strlen($alias),
                ];
            }
        }

        usort($agentAliases, fn ($a, $b) => $b['length'] <=> $a['length']);

        foreach ($agentAliases as $item) {
            $aliasPattern = $this->toAccentInsensitivePattern($item['alias']);
            $pattern = '/\b'.$aliasPattern.'\b/iu';

            if (preg_match($pattern, $scrubbed)) {
                $pseudonym = $pseudonymService->getOrCreatePseudonym($scopeId, 'agent', $item['canonical']);
                $scrubbed = preg_replace($pattern, $pseudonym, $scrubbed);
                $redactedMetadata['agents'] = ($redactedMetadata['agents'] ?? 0) + 1;
            }
        }

        return $scrubbed;
    }

    /**
     * Resolve a supervisor name or token: if pseudonym, resolves via vault;
     * if raw name or partial name, resolves via fuzzy matching.
     */
    public function resolveSupervisor(string $nameOrToken, string $scopeId, PseudonymService $pseudonymService): string
    {
        $trimmed = trim($nameOrToken);
        if (empty($trimmed)) {
            return $nameOrToken;
        }

        // 1. Pseudonym resolution
        if (str_starts_with($trimmed, 'SUP_') || str_starts_with($trimmed, 'ENT_')) {
            $resolved = $pseudonymService->resolveToInternalId($scopeId, $trimmed);
            if ($resolved) {
                return $resolved;
            }
        }

        // 2. Fuzzy match against canonical database supervisors
        $matched = $this->findBestSupervisorMatch($trimmed);
        if ($matched) {
            return $matched;
        }

        return $nameOrToken;
    }

    /**
     * Resolve an agent name or token: if pseudonym, resolves via vault;
     * if raw name or partial name, resolves via fuzzy matching.
     */
    public function resolveAgent(string $nameOrToken, string $scopeId, PseudonymService $pseudonymService): string
    {
        $trimmed = trim($nameOrToken);
        if (empty($trimmed)) {
            return $nameOrToken;
        }

        // 1. Pseudonym resolution
        if (str_starts_with($trimmed, 'AGT_') || str_starts_with($trimmed, 'ENT_')) {
            $resolved = $pseudonymService->resolveToInternalId($scopeId, $trimmed);
            if ($resolved) {
                return $resolved;
            }
        }

        // 2. Fuzzy match against canonical database agents
        $matched = $this->findBestAgentMatch($trimmed);
        if ($matched) {
            return $matched;
        }

        return $nameOrToken;
    }
}
