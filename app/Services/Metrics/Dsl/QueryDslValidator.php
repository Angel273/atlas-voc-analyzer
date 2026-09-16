<?php

namespace App\Services\Metrics\Dsl;

use App\Services\Metrics\Registry\MetricRegistry;
use InvalidArgumentException;

class QueryDslValidator
{
    public const ALLOWED_DIMENSIONS = [
        'agent' => 'agent_name',
        'agent_bms' => 'agent_bms',
        'agent_name' => 'agent_name',
        'supervisor' => 'supervisor',
        'survey_date' => 'survey_date',
        'wave' => 'wave',
        'tenure' => 'tenure_days',
        'tenure_days' => 'tenure_days',
        'category' => 'category',
    ];

    public const ALLOWED_OPERATORS = ['=', '!=', '<>', '>', '<', '>=', '<=', 'in', 'between', 'like'];

    public const ALLOWED_AGGREGATIONS = ['avg', 'count', 'sum', 'min', 'max'];

    public function __construct(
        protected MetricRegistry $metricRegistry
    ) {}

    /**
     * Validate and normalize a Query DSL payload.
     * Throws InvalidArgumentException on allowlist failure.
     */
    public function validate(array $dsl): array
    {
        // 1. Validate Metric
        if (isset($dsl['metric'])) {
            $metricKey = strtolower((string) $dsl['metric']);
            if (! $this->metricRegistry->has($metricKey)) {
                throw new InvalidArgumentException("Disallowed or unknown metric: '{$dsl['metric']}'");
            }
            $dsl['metric'] = $metricKey;
        }

        // Multiple metrics support (e.g. for charts)
        if (isset($dsl['metrics']) && is_array($dsl['metrics'])) {
            $validMetrics = [];
            foreach ($dsl['metrics'] as $m) {
                $metricKey = strtolower((string) $m);
                if (! $this->metricRegistry->has($metricKey)) {
                    throw new InvalidArgumentException("Disallowed or unknown metric in list: '{$m}'");
                }
                $validMetrics[] = $metricKey;
            }
            $dsl['metrics'] = $validMetrics;
        }

        // 2. Validate Aggregation
        if (isset($dsl['aggregation'])) {
            $agg = strtolower((string) $dsl['aggregation']);
            if (! in_array($agg, self::ALLOWED_AGGREGATIONS, true)) {
                throw new InvalidArgumentException("Disallowed aggregation: '{$dsl['aggregation']}'");
            }
            $dsl['aggregation'] = $agg;
        }

        // 3. Validate Group By / Dimensions
        if (isset($dsl['group_by'])) {
            $groupBy = is_array($dsl['group_by']) ? $dsl['group_by'] : [$dsl['group_by']];
            $normalizedGroupBy = [];
            foreach ($groupBy as $dim) {
                $dimKey = strtolower((string) $dim);
                if (! array_key_exists($dimKey, self::ALLOWED_DIMENSIONS)) {
                    throw new InvalidArgumentException("Disallowed dimension: '{$dim}'");
                }
                $normalizedGroupBy[] = $dimKey;
            }
            $dsl['group_by'] = $normalizedGroupBy;
        }

        // 4. Validate Filters
        if (isset($dsl['filters']) && is_array($dsl['filters'])) {
            foreach ($dsl['filters'] as &$filter) {
                if (! isset($filter['field'])) {
                    throw new InvalidArgumentException("Filter missing required 'field' property.");
                }
                $field = strtolower((string) $filter['field']);
                if (! array_key_exists($field, self::ALLOWED_DIMENSIONS)) {
                    throw new InvalidArgumentException("Filter field '{$filter['field']}' is not in allowlisted dimensions.");
                }
                $filter['field'] = $field;

                $op = strtolower((string) ($filter['operator'] ?? '='));
                if (! in_array($op, self::ALLOWED_OPERATORS, true)) {
                    throw new InvalidArgumentException("Disallowed filter operator: '{$filter['operator']}'");
                }
                $filter['operator'] = $op;
            }
        }

        // 5. Validate Date Range
        if (isset($dsl['date_range'])) {
            if (! is_array($dsl['date_range'])) {
                throw new InvalidArgumentException("date_range must be an object with optional 'from' and 'to' strings.");
            }
            if (isset($dsl['date_range']['from']) && ! strtotime($dsl['date_range']['from'])) {
                throw new InvalidArgumentException("Invalid 'from' date format in date_range.");
            }
            if (isset($dsl['date_range']['to']) && ! strtotime($dsl['date_range']['to'])) {
                throw new InvalidArgumentException("Invalid 'to' date format in date_range.");
            }
        }

        // 6. Validate Limit & Sort
        if (isset($dsl['limit'])) {
            $dsl['limit'] = max(1, min(1000, (int) $dsl['limit']));
        }
        if (isset($dsl['sort_order'])) {
            $dsl['sort_order'] = strtolower((string) $dsl['sort_order']) === 'desc' ? 'desc' : 'asc';
        }

        return $dsl;
    }
}
