<?php

namespace App\Services\Metrics\Registry;

class MetricDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $sourceColumn,
        public string $aggregation, // AVG, COUNT, SUM, MIN, MAX
        public array $range, // e.g. [-1, 1] or [0, 1]
        public string $format, // percentage, score, count
        public string $description = ''
    ) {}
}
