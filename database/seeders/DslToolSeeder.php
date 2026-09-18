<?php

namespace Database\Seeders;

use App\Models\DslTool;
use Illuminate\Database\Seeder;

class DslToolSeeder extends Seeder
{
    public function run(): void
    {
        $builtinTools = [
            [
                'name' => 'query_data',
                'label' => 'Consulta Agregada Query DSL',
                'description' => 'Query aggregated Voice of Customer metrics (NPS, CSAT, professionalism, survey_volume) grouped by dimensions (supervisor, agent, wave, tenure, survey_date, category) using the secure Query DSL. Returns sample_count and percentage_of_total for grouped distributions.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 1,
                'parameters_schema' => [
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
                'dsl_template' => null,
            ],
            [
                'name' => 'calculate_metric',
                'label' => 'Cálculo Puntual de Métrica',
                'description' => 'Calculate an overall single metric value with sample count across optional filters.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 2,
                'parameters_schema' => [
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
                'dsl_template' => null,
            ],
            [
                'name' => 'compare_metrics',
                'label' => 'Comparativa de Cohortes y Equipos',
                'description' => 'Compare VOC metrics between two supervisor teams or cohorts.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 3,
                'parameters_schema' => [
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
                'dsl_template' => null,
            ],
            [
                'name' => 'get_dimension_values',
                'label' => 'Catálogo de Dimensiones Activas',
                'description' => 'Get the list of active categories, waves, supervisors, or agents present in the dataset.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 4,
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'dimension' => [
                            'type' => 'string',
                            'enum' => ['category', 'wave', 'supervisor', 'agent'],
                        ],
                    ],
                    'required' => ['dimension'],
                ],
                'dsl_template' => null,
            ],
            [
                'name' => 'get_metric_definition',
                'label' => 'Definición Oficial de Métricas',
                'description' => 'Retrieve official metric formula, allowed value ranges, and business definitions.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 5,
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'enum' => ['nps', 'csat', 'professionalism', 'survey_volume'],
                        ],
                    ],
                    'required' => ['metric'],
                ],
                'dsl_template' => null,
            ],
            [
                'name' => 'analyze_categories',
                'label' => 'Distribución de Categorías Verbatim',
                'description' => 'Get distribution of verbatim feedback across authorized classification categories, returning sample counts, total analyzed, and percentage distribution.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 6,
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'supervisor_ref' => ['type' => 'string', 'description' => 'Optional supervisor pseudonym'],
                        'wave' => ['type' => 'string', 'description' => 'Optional wave filter'],
                    ],
                ],
                'dsl_template' => null,
            ],
            [
                'name' => 'run_forecast',
                'label' => 'Modelado Predictivo de Series',
                'description' => 'Calculate statistical forecast for a metric (using deterministic models: naive, sma, ema, linear_trend).',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 7,
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => ['type' => 'string', 'enum' => ['nps', 'csat', 'professionalism']],
                        'model' => ['type' => 'string', 'enum' => ['linear_trend', 'sma', 'ema', 'naive']],
                        'horizon' => ['type' => 'integer', 'description' => 'Number of days/periods to forecast ahead'],
                    ],
                    'required' => ['metric'],
                ],
                'dsl_template' => null,
            ],
            [
                'name' => 'agent_performance_ranking',
                'label' => 'Ranking Multimétrica de Agentes',
                'description' => 'Retrieve a consolidated performance ranking of top/bottom agents across NPS, CSAT, or Professionalism with survey volume in a single query. Use this whenever the user asks for top agents or performance rankings.',
                'is_builtin' => false,
                'is_active' => true,
                'execution_mode' => 'dsl_query',
                'sort_order' => 8,
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'metric' => [
                            'type' => 'string',
                            'enum' => ['nps', 'csat', 'professionalism', 'survey_volume'],
                            'description' => 'Primary ranking metric (defaults to nps).',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Number of agents to return (e.g. 5, 10, 20).',
                        ],
                        'sort_order' => [
                            'type' => 'string',
                            'enum' => ['desc', 'asc'],
                            'description' => 'Sort descending for top performers, ascending for lowest performers.',
                        ],
                        'supervisor' => [
                            'type' => 'string',
                            'description' => 'Optional supervisor pseudonym to filter agents within a specific team.',
                        ],
                    ],
                    'required' => ['metric'],
                ],
                'dsl_template' => [
                    'group_by' => ['agent'],
                    'default_limit' => 10,
                ],
            ],
            [
                'name' => 'query_raw_data',
                'label' => 'Extracción de Datos Crudos (RAW Surveys y Verbatims)',
                'description' => 'Query individual raw survey records and customer verbatims with flexible filters (supervisor, agent, category, wave, scores, date range, verbatim keyword search). Returns sanitized JSON records with verbatim customer text, metrics, and metadata for deep qualitative and quantitative analysis.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 9,
                'parameters_schema' => [
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
                'dsl_template' => null,
            ],
        ];

        foreach ($builtinTools as $t) {
            DslTool::updateOrCreate(
                ['name' => $t['name']],
                $t
            );
        }
    }
}
