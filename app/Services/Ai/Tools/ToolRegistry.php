<?php

namespace App\Services\Ai\Tools;

use App\Models\Category;
use App\Models\DslTool;
use App\Models\Survey;
use App\Models\User;
use App\Services\DriverAnalysis\DriverAnalysisService;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Metrics\Dsl\QueryEngine;
use App\Services\Metrics\Registry\MetricRegistry;
use App\Services\Privacy\DataMinimizerService;
use App\Services\Privacy\PseudonymService;

class ToolRegistry
{
    public function __construct(
        protected QueryEngine $queryEngine,
        protected MetricRegistry $metricRegistry,
        protected PseudonymService $pseudonymService,
        protected DataMinimizerService $dataMinimizer,
        protected ForecastEngine $forecastEngine,
        protected ?DriverAnalysisService $driverAnalysisService = null
    ) {
        $this->driverAnalysisService = $driverAnalysisService ?? app(DriverAnalysisService::class);
    }

    /**
     * Get tool definitions with JSON schema to pass to AI model.
     */
    public function getToolDefinitions(): array
    {
        try {
            $dbTools = DslTool::active()->orderBy('sort_order')->get();
            if ($dbTools->isNotEmpty()) {
                return $dbTools->map(fn (DslTool $tool) => [
                    'name' => $tool->name,
                    'description' => $tool->description,
                    'parameters' => $tool->parameters_schema,
                ])->toArray();
            }
        } catch (\Throwable $e) {
            // Fallback to hardcoded definitions if DB error or not migrated
        }

        return $this->getDefaultToolDefinitions();
    }

    /**
     * Fallback built-in tool definitions.
     */
    public function getDefaultToolDefinitions(): array
    {
        return [
            [
                'name' => 'query_data',
                'description' => 'Query aggregated Voice of Customer metrics (NPS, CSAT, professionalism, survey_volume) grouped by dimensions (supervisor, agent, wave, tenure, survey_date, category) using the secure Query DSL.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'enum' => ['nps', 'csat', 'professionalism', 'survey_volume'],
                            'description' => 'The target VOC metric to aggregate.',
                        ],
                        'aggregation' => [
                            'type' => 'string',
                            'enum' => ['avg', 'count', 'sum', 'min', 'max'],
                            'description' => 'Statistical aggregation function.',
                        ],
                        'group_by' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Dimensions to group by (supervisor, agent, wave, tenure, category, survey_date).',
                        ],
                        'filters' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'field' => ['type' => 'string'],
                                    'operator' => ['type' => 'string', 'enum' => ['=', '!=', '>', '<', '>=', '<=', 'in', 'between', 'like']],
                                    'value' => ['description' => 'Scalar value or array of values for in/between operators'],
                                ],
                                'required' => ['field', 'operator', 'value'],
                            ],
                            'description' => 'Array of filters to apply.',
                        ],
                        'date_range' => [
                            'type' => 'object',
                            'properties' => [
                                'from' => ['type' => 'string', 'description' => 'Start date YYYY-MM-DD'],
                                'to' => ['type' => 'string', 'description' => 'End date YYYY-MM-DD'],
                            ],
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum rows to return.',
                        ],
                    ],
                    'required' => ['metric'],
                ],
            ],
            [
                'name' => 'calculate_metric',
                'description' => 'Calculate an overall single metric value with sample count across optional filters.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'enum' => ['nps', 'csat', 'professionalism', 'survey_volume'],
                        ],
                        'filters' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'field' => ['type' => 'string'],
                                    'operator' => ['type' => 'string'],
                                    'value' => ['description' => 'Filter value'],
                                ],
                                'required' => ['field', 'operator', 'value'],
                            ],
                        ],
                    ],
                    'required' => ['metric'],
                ],
            ],
            [
                'name' => 'compare_metrics',
                'description' => 'Compare VOC metrics between two supervisor teams or cohorts.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'enum' => ['nps', 'csat', 'professionalism'],
                        ],
                        'dimension' => [
                            'type' => 'string',
                            'enum' => ['supervisor', 'wave', 'tenure'],
                        ],
                        'values' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'List of values or pseudonyms to compare.',
                        ],
                    ],
                    'required' => ['metric', 'dimension', 'values'],
                ],
            ],
            [
                'name' => 'get_dimension_values',
                'description' => 'Get the list of active categories or waves present in the dataset.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'dimension' => [
                            'type' => 'string',
                            'enum' => ['category', 'wave'],
                        ],
                    ],
                    'required' => ['dimension'],
                ],
            ],
            [
                'name' => 'get_metric_definition',
                'description' => 'Retrieve official metric formula, allowed value ranges, and business definitions.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'enum' => ['nps', 'csat', 'professionalism', 'survey_volume'],
                        ],
                    ],
                    'required' => ['metric'],
                ],
            ],
            [
                'name' => 'analyze_categories',
                'description' => 'Get distribution of verbatim feedback across authorized classification categories.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'supervisor_ref' => ['type' => 'string', 'description' => 'Optional supervisor pseudonym'],
                        'wave' => ['type' => 'string', 'description' => 'Optional wave filter'],
                    ],
                ],
            ],
            [
                'name' => 'run_forecast',
                'description' => 'Calculate explainable statistical forecast for a metric (Atlas automatically evaluates supported models: naive, moving_average, linear_trend, holt_trend via walk-forward validation).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => ['type' => 'string', 'enum' => ['nps', 'csat', 'professionalism']],
                        'horizon' => ['type' => 'integer', 'description' => 'Number of days/periods to forecast ahead (e.g. 7 or 14)'],
                    ],
                    'required' => ['metric'],
                ],
            ],
            [
                'name' => 'run_driver_analysis',
                'description' => 'Execute multivariant Driver Analysis to identify which variables (category, wave, supervisor, tenure, time) are statistically associated with VOC metrics (NPS via OLS linear regression, CSAT/Professionalism via Logistic regression).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => ['type' => 'string', 'enum' => ['nps', 'csat', 'professionalism']],
                        'supervisor' => ['type' => 'string', 'description' => 'Optional supervisor filter'],
                        'wave' => ['type' => 'string', 'description' => 'Optional wave filter'],
                    ],
                    'required' => ['metric'],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call with strict latency measurement, credential sanitization,
     * and governed Grounding Citations generation.
     *
     * @return array{result: array, citations: array, execution_log: array}
     */
    public function executeToolWithGrounding(string $toolName, array $arguments, string $scopeId, ?User $user = null): array
    {
        $start = hrtime(true);
        $sanitizedArgs = $this->sanitizeArguments($arguments);

        try {
            $pureResult = $this->executeTool($toolName, $arguments, $scopeId);
            $status = isset($pureResult['error']) ? 'error' : 'success';
        } catch (\Throwable $e) {
            $pureResult = ['error' => $e->getMessage()];
            $status = 'error';
        }

        $durationMs = (int) round((hrtime(true) - $start) / 1e6);
        $citations = $this->generateCitationsForTool($toolName, $arguments, $sanitizedArgs, $pureResult);

        return [
            'result' => $pureResult,
            'citations' => $citations,
            'execution_log' => [
                'tool_name' => $toolName,
                'parameters_redacted' => $sanitizedArgs,
                'result_summary' => is_array($pureResult) ? $pureResult : ['output' => $pureResult],
                'duration_ms' => $durationMs,
                'status' => $status,
                'citations' => $citations,
            ],
        ];
    }

    /**
     * Recursively sanitize sensitive keys such as passwords, tokens, API keys.
     */
    public function sanitizeArguments(array $args): array
    {
        $sanitized = [];
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'credential', 'auth', 'api_key', 'access_token'];

        foreach ($args as $k => $v) {
            $isSensitive = false;
            foreach ($sensitiveKeys as $pattern) {
                if (stripos((string) $k, $pattern) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$k] = '[REDACTADO]';
            } elseif (is_array($v)) {
                $sanitized[$k] = $this->sanitizeArguments($v);
            } else {
                $sanitized[$k] = $v;
            }
        }

        return $sanitized;
    }

    /**
     * Generate classified grounding citations based on tool results.
     */
    protected function generateCitationsForTool(string $toolName, array $rawArgs, array $sanitizedArgs, array $result): array
    {
        $citations = [];
        $queryHash = substr(md5(json_encode($sanitizedArgs)), 0, 8);

        switch ($toolName) {
            case 'query_data':
                $metric = strtoupper((string) ($rawArgs['metric'] ?? 'VOC'));
                $aggregation = $rawArgs['aggregation'] ?? 'avg';
                $groupBy = ! empty($rawArgs['group_by']) ? implode(', ', (array) $rawArgs['group_by']) : 'total';
                $resultsCount = $result['results_count'] ?? count($result['results'] ?? []);

                $citations[] = [
                    'title' => "Consulta métrica {$metric} ({$aggregation}) agrupada por [{$groupBy}]",
                    'factType' => 'calculation',
                    'resourceId' => 'metric_'.strtolower($metric),
                    'queryHash' => $queryHash,
                ];

                $citations[] = [
                    'title' => "Muestra agregada: {$resultsCount} registros analizados en dataset",
                    'factType' => 'fact',
                    'resourceId' => 'dataset_voc',
                    'queryHash' => $queryHash,
                ];
                break;

            case 'calculate_metric':
                $metric = strtoupper((string) ($rawArgs['metric'] ?? 'MÉTRICA'));
                $val = isset($result['value']) ? round((float) $result['value'], 3) : 'N/A';
                $surveys = $result['total_surveys'] ?? 0;

                $citations[] = [
                    'title' => "Cálculo puntual {$metric} = {$val} sobre {$surveys} encuestas evaluadas",
                    'factType' => 'calculation',
                    'resourceId' => 'metric_'.strtolower($metric),
                    'queryHash' => $queryHash,
                ];
                break;

            case 'compare_metrics':
                $metric = strtoupper((string) ($rawArgs['metric'] ?? 'MÉTRICA'));
                $dim = $rawArgs['dimension'] ?? 'dimensión';
                $cohortsCount = count($rawArgs['values'] ?? []);

                $citations[] = [
                    'title' => "Comparativa de {$metric} entre {$cohortsCount} cohortes en dimensión [{$dim}]",
                    'factType' => 'fact',
                    'resourceId' => 'compare_'.strtolower($metric),
                    'queryHash' => $queryHash,
                ];
                break;

            case 'analyze_categories':
                $categoriesCount = count($result['category_distribution'] ?? []);

                $citations[] = [
                    'title' => "Distribución de comentarios verbatim en {$categoriesCount} categorías clasificadas",
                    'factType' => 'structure',
                    'resourceId' => 'category_distribution',
                    'queryHash' => $queryHash,
                ];
                break;

            case 'run_forecast':
                $metric = strtoupper((string) ($result['metric'] ?? 'NPS'));
                $model = $result['model'] ?? 'linear_trend';
                $status = $result['status'] ?? 'completed';
                $mae = isset($result['mae']) ? round((float) $result['mae'], 4) : 'N/A';
                $horizon = $result['horizon_days'] ?? 7;

                $citations[] = [
                    'title' => "Proyección predictiva {$metric} (Estado: {$status}, Modelo: {$model}, MAE: {$mae}, Horizonte: {$horizon}d)",
                    'factType' => 'calculation',
                    'resourceId' => 'forecast_'.strtolower($metric),
                    'queryHash' => $queryHash,
                ];
                break;

            case 'run_driver_analysis':
                $metric = strtoupper((string) ($result['target_metric'] ?? 'NPS'));
                $sample = $result['sample_size'] ?? 0;
                $driversCount = count($result['drivers'] ?? []);

                $citations[] = [
                    'title' => "Análisis multivariado de drivers {$metric} ({$sample} encuestas, {$driversCount} factores)",
                    'factType' => 'calculation',
                    'resourceId' => 'drivers_'.strtolower($metric),
                    'queryHash' => $queryHash,
                ];
                break;

            case 'get_dimension_values':
                $dim = $rawArgs['dimension'] ?? 'dimensión';
                $count = count($result['values'] ?? []);

                $citations[] = [
                    'title' => "Catálogo de valores disponibles para [{$dim}] ({$count} registros válidos)",
                    'factType' => 'structure',
                    'resourceId' => 'dim_'.strtolower($dim),
                    'queryHash' => $queryHash,
                ];
                break;

            case 'get_metric_definition':
                $metric = strtoupper((string) ($rawArgs['metric'] ?? 'MÉTRICA'));
                $label = $result['label'] ?? $metric;

                $citations[] = [
                    'title' => "Definición y rangos oficiales para {$label} ({$metric})",
                    'factType' => 'interpretation',
                    'resourceId' => 'definition_'.strtolower($metric),
                    'queryHash' => $queryHash,
                ];
                break;

            default:
                $citations[] = [
                    'title' => "Ejecución técnica de herramienta: {$toolName}",
                    'factType' => 'fact',
                    'resourceId' => $toolName,
                    'queryHash' => $queryHash,
                ];
                break;
        }

        return $citations;
    }

    /**
     * Execute a tool call safely, translating pseudonyms, enforcing Query DSL, and minimizing results.
     */
    public function executeTool(string $toolName, array $arguments, string $scopeId): array
    {
        // Translate any pseudonyms in arguments back to internal IDs for local database queries
        $arguments = $this->resolvePseudonymsInArgs($arguments, $scopeId);

        return match ($toolName) {
            'query_data' => $this->handleQueryData($arguments, $scopeId),
            'calculate_metric' => $this->handleCalculateMetric($arguments, $scopeId),
            'compare_metrics' => $this->handleCompareMetrics($arguments, $scopeId),
            'get_dimension_values' => $this->handleGetDimensionValues($arguments),
            'get_metric_definition' => $this->handleGetMetricDefinition($arguments),
            'analyze_categories' => $this->handleAnalyzeCategories($arguments, $scopeId),
            'run_forecast' => $this->handleRunForecast($arguments),
            'run_driver_analysis' => $this->handleRunDriverAnalysis($arguments),
            default => $this->handleDynamicTool($toolName, $arguments, $scopeId),
        };
    }

    protected function handleDynamicTool(string $toolName, array $arguments, string $scopeId): array
    {
        $tool = DslTool::where('name', $toolName)->where('is_active', true)->first();
        if (! $tool) {
            return ['error' => "Unknown tool: {$toolName}"];
        }

        if ($tool->execution_mode === 'dsl_query') {
            return $this->handleCustomDslQuery($tool, $arguments, $scopeId);
        }

        return ['error' => "Unsupported execution mode '{$tool->execution_mode}' for tool: {$toolName}"];
    }

    public function handleCustomDslQuery(DslTool $tool, array $args, string $scopeId): array
    {
        $template = $tool->dsl_template ?? [];
        $metric = $args['metric'] ?? $template['metric'] ?? 'nps';
        $groupBy = $args['group_by'] ?? $template['group_by'] ?? ['agent'];
        $limit = $args['limit'] ?? $template['default_limit'] ?? 10;
        $sortOrder = $args['sort_order'] ?? 'desc';

        $filters = $args['filters'] ?? [];
        if (! empty($args['supervisor'])) {
            $realSup = $this->pseudonymService->resolveToInternalId($scopeId, $args['supervisor']) ?: $args['supervisor'];
            $filters[] = ['field' => 'supervisor', 'operator' => '=', 'value' => $realSup];
        }

        if (! empty($filters) && is_array($filters)) {
            $filters = $this->resolvePseudonymsInFilters($filters, $scopeId);
        }

        $dsl = [
            'metric' => $metric,
            'group_by' => (array) $groupBy,
            'filters' => $filters,
            'limit' => max(1, min(1000, (int) $limit)),
            'sort_order' => $sortOrder,
        ];

        $result = $this->queryEngine->execute($dsl);
        $minimized = $this->dataMinimizer->minimizeToolResult($result['data'], $scopeId);

        return [
            'tool' => $tool->name,
            'metric' => $metric,
            'results_count' => count($minimized),
            'results' => $minimized,
        ];
    }

    protected function resolvePseudonymsInFilters(array $filters, string $scopeId): array
    {
        foreach ($filters as &$filter) {
            if (! isset($filter['value'])) {
                continue;
            }

            if (is_string($filter['value'])) {
                $real = $this->pseudonymService->resolveToInternalId($scopeId, $filter['value']);
                if ($real) {
                    $filter['value'] = $real;
                }
            } elseif (is_array($filter['value'])) {
                $filter['value'] = array_map(function ($val) use ($scopeId) {
                    return is_string($val) ? ($this->pseudonymService->resolveToInternalId($scopeId, $val) ?: $val) : $val;
                }, $filter['value']);
            }
        }

        return $filters;
    }

    protected function handleQueryData(array $args, string $scopeId): array
    {
        if (! empty($args['filters']) && is_array($args['filters'])) {
            $args['filters'] = $this->resolvePseudonymsInFilters($args['filters'], $scopeId);
        }

        $result = $this->queryEngine->execute($args);
        $minimized = $this->dataMinimizer->minimizeToolResult($result['data'], $scopeId);

        return [
            'metric' => $args['metric'],
            'results_count' => count($minimized),
            'results' => $minimized,
        ];
    }

    protected function handleCalculateMetric(array $args, string $scopeId): array
    {
        $filters = $args['filters'] ?? [];
        if (! empty($filters) && is_array($filters)) {
            $filters = $this->resolvePseudonymsInFilters($filters, $scopeId);
        }

        $dsl = [
            'metric' => $args['metric'],
            'filters' => $filters,
        ];
        $result = $this->queryEngine->execute($dsl);
        $row = $result['data'][0] ?? null;

        return [
            'metric' => $args['metric'],
            'value' => $row ? (float) $row[$args['metric']] : null,
            'total_surveys' => $row ? (int) ($row['sample_count'] ?? $row['survey_volume'] ?? 0) : 0,
        ];
    }

    protected function handleCompareMetrics(array $args, string $scopeId): array
    {
        $values = array_map(function ($val) use ($scopeId) {
            return is_string($val) ? ($this->pseudonymService->resolveToInternalId($scopeId, $val) ?: $val) : $val;
        }, $args['values'] ?? []);

        $dsl = [
            'metric' => $args['metric'],
            'group_by' => [$args['dimension']],
            'filters' => [
                [
                    'field' => $args['dimension'],
                    'operator' => 'in',
                    'value' => $values,
                ],
            ],
        ];

        $result = $this->queryEngine->execute($dsl);
        $minimized = $this->dataMinimizer->minimizeToolResult($result['data'], $scopeId);

        return [
            'metric' => $args['metric'],
            'dimension' => $args['dimension'],
            'comparison' => $minimized,
        ];
    }

    protected function handleGetDimensionValues(array $args): array
    {
        if ($args['dimension'] === 'category') {
            return [
                'dimension' => 'category',
                'values' => Category::where('active', true)->pluck('name')->toArray(),
            ];
        }

        if ($args['dimension'] === 'wave') {
            return [
                'dimension' => 'wave',
                'values' => Survey::distinct()->whereNotNull('wave')->pluck('wave')->toArray(),
            ];
        }

        return ['values' => []];
    }

    protected function handleGetMetricDefinition(array $args): array
    {
        $def = $this->metricRegistry->get($args['metric']);
        if (! $def) {
            return ['error' => 'Metric not found'];
        }

        return [
            'key' => $def->key,
            'label' => $def->label,
            'aggregation' => $def->aggregation,
            'range' => $def->range,
            'format' => $def->format,
            'description' => $def->description,
        ];
    }

    protected function handleAnalyzeCategories(array $args, string $scopeId): array
    {
        $dsl = [
            'metric' => 'survey_volume',
            'group_by' => ['category'],
            'filters' => [],
        ];

        $supRef = $args['supervisor_ref'] ?? $args['supervisor'] ?? null;
        if (! empty($supRef)) {
            $realSup = $this->pseudonymService->resolveToInternalId($scopeId, $supRef) ?: $supRef;
            $dsl['filters'][] = ['field' => 'supervisor', 'operator' => '=', 'value' => $realSup];
        }
        if (! empty($args['wave'])) {
            $dsl['filters'][] = ['field' => 'wave', 'operator' => '=', 'value' => $args['wave']];
        }

        $result = $this->queryEngine->execute($dsl);

        return [
            'category_distribution' => $result['data'],
        ];
    }

    protected function handleRunForecast(array $args): array
    {
        $metric = $args['metric'] ?? 'nps';
        $horizon = $args['horizon'] ?? 7;

        $forecast = $this->forecastEngine->calculateForecast(
            metric: $metric,
            modelType: null, // Deterministic selection by Atlas
            horizon: (int) $horizon
        );

        return [
            'metric' => $forecast->metric,
            'status' => $forecast->status,
            'model' => $forecast->model,
            'reliability' => $forecast->reliability,
            'selection_reason' => $forecast->selection_reason,
            'mae' => $forecast->mae,
            'rmse' => $forecast->rmse,
            'r2' => $forecast->r2,
            'training_start' => $forecast->training_period_start ? $forecast->training_period_start->format('Y-m-d') : null,
            'training_end' => $forecast->training_period_end ? $forecast->training_period_end->format('Y-m-d') : null,
            'horizon_days' => $forecast->forecast_horizon,
            'candidate_comparison' => $forecast->parameters['candidate_comparison'] ?? [],
            'projections' => $forecast->results->map(fn ($r) => [
                'date' => $r->date->format('Y-m-d'),
                'forecast' => $r->forecast_value,
                'was_bounded' => $r->was_bounded,
            ])->toArray(),
        ];
    }

    protected function handleRunDriverAnalysis(array $args): array
    {
        $metric = $args['metric'] ?? 'nps';
        $filters = array_filter([
            'supervisor' => $args['supervisor'] ?? null,
            'wave' => $args['wave'] ?? null,
        ]);

        return $this->driverAnalysisService->execute($metric, $filters);
    }

    protected function resolvePseudonymsInArgs(array $args, string $scopeId): array
    {
        // Recursively inspect array and resolve pseudonyms (e.g. AGT_..., SUP_...)
        array_walk_recursive($args, function (&$val) use ($scopeId) {
            if (is_string($val) && (str_starts_with($val, 'AGT_') || str_starts_with($val, 'SUP_') || str_starts_with($val, 'REC_'))) {
                $resolved = $this->pseudonymService->resolveToInternalId($scopeId, $val);
                if ($resolved) {
                    $val = $resolved;
                }
            }
        });

        return $args;
    }
}
