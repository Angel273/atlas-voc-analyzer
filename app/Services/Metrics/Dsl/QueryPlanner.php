<?php

namespace App\Services\Metrics\Dsl;

use App\Services\Metrics\Registry\MetricRegistry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class QueryPlanner
{
    public function __construct(
        protected MetricRegistry $metricRegistry
    ) {}

    /**
     * Plan and build a safe Laravel Query Builder instance from validated DSL.
     */
    public function buildQuery(array $validDsl): Builder
    {
        $query = DB::table('surveys');

        // Join verbatim categories if 'category' dimension is used in group_by or filters
        $needsCategoryJoin = false;
        if (!empty($validDsl['group_by']) && in_array('category', $validDsl['group_by'], true)) {
            $needsCategoryJoin = true;
        }
        if (!empty($validDsl['filters'])) {
            foreach ($validDsl['filters'] as $f) {
                if ($f['field'] === 'category') {
                    $needsCategoryJoin = true;
                    break;
                }
            }
        }

        if ($needsCategoryJoin) {
            $query->leftJoin('verbatim_analyses', 'surveys.survey_id', '=', 'verbatim_analyses.survey_id')
                ->leftJoin('categories', 'verbatim_analyses.category_id', '=', 'categories.id');
        }

        // Apply Filters
        if (!empty($validDsl['filters'])) {
            foreach ($validDsl['filters'] as $filter) {
                if ($filter['field'] === 'category') {
                    $column = DB::raw("COALESCE(categories.name, 'Uncategorized')");
                } else {
                    $column = $this->resolveColumn($filter['field']);
                }
                $op = $filter['operator'];
                $val = $filter['value'];

                if ($op === 'in' && is_array($val)) {
                    $query->whereIn($column, $val);
                } elseif ($op === 'between' && is_array($val) && count($val) === 2) {
                    $query->whereBetween($column, [$val[0], $val[1]]);
                } else {
                    $query->where($column, $op, $val);
                }
            }
        }

        // Apply Date Range
        if (!empty($validDsl['date_range'])) {
            if (!empty($validDsl['date_range']['from'])) {
                $query->where('surveys.survey_date', '>=', $validDsl['date_range']['from']);
            }
            if (!empty($validDsl['date_range']['to'])) {
                $query->where('surveys.survey_date', '<=', $validDsl['date_range']['to']);
            }
        }

        // Apply Select & Group By
        $selects = [];
        if (!empty($validDsl['group_by'])) {
            foreach ($validDsl['group_by'] as $dim) {
                if ($dim === 'category') {
                    $selects[] = DB::raw("COALESCE(categories.name, 'Uncategorized') as category");
                    $query->groupBy(DB::raw("COALESCE(categories.name, 'Uncategorized')"));
                } else {
                    $col = $this->resolveColumn($dim);
                    $alias = $dim;
                    $selects[] = "{$col} as {$alias}";
                    $query->groupBy($col);
                }
            }
        }

        // Apply Metric Aggregation
        $metrics = $validDsl['metrics'] ?? (isset($validDsl['metric']) ? [$validDsl['metric']] : ['survey_volume']);
        foreach ($metrics as $metricKey) {
            $metricDef = $this->metricRegistry->get($metricKey);
            $agg = strtoupper($validDsl['aggregation'] ?? ($metricDef ? $metricDef->aggregation : 'AVG'));

            if ($metricKey === 'survey_volume' || !$metricDef->sourceColumn) {
                $selects[] = DB::raw("COUNT(surveys.id) as {$metricKey}");
            } elseif ($agg === 'AVG' && in_array($metricKey, ['csat', 'professionalism'], true)) {
                // VOC Top-Box satisfaction proportion: positive responses (1.0) / total, or continuous score if positive
                $col = "surveys.{$metricDef->sourceColumn}";
                $selects[] = DB::raw("AVG(CASE WHEN {$col} = 1 THEN 1.0 WHEN {$col} > 0 THEN {$col} ELSE 0.0 END) as {$metricKey}");
            } else {
                $col = "surveys.{$metricDef->sourceColumn}";
                $selects[] = DB::raw("{$agg}({$col}) as {$metricKey}");
            }
        }

        // Always include count to provide sample size / denominator
        if (!in_array('survey_volume', $metrics, true)) {
            $selects[] = DB::raw("COUNT(surveys.id) as sample_count");
        }

        // Include agent_name if grouped by agent/agent_bms and not already grouped by agent_name
        if (!empty($validDsl['group_by']) && (in_array('agent', $validDsl['group_by'], true) || in_array('agent_bms', $validDsl['group_by'], true))) {
            if (!in_array('agent_name', $validDsl['group_by'], true)) {
                $selects[] = DB::raw("MAX(surveys.agent_name) as agent_name");
            }
        }

        $query->select($selects);

        // Sorting
        if (!empty($validDsl['sort_by'])) {
            if ($validDsl['sort_by'] === 'category') {
                $query->orderBy(DB::raw("COALESCE(categories.name, 'Uncategorized')"), $validDsl['sort_order'] ?? 'desc');
            } else {
                $sortColumn = in_array($validDsl['sort_by'], $metrics, true)
                    ? $validDsl['sort_by']
                    : $this->resolveColumn($validDsl['sort_by']);
                $query->orderBy($sortColumn, $validDsl['sort_order'] ?? 'desc');
            }
        } elseif (!empty($validDsl['group_by'])) {
            $primaryMetric = $metrics[0] ?? 'sample_count';
            $query->orderByDesc($primaryMetric);
        }

        // Limit
        if (!empty($validDsl['limit'])) {
            $query->limit((int) $validDsl['limit']);
        }

        return $query;
    }

    protected function resolveColumn(string $dimension): string
    {
        return match (strtolower($dimension)) {
            'agent', 'agent_bms' => 'surveys.agent_bms',
            'agent_name' => 'surveys.agent_name',
            'supervisor' => 'surveys.supervisor',
            'survey_date' => 'surveys.survey_date',
            'wave' => 'surveys.wave',
            'tenure', 'tenure_days' => 'surveys.tenure_days',
            'category' => 'categories.name',
            default => "surveys.{$dimension}",
        };
    }
}
