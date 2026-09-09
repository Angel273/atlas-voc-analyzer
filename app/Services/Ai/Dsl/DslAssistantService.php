<?php

namespace App\Services\Ai\Dsl;

use App\Services\Ai\Contracts\AiProvider;
use App\Services\Metrics\Dsl\QueryDslValidator;
use App\Services\Metrics\Registry\MetricRegistry;
use Illuminate\Support\Str;

class DslAssistantService
{
    public function __construct(
        protected AiProvider $aiProvider,
        protected QueryDslValidator $validator,
        protected MetricRegistry $metricRegistry
    ) {}

    /**
     * Generate a Query DSL proposal and explanation based on natural language input.
     *
     * @return array{
     *     content: string,
     *     dsl: array|null,
     *     dsl_valid: bool,
     *     validation_error: string|null,
     *     suggested_tool: array|null,
     *     tokens_used: int
     * }
     */
    public function generateDslProposal(string $userMessage, array $conversationHistory = []): array
    {
        $systemInstruction = "Eres el 'Arquitecto Experto de Query DSL de ATLAS VOC'. "
            .'Tu único propósito es ayudar a administradores y analistas a construir consultas seguras y deterministicas usando el Query DSL de la plataforma ATLAS VOC. '
            ."REGLAS Y RESTRICCIONES DEL SISTEMA (ESTRICTAS):\n"
            ."1. Métricas permitidas (metric): 'nps', 'csat', 'professionalism', 'survey_volume'.\n"
            ."2. Agregaciones permitidas (aggregation): 'avg', 'count', 'sum', 'min', 'max'. Para NPS, CSAT y Professionalism suele ser 'avg'. Para survey_volume es 'count' o 'sum'.\n"
            ."3. Dimensiones permitidas (group_by y campo en filters): 'agent', 'supervisor', 'wave', 'tenure', 'category', 'survey_date'. (No uses 'team' o nombres no permitidos, usa siempre 'supervisor' o 'agent').\n"
            ."4. Operadores de filtro permitidos (operator): '=', '!=', '>', '<', '>=', '<=', 'in', 'between', 'like'.\n"
            ."5. Rango de fechas (date_range): objeto opcional con claves 'from' y 'to' (formato YYYY-MM-DD).\n"
            ."6. Límite (limit): entero opcional (1 a 1000). Orden (sort_order): 'desc' o 'asc'.\n"
            ."INSTRUCCIÓN DE FORMATO:\n"
            .'Explica brevemente y de forma sobria en español cómo se construye la consulta para satisfacer la necesidad del usuario. '
            .'Obligatoriamente incluye el bloque JSON de la consulta Query DSL delimitado exactamente con triple comilla invertida ```json ... ```. '
            ."Ejemplo de bloque JSON:\n"
            ."```json\n"
            ."{\n"
            .'  "metric": "nps",'."\n"
            .'  "aggregation": "avg",'."\n"
            .'  "group_by": ["supervisor"],'."\n"
            .'  "filters": ['."\n"
            .'    {"field": "wave", "operator": "=", "value": "Wave 1"}'."\n"
            ."  ],\n"
            .'  "limit": 10,'."\n"
            .'  "sort_order": "desc"'."\n"
            ."}\n"
            .'```';

        $messages = [];
        foreach ($conversationHistory as $msg) {
            $messages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        // Call AI Provider
        $response = $this->aiProvider->generate($messages, [], [
            'system_instruction' => $systemInstruction,
        ]);

        $rawContent = (string) ($response['content'] ?? '');
        $tokensUsed = (int) ($response['tokens_used'] ?? 60);

        // Extract JSON block from response
        $dsl = $this->extractJsonDsl($rawContent);

        // If no JSON block found in AI response (e.g. mock provider or conversational reply),
        // fallback to intelligent intent heuristic builder
        if (! $dsl) {
            $dsl = $this->buildFallbackDslFromIntent($userMessage);
            if ($dsl && empty(trim($rawContent))) {
                $rawContent = "He generado la consulta Query DSL óptima basada en tu solicitud:\n\n"
                    ."```json\n"
                    .json_encode($dsl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
                    ."```\n\n"
                    .'Puedes ejecutar la prueba en tiempo real o cargar esta consulta directamente como una nueva Tool DSL.';
            } elseif ($dsl && ! str_contains($rawContent, '```json')) {
                $rawContent .= "\n\n```json\n".json_encode($dsl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n```";
            }
        }

        $dslValid = false;
        $validationError = null;

        if ($dsl) {
            try {
                $dsl = $this->validator->validate($dsl);
                $dslValid = true;
            } catch (\Throwable $e) {
                $validationError = $e->getMessage();
                // Attempt auto-repair
                $dsl = $this->repairDsl($dsl);
                try {
                    $dsl = $this->validator->validate($dsl);
                    $dslValid = true;
                    $validationError = null;
                } catch (\Throwable $e2) {
                    $validationError = $e2->getMessage();
                }
            }
        }

        $suggestedTool = null;
        if ($dslValid && $dsl) {
            $suggestedTool = $this->buildSuggestedToolMetadata($dsl, $userMessage);
        }

        return [
            'content' => $rawContent,
            'dsl' => $dsl,
            'dsl_valid' => $dslValid,
            'validation_error' => $validationError,
            'suggested_tool' => $suggestedTool,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * Extract JSON DSL payload from markdown code block.
     */
    protected function extractJsonDsl(string $text): ?array
    {
        if (preg_match('/```(?:json|dsl)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded) && (isset($decoded['metric']) || isset($decoded['metrics']))) {
                return $decoded;
            }
        }

        // Try direct JSON object in text
        if (preg_match('/(\{[\s\S]*"metric"[\s\S]*\})/', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded) && isset($decoded['metric'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Fallback heuristic builder for DSL when AI provider is Mock or offline.
     */
    protected function buildFallbackDslFromIntent(string $input): ?array
    {
        $lower = mb_strtolower($input);

        // Detect metric
        $metric = 'nps';
        if (str_contains($lower, 'csat')) {
            $metric = 'csat';
        } elseif (str_contains($lower, 'profesionalismo') || str_contains($lower, 'professionalism')) {
            $metric = 'professionalism';
        } elseif (str_contains($lower, 'volumen') || str_contains($lower, 'cantidad') || str_contains($lower, 'volume')) {
            $metric = 'survey_volume';
        }

        // Detect dimensions
        $groupBy = [];
        if (str_contains($lower, 'supervisor') || str_contains($lower, 'equipo')) {
            $groupBy[] = 'supervisor';
        }
        if (str_contains($lower, 'agente') || str_contains($lower, 'agent')) {
            $groupBy[] = 'agent';
        }
        if (str_contains($lower, 'categor') || str_contains($lower, 'verbatim')) {
            $groupBy[] = 'category';
        }
        if (str_contains($lower, 'ola') || str_contains($lower, 'wave')) {
            $groupBy[] = 'wave';
        }
        if (str_contains($lower, 'tenure') || str_contains($lower, 'antigüedad')) {
            $groupBy[] = 'tenure';
        }
        if (str_contains($lower, 'fecha') || str_contains($lower, 'dia') || str_contains($lower, 'temporal') || str_contains($lower, 'date')) {
            $groupBy[] = 'survey_date';
        }

        if (empty($groupBy)) {
            $groupBy = ['supervisor'];
        }

        // Detect filters
        $filters = [];
        if (preg_match('/wave\s*(\d+|[a-z0-9_-]+)/i', $lower, $m)) {
            $filters[] = [
                'field' => 'wave',
                'operator' => '=',
                'value' => 'Wave '.$m[1],
            ];
        }

        // Detect limit
        $limit = 10;
        if (preg_match('/top\s*(\d+)/i', $lower, $m) || preg_match('/l[ií]mite\s*(\d+)/i', $lower, $m)) {
            $limit = max(1, min(100, (int) $m[1]));
        }

        // Detect sort order
        $sortOrder = 'desc';
        if (str_contains($lower, 'peor') || str_contains($lower, 'menor') || str_contains($lower, 'bajo') || str_contains($lower, 'ascendente')) {
            $sortOrder = 'asc';
        }

        return [
            'metric' => $metric,
            'aggregation' => $metric === 'survey_volume' ? 'count' : 'avg',
            'group_by' => $groupBy,
            'filters' => $filters,
            'limit' => $limit,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Auto-repair common AI mistakes against QueryDslValidator constraints.
     */
    protected function repairDsl(array $dsl): array
    {
        // Fix metric key
        if (! empty($dsl['metric'])) {
            $dsl['metric'] = strtolower(trim((string) $dsl['metric']));
            if (! in_array($dsl['metric'], ['nps', 'csat', 'professionalism', 'survey_volume'], true)) {
                $dsl['metric'] = 'nps';
            }
        }

        // Fix dimensions
        if (! empty($dsl['group_by'])) {
            $validDims = ['supervisor', 'agent', 'wave', 'tenure', 'category', 'survey_date'];
            $newDims = [];
            foreach ((array) $dsl['group_by'] as $d) {
                $norm = strtolower(trim((string) $d));
                if ($norm === 'agent_bms' || $norm === 'agents') {
                    $norm = 'agent';
                }
                if ($norm === 'supervisors' || $norm === 'team' || $norm === 'team_leader') {
                    $norm = 'supervisor';
                }
                if ($norm === 'categories') {
                    $norm = 'category';
                }
                if ($norm === 'waves') {
                    $norm = 'wave';
                }
                if ($norm === 'date') {
                    $norm = 'survey_date';
                }

                if (in_array($norm, $validDims, true) && ! in_array($norm, $newDims, true)) {
                    $newDims[] = $norm;
                }
            }
            $dsl['group_by'] = ! empty($newDims) ? $newDims : ['supervisor'];
        }

        // Fix filters
        if (! empty($dsl['filters']) && is_array($dsl['filters'])) {
            $repairedFilters = [];
            foreach ($dsl['filters'] as $f) {
                if (! isset($f['field']) || ! isset($f['value'])) {
                    continue;
                }
                $field = strtolower(trim((string) $f['field']));
                if ($field === 'team') {
                    $field = 'supervisor';
                }
                $op = $f['operator'] ?? '=';
                if (! in_array($op, ['=', '!=', '<>', '>', '<', '>=', '<=', 'in', 'between', 'like'], true)) {
                    $op = '=';
                }

                $repairedFilters[] = [
                    'field' => $field,
                    'operator' => $op,
                    'value' => $f['value'],
                ];
            }
            $dsl['filters'] = $repairedFilters;
        }

        return $dsl;
    }

    /**
     * Build suggested Tool DSL metadata from validated DSL.
     */
    protected function buildSuggestedToolMetadata(array $dsl, string $originalPrompt): array
    {
        $metric = $dsl['metric'] ?? 'nps';
        $groupBy = ! empty($dsl['group_by']) ? implode('_', (array) $dsl['group_by']) : 'summary';

        $toolName = Str::slug("{$metric}_by_{$groupBy}", '_');
        $label = strtoupper($metric).' por '.ucwords(str_replace('_', ' ', $groupBy));

        $properties = [
            'metric' => [
                'type' => 'string',
                'enum' => ['nps', 'csat', 'professionalism', 'survey_volume'],
                'description' => "Métrica a calcular (por defecto: {$metric})",
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Número máximo de registros a devolver',
            ],
            'sort_order' => [
                'type' => 'string',
                'enum' => ['desc', 'asc'],
                'description' => 'Orden de clasificación',
            ],
        ];

        // Add filter parameter if relevant
        if (in_array('supervisor', (array) ($dsl['group_by'] ?? []), true)) {
            $properties['wave'] = [
                'type' => 'string',
                'description' => 'Filtro opcional por ola o cohorte de encuestas',
            ];
        }

        return [
            'name' => $toolName,
            'label' => $label,
            'description' => "Calcula y recupera {$label} utilizando el Query DSL unificado de ATLAS. {$originalPrompt}",
            'execution_mode' => 'dsl_query',
            'is_active' => true,
            'parameters_schema' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => ['metric'],
            ],
            'dsl_template' => [
                'metric' => $metric,
                'group_by' => $dsl['group_by'] ?? ['supervisor'],
                'default_limit' => $dsl['limit'] ?? 10,
                'sort_order' => $dsl['sort_order'] ?? 'desc',
            ],
        ];
    }
}
