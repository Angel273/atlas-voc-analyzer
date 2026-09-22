<?php

namespace App\Services\Reports;

use App\Models\KpiGoal;
use App\Services\Ai\Contracts\AiProvider;
use Illuminate\Support\Facades\Log;

class AiTeamReportNarrativeService
{
    public function __construct(
        protected AiProvider $provider
    ) {}

    /**
     * Generate a structured performance narrative for a team report.
     *
     * @param  array<string, mixed>  $reportData
     * @param  array<string, mixed>  $options
     * @return array{
     *     narrative: array{
     *         executive_summary: string,
     *         team_strengths: array<string>,
     *         team_risks: array<string>,
     *         agent_reviews: array<array{
     *             agent_name: string,
     *             assessment: string,
     *             action: string,
     *             verbatim_analysis?: string,
     *             strengths?: array<string>,
     *             friction_points?: array<string>
     *         }>,
     *         recommended_actions: array<string>,
     *         data_quality_notes: string
     *     },
     *     tokens_used: int,
     *     model: string,
     *     is_fallback: bool
     * }
     */
    public function generateNarrative(array $reportData, array $options = []): array
    {
        $model = $options['model'] ?? config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest'));
        $baseline = $this->buildDeterministicNarrative($reportData);

        try {
            $prompt = $this->buildPrompt($reportData);
            $systemInstruction = $this->buildSystemInstruction($reportData['goals'] ?? null);

            $response = $this->provider->generate(
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ],
                tools: [],
                options: [
                    'system_instruction' => $systemInstruction,
                    'model' => $model,
                ]
            );

            $rawContent = trim((string) ($response['content'] ?? ''));
            $parsedJson = $this->extractJsonPayload($rawContent);

            if ($this->isValidNarrativePayload($parsedJson)) {
                // Ensure agent review items have required verbatim structure and merge with baseline
                // to guarantee that 100% of agents in the report have an analysis.
                $parsedJson['agent_reviews'] = $this->mergeAgentReviews(
                    $baseline['agent_reviews'] ?? [],
                    $parsedJson['agent_reviews'] ?? []
                );

                return [
                    'narrative' => $parsedJson,
                    'tokens_used' => (int) ($response['tokens_used'] ?? 0),
                    'model' => $response['model'] ?? $model,
                    'is_fallback' => false,
                ];
            }

            Log::warning('AI narrative response did not match expected JSON schema. Using deterministic fallback.');
        } catch (\Throwable $e) {
            Log::warning("AI narrative generation failed: {$e->getMessage()}. Using deterministic fallback.", [
                'exception' => $e,
            ]);
        }

        return [
            'narrative' => $baseline,
            'tokens_used' => 0,
            'model' => 'deterministic-rules-engine',
            'is_fallback' => true,
        ];
    }

    /**
     * Rule-based deterministic fallback narrative generator.
     *
     * @param  array<string, mixed>  $reportData
     * @return array{
     *     executive_summary: string,
     *     team_strengths: array<string>,
     *     team_risks: array<string>,
     *     agent_reviews: array<array{agent_name: string, assessment: string, action: string}>,
     *     recommended_actions: array<string>,
     *     data_quality_notes: string
     * }
     */
    public function buildDeterministicNarrative(array $reportData): array
    {
        $team = $reportData['team'] ?? [];
        $metrics = $reportData['metrics'] ?? [];
        $comparison = $reportData['comparison'] ?? null;
        $agentReviews = $reportData['agent_reviews'] ?? [];
        $isLowSample = (bool) ($reportData['is_low_sample'] ?? false);
        $openCases = $reportData['open_cases'] ?? [];

        $teamName = $team['name'] ?? 'Equipo';
        $supervisor = $team['supervisor_name'] ?? 'Supervisor';
        $volume = $metrics['survey_volume'] ?? 0;

        $nps = $metrics['nps_score'] !== null ? sprintf('%+.2f (%+.1f%%)', $metrics['nps_score'], $metrics['nps_score'] * 100) : 'N/D';
        $csat = $metrics['csat_score'] !== null ? sprintf('%.2f (%.1f%%)', $metrics['csat_score'], $metrics['csat_score'] * 100) : 'N/D';
        $prof = $metrics['professionalism_score'] !== null ? sprintf('%.2f (%.1f%%)', $metrics['professionalism_score'], $metrics['professionalism_score'] * 100) : 'N/D';

        // Goals check
        $goals = $metrics['goals_comparison'] ?? [];
        $npsMet = ($goals['nps']['meets_goal'] ?? false);
        $csatMet = ($goals['csat']['meets_goal'] ?? false);
        $profMet = ($goals['professionalism']['meets_goal'] ?? false);

        // 1. Executive summary
        $execSummary = "Durante el período evaluado, el equipo {$teamName} liderado por {$supervisor} registró un volumen de {$volume} encuestas. ";
        if ($volume === 0) {
            $execSummary .= 'No se registraron interacciones evaluadas en el corte actual. Se recomienda revisar la asignación y captura de encuestas para el equipo.';
        } else {
            $metCount = ($npsMet ? 1 : 0) + ($csatMet ? 1 : 0) + ($profMet ? 1 : 0);
            if ($metCount === 3) {
                $execSummary .= "El equipo alcanzó o superó el 100% de las metas operacionales establecidas (NPS: {$nps}, CSAT: {$csat}, Profesionalismo: {$prof}), demostrando solidez en el servicio al cliente.";
            } elseif ($metCount >= 1) {
                $execSummary .= "El equipo muestra cumplimiento parcial con {$metCount} de 3 metas alcanzadas (NPS: {$nps}, CSAT: {$csat}, Profesionalismo: {$prof}). Se requiere foco en las dimensiones rezagadas.";
            } else {
                $execSummary .= "Las 3 metas operacionales se encuentran por debajo del benchmark esperado (NPS: {$nps}, CSAT: {$csat}, Profesionalismo: {$prof}), requiriendo intervención y calibración operativa inmediata.";
            }

            if ($comparison && isset($comparison['deltas'])) {
                $npsDelta = $comparison['deltas']['nps_delta'];
                $csatDelta = $comparison['deltas']['csat_delta'];
                if ($npsDelta !== null) {
                    $execSummary .= sprintf(' Frente al período anterior, el NPS varió en %+.2f y el CSAT en %+.2f.', $npsDelta, $csatDelta ?? 0);
                }
            }
        }

        // 2. Strengths
        $strengths = [];
        if ($profMet && $metrics['professionalism_score'] !== null) {
            $strengths[] = "Excelente trato y cortesía reflejado en Profesionalismo ({$prof}), cumpliendo la meta fijada.";
        }
        if ($csatMet && $metrics['csat_score'] !== null) {
            $strengths[] = "Satisfacción del cliente (CSAT) sólida en {$csat}, alcanzando el umbral de excelencia.";
        }
        if ($npsMet && $metrics['nps_score'] !== null) {
            $strengths[] = "Índice de recomendación neto favorable ({$nps}), superando la meta operacional de lealtad.";
        }
        if ($comparison && ($comparison['deltas']['volume_delta'] ?? 0) > 0) {
            $strengths[] = sprintf('Crecimiento en el volumen de encuestas auditadas (+%d interacciones vs período previo).', $comparison['deltas']['volume_delta']);
        }
        if (empty($strengths)) {
            $strengths[] = 'Compromiso del equipo en la atención diaria de clientes y registro de interacciones.';
        }

        // 3. Risks
        $risks = [];
        if ($isLowSample) {
            $risks[] = "Muestra reducida ({$volume} encuestas). Las métricas pueden presentar alta sensibilidad a casos atípicos aislados.";
        }
        if (! $npsMet && $metrics['nps_score'] !== null) {
            $risks[] = "NPS por debajo del objetivo (+0.50), situándose en {$nps}. Alerta sobre detractores no contenidos en la llamada.";
        }
        if (! $csatMet && $metrics['csat_score'] !== null) {
            $risks[] = "CSAT en zona de atención ({$csat} vs meta 80.0%), evidenciando fricción en la resolución del cliente.";
        }
        if (! empty($openCases)) {
            $risks[] = sprintf('Existen %d caso(s) de desempeño activo(s) en seguimiento dentro del equipo.', count($openCases));
        }
        if (empty($risks)) {
            $risks[] = 'Monitorear la consistencia semanal para evitar desvíos ante incrementos estacionales de volumen.';
        }

        // 4. Agent reviews
        $reviews = [];
        foreach ($agentReviews as $ar) {
            $reviews[] = $this->buildSingleAgentReview($ar);
        }

        // 5. Recommended actions
        $actions = [];
        if (! empty($reportData['agents_needing_attention'])) {
            $actions[] = 'Ejecutar sesiones de coaching prioritarias con los agentes que presentaron indicadores en zona crítica.';
        }
        if (! $csatMet || ! $npsMet) {
            $actions[] = 'Auditar grabaciones de interacciones con calificaciones detractoras para diagnosticar causas raíz.';
        }
        if (! empty($reportData['verbatim_categories'])) {
            $topCat = $reportData['verbatim_categories'][0]['category'] ?? null;
            if ($topCat) {
                $actions[] = "Revisar políticas operativas vinculadas a la categoría con mayor recurrencia: '{$topCat}'.";
            }
        }
        $actions[] = 'Monitorear la evolución de los resultados diarios en los tableros de seguimiento.';

        // 6. Data quality notes
        $dataQuality = $isLowSample
            ? "Muestra limitada ({$volume} encuestas registradas). Los porcentajes deben interpretarse con cautela metodológica debido a la baja representatividad estadística."
            : "Muestra representativa de {$volume} encuestas procesadas con trazabilidad auditable en el período indicado.";

        return [
            'executive_summary' => $execSummary,
            'team_strengths' => $strengths,
            'team_risks' => $risks,
            'agent_reviews' => $reviews,
            'recommended_actions' => $actions,
            'data_quality_notes' => $dataQuality,
        ];
    }

    /**
     * Build the user prompt containing structured data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function buildPrompt(array $data): string
    {
        $team = $data['team'] ?? [];
        $period = $data['period'] ?? [];
        $metrics = $data['metrics'] ?? [];
        $comparison = $data['comparison'] ?? null;
        $agents = $data['agent_reviews'] ?? [];
        $openCases = $data['open_cases'] ?? [];
        $categories = $data['verbatim_categories'] ?? [];

        // Full agent summary including all their verbatims
        $agentSummary = [];
        foreach ($agents as $agent) {
            $agentSummary[] = [
                'agent_name' => $agent['agent_name'],
                'agent_bms' => $agent['agent_bms'],
                'volume' => $agent['volume'],
                'nps' => $agent['nps'],
                'csat' => $agent['csat'],
                'professionalism' => $agent['professionalism'],
                'status' => $agent['status'],
                'promoters_count' => $agent['promoters_count'] ?? 0,
                'passives_count' => $agent['passives_count'] ?? 0,
                'detractors_count' => $agent['detractors_count'] ?? 0,
                'top_categories' => $agent['top_categories'] ?? [],
                'verbatims' => array_map(function ($vb) {
                    return [
                        'date' => $vb['date'],
                        'sentiment' => $vb['sentiment'],
                        'category' => $vb['category'],
                        'nps_score' => $vb['nps_score'],
                        'text' => $vb['verbatim'],
                    ];
                }, $agent['verbatims'] ?? []),
            ];
        }

        $payload = [
            'team' => $team,
            'period' => $period,
            'team_metrics' => $metrics,
            'previous_period_comparison' => $comparison,
            'agents' => $agentSummary,
            'open_performance_cases_count' => count($openCases),
            'top_categories' => array_slice($categories, 0, 5),
            'is_low_sample' => $data['is_low_sample'] ?? false,
        ];

        return "Por favor genera el análisis gerencial y narrativo para el siguiente equipo de supervisión basándote exclusivamente en estos datos consolidados:\n\n"
            .json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\n\nResponde ÚNICAMENTE con un JSON válido con la estructura solicitada.";
    }

    /**
     * Build system instruction for the AI narrative generation.
     *
     * @param  array<string, array<string, mixed>>|null  $goals
     */
    protected function buildSystemInstruction(?array $goals = null): string
    {
        return 'Eres un Director Ejecutivo de Operaciones y Calidad de Clientes (Customer Experience y Voice of Customer). '
            ."Tu objetivo es generar un análisis de desempeño gerencial riguroso, formal, constructivo y fundamentado estrictamente en los datos provistos para el reporte en PDF de un equipo de supervisores.\n"
            ."REGLAS OBLIGATORIAS:\n"
            ."1. NUNCA inventes números, porcentajes o métricas. Utiliza exclusivamente las cifras del JSON.\n"
            ."2. El tono debe ser ejecutivo, analítico, claro y en español formal.\n"
            ."3. En 'agent_reviews', DEBES incluir OBLIGATORIAMENTE una entrada para CADA UNO de los colaboradores presentes en el JSON de entrada, SIN OMITIR A NINGUNO. Copia exactamente su 'agent_name' y 'agent_bms'.\n"
            ."4. Devuelve OBLIGATORIAMENTE un JSON válido sin texto previo ni posterior, con la siguiente estructura:\n"
            ."{\n"
            .'  "executive_summary": "Texto en prosa resumiendo desempeño general, volumen y cumplimiento de metas.",'."\n"
            .'  "team_strengths": ["Fortaleza 1 con datos concretos", "Fortaleza 2..."],'."\n"
            .'  "team_risks": ["Riesgo o brecha 1", "Riesgo 2..."],'."\n"
            .'  "agent_reviews": [\n'
            ."    {\n"
            .'      "agent_name": "Nombre exacto del agente según el JSON",\n'
            .'      "agent_bms": "Código BMS",\n'
            .'      "assessment": "Evaluación objetiva del agente basada en su volumen y métricas cuantitativas.",\n'
            .'      "action": "Recomendación específica de coaching o acompañamiento operativo.",\n'
            .'      "verbatim_analysis": "Diagnóstico cualitativo de la voz del cliente analizando los comentarios recibidos (qué valoran los promotores y causas raíz de quejas en detractores).",\n'
            .'      "strengths": ["Punto fuerte destacado por clientes en sus comentarios"],\n'
            .'      "friction_points": ["Motivo o causa raíz recurrente de fricción/detracción"]\n'
            ."    }\n"
            ."  ],\n"
            .'  "recommended_actions": ["Acción operativa prioritaria 1", "Acción 2..."],'."\n"
            .'  "data_quality_notes": "Nota metodológica sobre representatividad muestral y confiabilidad de los datos."\n'
            ."}\n\n"
            .KpiGoal::getFormattedContext($goals);
    }

    /**
     * Extract and decode JSON from the model's text response.
     *
     * @return array<string, mixed>|null
     */
    protected function extractJsonPayload(string $rawContent): ?array
    {
        if (empty($rawContent)) {
            return null;
        }

        // Strip markdown fences ```json ... ``` if present
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $rawContent, $matches)) {
            $rawContent = $matches[1];
        } else {
            // Find first '{' and last '}'
            $firstBrace = strpos($rawContent, '{');
            $lastBrace = strrpos($rawContent, '}');
            if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                $rawContent = substr($rawContent, $firstBrace, $lastBrace - $firstBrace + 1);
            }
        }

        $decoded = json_decode($rawContent, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Check whether decoded JSON payload contains required keys and types.
     *
     * @param  array<string, mixed>|null  $payload
     */
    protected function isValidNarrativePayload(?array $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        $requiredKeys = [
            'executive_summary',
            'team_strengths',
            'team_risks',
            'agent_reviews',
            'recommended_actions',
            'data_quality_notes',
        ];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        return is_string($payload['executive_summary'])
            && is_array($payload['team_strengths'])
            && is_array($payload['team_risks'])
            && is_array($payload['agent_reviews'])
            && is_array($payload['recommended_actions'])
            && is_string($payload['data_quality_notes']);
    }

    /**
     * Build deterministic narrative review for a single agent from their metrics and verbatims.
     *
     * @param  array<string, mixed>  $ar
     * @return array{
     *     agent_name: string,
     *     agent_bms: string,
     *     assessment: string,
     *     action: string,
     *     verbatim_analysis: string,
     *     strengths: array<string>,
     *     friction_points: array<string>
     * }
     */
    public function buildSingleAgentReview(array $ar): array
    {
        $aName = $ar['agent_name'] ?? 'Agente';
        $aBms = (string) ($ar['agent_bms'] ?? '');
        $aVol = $ar['volume'] ?? 0;
        $aNps = $ar['nps'] !== null ? sprintf('%+.2f', $ar['nps']) : 'N/D';
        $aCsat = $ar['csat'] !== null ? sprintf('%.1f%%', $ar['csat'] * 100) : 'N/D';
        $aProf = $ar['professionalism'] !== null ? sprintf('%.1f%%', $ar['professionalism'] * 100) : 'N/D';
        $aStatus = $ar['status'] ?? 'on_target';
        $verbatims = $ar['verbatims'] ?? [];
        $topCats = $ar['top_categories'] ?? [];

        $assessment = "Volumen: {$aVol} encuestas. NPS: {$aNps}, CSAT: {$aCsat}, Profesionalismo: {$aProf}. ";
        $action = 'Mantener acompañamiento y seguimiento rutinario de interacciones.';

        if ($aStatus === 'critical') {
            $assessment .= 'Desempeño en rango crítico con oportunidades prioritarias de satisfacción.';
            $action = 'Programar sesión urgente 1 a 1 de calibración de llamadas y plan de acompañamiento intensivo.';
        } elseif ($aStatus === 'warning') {
            $assessment .= 'Desempeño con oportunidad de mejora frente a metas de satisfacción.';
            $action = 'Reforzar técnicas de resolución en primer contacto y empatía.';
        } elseif ($aStatus === 'low_sample') {
            $assessment .= 'Muestra reducida para concluir tendencia estadística definitiva.';
            $action = 'Priorizar monitoreo adicional de llamadas para evaluar calidad de manera representativa.';
        } else {
            $assessment .= 'Rendimiento alineado con las metas operacionales de calidad.';
            $action = 'Reconocer buen desempeño e incentivar como referente en mejores prácticas.';
        }

        $strengths = [];
        $frictionPoints = [];
        $promoterQuotes = array_filter($verbatims, fn ($v) => ($v['sentiment'] ?? '') === 'promoter');
        $detractorQuotes = array_filter($verbatims, fn ($v) => ($v['sentiment'] ?? '') === 'detractor');
        $vCount = count($verbatims);

        if ($vCount > 0) {
            $catContext = ! empty($topCats) ? ' con principal concentración en: '.implode(', ', $topCats) : '';
            $verbatimAnalysis = "Se registraron {$vCount} comentarios de clientes{$catContext}. ";

            if (! empty($promoterQuotes)) {
                $verbatimAnalysis .= sprintf('Existen %d menciones altamente favorables que destacan la amabilidad, paciencia y disposición de servicio. ', count($promoterQuotes));
                $strengths[] = 'Reconocimiento explícito de clientes por trato cordial, disposición y cortesía.';
                if ($ar['professionalism'] !== null && $ar['professionalism'] >= 0.85) {
                    $strengths[] = 'Alto estándar en profesionalismo percibido por los usuarios.';
                }
            }

            if (! empty($detractorQuotes)) {
                $verbatimAnalysis .= sprintf('Se identificaron %d menciones detractoras enfocadas en demoras de gestión o insatisfacción con el tiempo de resolución. ', count($detractorQuotes));
                $frictionPoints[] = 'Comentarios de clientes señalando inconformidad con tiempos de respuesta o seguimiento.';
                if ($ar['csat'] !== null && $ar['csat'] < 0.80) {
                    $frictionPoints[] = 'Fricción en la resolución efectiva de consultas complejas de clientes.';
                }
            }

            if (empty($detractorQuotes) && ! empty($promoterQuotes)) {
                $verbatimAnalysis .= 'No se registraron comentarios negativos en el período evaluado.';
            }
        } else {
            $verbatimAnalysis = 'No se registraron comentarios textuales (verbatims) de clientes para este colaborador en el corte evaluado.';
            if ($aStatus === 'on_target') {
                $strengths[] = 'Métricas cuantitativas sólidas y alineadas con las metas del equipo.';
            } else {
                $frictionPoints[] = 'Falta de feedback cualitativo directo; se recomienda auditar grabaciones de interacciones.';
            }
        }

        if (empty($strengths)) {
            $strengths[] = 'Participación activa y disponibilidad en la atención diaria del canal.';
        }
        if (empty($frictionPoints)) {
            $frictionPoints[] = 'Mantener consistencia operativa en la gestión de casos atípicos.';
        }

        return [
            'agent_name' => $aName,
            'agent_bms' => $aBms,
            'assessment' => $assessment,
            'action' => $action,
            'verbatim_analysis' => $verbatimAnalysis,
            'strengths' => $strengths,
            'friction_points' => $frictionPoints,
        ];
    }

    /**
     * Merge AI-generated agent reviews with the baseline deterministic reviews
     * to guarantee that 100% of agents have a complete analysis.
     *
     * @param  array<int, array<string, mixed>>  $baselineReviews
     * @param  array<int, array<string, mixed>>  $aiReviews
     * @return array<int, array<string, mixed>>
     */
    public function mergeAgentReviews(array $baselineReviews, array $aiReviews): array
    {
        $merged = [];

        foreach ($baselineReviews as $base) {
            $baseName = (string) ($base['agent_name'] ?? '');
            $baseBms = (string) ($base['agent_bms'] ?? '');

            // Find matching AI review
            $matchedAi = $this->findMatchingAgentReview($baseName, $baseBms, $aiReviews);

            if ($matchedAi) {
                // Use AI review, falling back to baseline for any blank fields
                $assessment = ! empty(trim((string) ($matchedAi['assessment'] ?? '')))
                    ? (string) $matchedAi['assessment']
                    : (string) $base['assessment'];

                $action = ! empty(trim((string) ($matchedAi['action'] ?? '')))
                    ? (string) $matchedAi['action']
                    : (string) $base['action'];

                $verbatimAnalysis = ! empty(trim((string) ($matchedAi['verbatim_analysis'] ?? '')))
                    ? (string) $matchedAi['verbatim_analysis']
                    : (! empty(trim((string) ($matchedAi['assessment'] ?? ''))) ? (string) $matchedAi['assessment'] : (string) $base['verbatim_analysis']);

                $strengths = ! empty($matchedAi['strengths']) && is_array($matchedAi['strengths'])
                    ? $matchedAi['strengths']
                    : (array) ($base['strengths'] ?? []);

                $frictionPoints = ! empty($matchedAi['friction_points']) && is_array($matchedAi['friction_points'])
                    ? $matchedAi['friction_points']
                    : (array) ($base['friction_points'] ?? []);

                $merged[] = [
                    'agent_name' => $baseName, // Keep canonical name
                    'agent_bms' => $baseBms,   // Keep canonical BMS
                    'assessment' => $assessment,
                    'action' => $action,
                    'verbatim_analysis' => $verbatimAnalysis,
                    'strengths' => $strengths,
                    'friction_points' => $frictionPoints,
                ];
            } else {
                // AI omitted this agent: use the complete baseline review
                $merged[] = $base;
            }
        }

        return $merged;
    }

    /**
     * Find matching review from AI results using exact, BMS, or token-set matching.
     *
     * @param  array<int, array<string, mixed>>  $aiReviews
     * @return array<string, mixed>|null
     */
    protected function findMatchingAgentReview(string $targetName, string $targetBms, array $aiReviews): ?array
    {
        // 1. Exact name match
        foreach ($aiReviews as $r) {
            if (isset($r['agent_name']) && trim(mb_strtolower((string) $r['agent_name'])) === trim(mb_strtolower($targetName))) {
                return $r;
            }
        }

        // 2. BMS match (if both non-empty)
        if (! empty($targetBms)) {
            foreach ($aiReviews as $r) {
                if (! empty($r['agent_bms']) && trim((string) $r['agent_bms']) === trim($targetBms)) {
                    return $r;
                }
            }
        }

        // 3. Word tokens match (handles reversed surname/name like "Flores Gomez, Nicole D" vs "Nicole D Flores Gomez")
        $targetTokens = $this->tokenizeName($targetName);
        if (! empty($targetTokens)) {
            foreach ($aiReviews as $r) {
                if (! empty($r['agent_name'])) {
                    $aiTokens = $this->tokenizeName((string) $r['agent_name']);
                    if (! empty($aiTokens) && $targetTokens === $aiTokens) {
                        return $r;
                    }
                }
            }
        }

        // 4. Substring overlap match
        foreach ($aiReviews as $r) {
            if (! empty($r['agent_name'])) {
                $aiNameClean = $this->cleanName((string) $r['agent_name']);
                $targetNameClean = $this->cleanName($targetName);
                if (! empty($aiNameClean) && ! empty($targetNameClean)) {
                    if (str_contains($aiNameClean, $targetNameClean) || str_contains($targetNameClean, $aiNameClean)) {
                        return $r;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Clean and sort words in a person name for order-independent matching.
     */
    protected function tokenizeName(string $name): string
    {
        $clean = $this->cleanName($name);
        $words = preg_split('/\s+/', $clean);
        $words = array_filter($words, fn ($w) => strlen((string) $w) > 0);
        sort($words);

        return implode(' ', $words);
    }

    /**
     * Remove accents, punctuation, and extraneous spaces from a string.
     */
    protected function cleanName(string $str): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        if ($ascii === false) {
            $ascii = $str;
        }
        $lowered = mb_strtolower($ascii);
        $sanitized = preg_replace('/[^a-z0-9]/', ' ', $lowered);

        return trim(preg_replace('/\s+/', ' ', $sanitized));
    }
}
