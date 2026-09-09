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
                'description' => 'Query aggregated Voice of Customer metrics (NPS, CSAT, professionalism, survey_volume) grouped by dimensions (supervisor, agent, wave, tenure, survey_date, category) using the secure Query DSL.',
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
                'description' => 'Get the list of active categories or waves present in the dataset.',
                'is_builtin' => true,
                'is_active' => true,
                'execution_mode' => 'system',
                'sort_order' => 4,
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'dimension' => [
                            'type' => 'string',
                            'enum' => ['category', 'wave'],
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
                'description' => 'Get distribution of verbatim feedback across authorized classification categories.',
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
        ];

        foreach ($builtinTools as $t) {
            DslTool::updateOrCreate(
                ['name' => $t['name']],
                $t
            );
        }
    }
}
