<?php

namespace App\Services\Metrics\Registry;

class MetricRegistry
{
    /**
     * @var array<string, MetricDefinition>
     */
    protected array $metrics = [];

    public function __construct()
    {
        $this->registerDefaultMetrics();
    }

    protected function registerDefaultMetrics(): void
    {
        $this->register(new MetricDefinition(
            key: 'nps',
            label: 'Net Promoter Score',
            sourceColumn: 'nps_score',
            aggregation: 'AVG',
            range: [-1.0, 1.0],
            format: 'score', // Can be rendered as -100 to 100 or -1.0 to 1.0 in UI
            description: 'Average Net Promoter Score across respondents (-1 to +1 scale).'
        ));

        $this->register(new MetricDefinition(
            key: 'csat',
            label: 'Customer Satisfaction',
            sourceColumn: 'csat_score',
            aggregation: 'AVG',
            range: [-1.0, 1.0],
            format: 'score',
            description: 'Customer Satisfaction Score (-1 to +1 scale).'
        ));

        $this->register(new MetricDefinition(
            key: 'professionalism',
            label: 'Professionalism Score',
            sourceColumn: 'professionalism_score',
            aggregation: 'AVG',
            range: [-1.0, 1.0],
            format: 'score',
            description: 'Customer evaluation of representative professionalism (-1 to +1 scale).'
        ));

        $this->register(new MetricDefinition(
            key: 'survey_volume',
            label: 'Survey Volume',
            sourceColumn: null,
            aggregation: 'COUNT',
            range: [0, PHP_INT_MAX],
            format: 'count',
            description: 'Total number of valid survey responses collected.'
        ));
    }

    public function register(MetricDefinition $definition): void
    {
        $this->metrics[$definition->key] = $definition;
    }

    public function get(string $key): ?MetricDefinition
    {
        return $this->metrics[strtolower($key)] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->metrics[strtolower($key)]);
    }

    /**
     * @return array<string, MetricDefinition>
     */
    public function all(): array
    {
        return $this->metrics;
    }
}
