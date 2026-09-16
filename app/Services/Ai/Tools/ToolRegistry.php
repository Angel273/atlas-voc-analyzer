<?php

namespace App\Services\Ai\Tools;

use App\Models\Category;
use App\Models\DslTool;
use App\Models\KpiGoal;
use App\Models\Survey;
use App\Models\User;
use App\Services\DriverAnalysis\DriverAnalysisService;
use App\Services\Forecasting\ForecastEngine;
use App\Services\Metrics\Dsl\QueryEngine;
use App\Services\Metrics\Registry\MetricRegistry;
use App\Services\Privacy\DataMinimizerService;
use App\Services\Privacy\EntityFuzzyMatcher;
use App\Services\Privacy\PiiScrubberService;
use App\Services\Privacy\PseudonymService;

class ToolRegistry
{
    public function __construct(
        protected QueryEngine $queryEngine,
        protected MetricRegistry $metricRegistry,
        protected PseudonymService $pseudonymService,
        protected DataMinimizerService $dataMinimizer,
        protected ForecastEngine $forecastEngine,
        protected ?DriverAnalysisService $driverAnalysisService = null,
        protected ?PiiScrubberService $piiScrubberService = null,
        protected ?EntityFuzzyMatcher $entityMatcher = null
    ) {
        $this->driverAnalysisService = $driverAnalysisService ?? app(DriverAnalysisService::class);
        $this->piiScrubberService = $piiScrubberService ?? app(PiiScrubberService::class);
        $this->entityMatcher = $entityMatcher ?? app(EntityFuzzyMatcher::class);
    }

    public function resolveSupervisor(string $value, string $scopeId): string
    {
        return $this->entityMatcher->resolveSupervisor($value, $scopeId, $this->pseudonymService);
    }

    public function resolveAgent(string $value, string $scopeId): string
    {
        return $this->entityMatcher->resolveAgent($value, $scopeId, $this->pseudonymService);
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
                'description' => 'Get the list of active categories, waves, supervisors, or agents present in the dataset.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'dimension' => [
                            'type' => 'string',
                            'enum' => ['category', 'wave', 'supervisor', 'agent'],
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
            [
                'name' => 'query_raw_data',
                'description' => 'Query individual raw survey records and customer verbatims with flexible filters (supervisor, agent, category, wave, scores, date range, verbatim keyword search). Returns sanitized JSON records with verbatim customer text, metrics, and metadata for deep qualitative and quantitative analysis.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'filters' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'field' => ['type' => 'string', 'description' => 'Field name (supervisor, agent, category, wave, tenure_days, nps_score, csat_score, professionalism_score, survey_date)'],
                                    'operator' => ['type' => 'string', 'enum' => ['=', '!=', '>', '<', '>=', '<=', 'in', 'between', 'like']],
                                    'value' => ['description' => 'Scalar value or array of values for in/between operators'],
                                ],
                                'required' => ['field', 'operator', 'value'],
                            ],
                            'description' => 'Optional array of filters to narrow down the raw dataset.',
                        ],
                        'keyword' => [
                            'type' => 'string',
                            'description' => 'Optional search term to filter surveys containing specific text or keywords in their verbatim feedback.',
                        ],
                        'category' => [
                            'type' => 'string',
                            'description' => 'Optional category name filter (e.g. Customer Service, Billing & Payments, etc.).',
                        ],
                        'supervisor' => [
                            'type' => 'string',
                            'description' => 'Optional supervisor pseudonym token (e.g. SUP_...) or name.',
                        ],
                        'include_all' => [
                            'type' => 'boolean',
                            'description' => 'If true, retrieves all matching raw records uploaded to the system without truncation.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of raw records to retrieve (default 200, up to all records in dataset).',
                        ],
                        'offset' => [
                            'type' => 'integer',
                            'description' => 'Offset for pagination.',
                        ],
                        'sort_by' => [
                            'type' => 'string',
                            'enum' => ['survey_date', 'nps_score', 'csat_score', 'professionalism_score', 'tenure_days'],
                            'description' => 'Field to sort raw records by.',
                        ],
                        'sort_direction' => [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'description' => 'Sort direction (asc or desc).',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'get_kpi_goals',
                'description' => 'Retrieve active operational KPI targets and warning thresholds for VOC metrics (NPS, CSAT, Professionalism). Use this tool to verify current benchmarks and compare operational performance.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'enum' => ['all', 'nps', 'csat', 'professionalism'],
                            'description' => 'Specific metric to inspect, or "all" to retrieve all active goals',
                        ],
                    ],
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

            case 'query_raw_data':
                $returned = $result['returned_count'] ?? count($result['records'] ?? []);
                $total = $result['total_matching_records'] ?? $returned;
                $citations[] = [
                    'title' => "Extracción RAW de datos: {$returned} encuestas y verbatims analizados (de {$total} coincidentes)",
                    'factType' => 'fact',
                    'resourceId' => 'raw_surveys_dataset',
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

            case 'get_kpi_goals':
                $citations[] = [
                    'title' => 'Metas operacionales y umbrales gobernados consultados para VOC',
                    'factType' => 'fact',
                    'resourceId' => 'kpi_goals_registry',
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
            'query_raw_data' => $this->handleQueryRawData($arguments, $scopeId),
            'calculate_metric' => $this->handleCalculateMetric($arguments, $scopeId),
            'compare_metrics' => $this->handleCompareMetrics($arguments, $scopeId),
            'get_dimension_values' => $this->handleGetDimensionValues($arguments, $scopeId),
            'get_metric_definition' => $this->handleGetMetricDefinition($arguments),
            'get_kpi_goals' => $this->handleGetKpiGoals($arguments),
            'analyze_categories' => $this->handleAnalyzeCategories($arguments, $scopeId),
            'run_forecast' => $this->handleRunForecast($arguments),
            'run_driver_analysis' => $this->handleRunDriverAnalysis($arguments, $scopeId),
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
            $realSup = $this->resolveSupervisor($args['supervisor'], $scopeId);
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

            $field = strtolower((string) ($filter['field'] ?? ''));

            if (is_string($filter['value'])) {
                $val = $filter['value'];
                if ($field === 'supervisor') {
                    $filter['value'] = $this->resolveSupervisor($val, $scopeId);
                } elseif ($field === 'agent' || $field === 'agent_name' || $field === 'agent_bms') {
                    $filter['value'] = $this->resolveAgent($val, $scopeId);
                } else {
                    $real = $this->pseudonymService->resolveToInternalId($scopeId, $val);
                    if ($real) {
                        $filter['value'] = $real;
                    }
                }
            } elseif (is_array($filter['value'])) {
                $filter['value'] = array_map(function ($val) use ($scopeId, $field) {
                    if (! is_string($val)) {
                        return $val;
                    }
                    if ($field === 'supervisor') {
                        return $this->resolveSupervisor($val, $scopeId);
                    }
                    if ($field === 'agent' || $field === 'agent_name' || $field === 'agent_bms') {
                        return $this->resolveAgent($val, $scopeId);
                    }

                    return $this->pseudonymService->resolveToInternalId($scopeId, $val) ?: $val;
                }, $filter['value']);
            }
        }

        return $filters;
    }

    protected function handleQueryRawData(array $args, string $scopeId): array
    {
        $query = Survey::with(['verbatimAnalysis.category']);

        // 1. Direct filters
        if (! empty($args['category'])) {
            $catName = $args['category'];
            $query->whereHas('verbatimAnalysis.category', function ($q) use ($catName) {
                $q->where('name', $catName);
            });
        }

        if (! empty($args['supervisor'])) {
            $realSup = $this->resolveSupervisor($args['supervisor'], $scopeId);
            $query->where('supervisor', $realSup);
        }

        if (! empty($args['keyword'])) {
            $kw = $args['keyword'];
            $query->where('verbatim', 'like', "%{$kw}%");
        }

        // 2. Generic filters array
        if (! empty($args['filters']) && is_array($args['filters'])) {
            $filters = $this->resolvePseudonymsInFilters($args['filters'], $scopeId);
            foreach ($filters as $f) {
                $field = $f['field'] ?? null;
                $op = strtolower($f['operator'] ?? '=');
                $val = $f['value'] ?? null;
                if (! $field) {
                    continue;
                }

                if ($field === 'category') {
                    $query->whereHas('verbatimAnalysis.category', function ($q) use ($op, $val) {
                        if ($op === 'in' && is_array($val)) {
                            $q->whereIn('name', $val);
                        } else {
                            $q->where('name', $op === 'like' ? 'like' : '=', $op === 'like' ? "%{$val}%" : $val);
                        }
                    });

                    continue;
                }

                if ($field === 'agent') {
                    $field = 'agent_name';
                }

                if ($op === 'in' && is_array($val)) {
                    $query->whereIn($field, $val);
                } elseif ($op === 'between' && is_array($val) && count($val) >= 2) {
                    $query->whereBetween($field, [$val[0], $val[1]]);
                } elseif ($op === 'like') {
                    $query->where($field, 'like', "%{$val}%");
                } else {
                    $query->where($field, $op, $val);
                }
            }
        }

        $totalMatching = (clone $query)->count();

        // 3. Sorting
        $sortBy = $args['sort_by'] ?? 'survey_date';
        $sortDir = strtolower($args['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if (in_array($sortBy, ['survey_date', 'nps_score', 'csat_score', 'professionalism_score', 'tenure_days', 'wave'])) {
            $query->orderBy($sortBy, $sortDir);
        }

        // 4. Pagination / Limit
        $includeAll = ! empty($args['include_all']);
        if (! $includeAll) {
            $limit = isset($args['limit']) ? max(1, min(2000, (int) $args['limit'])) : 200;
            $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;
            $query->skip($offset)->take($limit);
        } else {
            // Include all (up to safe upper ceiling, e.g. 5000)
            $query->take(5000);
        }

        $surveys = $query->get();

        // 5. Sanitize, scrub PII, and pseudonymize each record
        $scrubber = $this->piiScrubberService ?? app(PiiScrubberService::class);
        $records = [];

        foreach ($surveys as $s) {
            $rawVerbatim = (string) ($s->verbatim ?? '');
            $scrubbedVerbatim = ! empty($rawVerbatim) ? $scrubber->scrubText($rawVerbatim, $scopeId) : '';

            $categoryName = $s->verbatimAnalysis?->category?->name ?? 'Uncategorized';

            $records[] = [
                'record_ref' => $this->pseudonymService->getOrCreatePseudonym($scopeId, 'survey', (string) $s->survey_id),
                'survey_date' => $s->survey_date ? $s->survey_date->format('Y-m-d') : null,
                'nps_score' => $s->nps_score !== null ? (float) $s->nps_score : null,
                'csat_score' => $s->csat_score !== null ? (float) $s->csat_score : null,
                'professionalism_score' => $s->professionalism_score !== null ? (float) $s->professionalism_score : null,
                'verbatim' => $scrubbedVerbatim,
                'category' => $categoryName,
                'supervisor_ref' => $s->supervisor ? $this->pseudonymService->getOrCreatePseudonym($scopeId, 'supervisor', (string) $s->supervisor) : null,
                'agent_ref' => $s->agent_name ? $this->pseudonymService->getOrCreatePseudonym($scopeId, 'agent', (string) $s->agent_name) : null,
                'wave' => $s->wave,
                'tenure_days' => $s->tenure_days,
            ];
        }

        return [
            'total_matching_records' => $totalMatching,
            'returned_count' => count($records),
            'records' => $records,
        ];
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
        $dimension = strtolower((string) ($args['dimension'] ?? ''));
        $values = array_map(function ($val) use ($scopeId, $dimension) {
            if (! is_string($val)) {
                return $val;
            }
            if ($dimension === 'supervisor') {
                return $this->resolveSupervisor($val, $scopeId);
            }
            if ($dimension === 'agent' || $dimension === 'agent_name') {
                return $this->resolveAgent($val, $scopeId);
            }

            return $this->pseudonymService->resolveToInternalId($scopeId, $val) ?: $val;
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

    protected function handleGetDimensionValues(array $args, string $scopeId = ''): array
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

        if ($args['dimension'] === 'supervisor') {
            $supervisors = Survey::distinct()->whereNotNull('supervisor')->pluck('supervisor')->toArray();
            $values = array_map(
                fn ($s) => ! empty($scopeId) ? $this->pseudonymService->getOrCreatePseudonym($scopeId, 'supervisor', $s) : $s,
                $supervisors
            );

            return [
                'dimension' => 'supervisor',
                'values' => array_values($values),
            ];
        }

        if ($args['dimension'] === 'agent') {
            $agents = Survey::distinct()->whereNotNull('agent_name')->pluck('agent_name')->toArray();
            $values = array_map(
                fn ($a) => ! empty($scopeId) ? $this->pseudonymService->getOrCreatePseudonym($scopeId, 'agent', $a) : $a,
                $agents
            );

            return [
                'dimension' => 'agent',
                'values' => array_values($values),
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

    protected function handleGetKpiGoals(array $args): array
    {
        $metric = $args['metric'] ?? 'all';
        $goals = KpiGoal::getGoalsMap();

        if ($metric !== 'all' && isset($goals[$metric])) {
            return [
                'success' => true,
                'goal' => $goals[$metric],
                'scale_note' => 'Scores are on a -1.0 to 1.0 scale.',
            ];
        }

        return [
            'success' => true,
            'goals' => $goals,
            'scale_note' => 'Scores are on a -1.0 to 1.0 scale (1.0 = 100%, 0.5 = 50%, -1.0 = -100%).',
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
            $realSup = $this->resolveSupervisor($supRef, $scopeId);
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

    protected function handleRunDriverAnalysis(array $args, string $scopeId = ''): array
    {
        $metric = $args['metric'] ?? 'nps';
        $supervisor = ! empty($args['supervisor']) ? $this->resolveSupervisor($args['supervisor'], $scopeId) : null;
        $filters = array_filter([
            'supervisor' => $supervisor,
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
