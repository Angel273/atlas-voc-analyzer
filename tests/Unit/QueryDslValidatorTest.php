<?php

namespace Tests\Unit;

use App\Services\Metrics\Dsl\QueryDslValidator;
use App\Services\Metrics\Registry\MetricRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class QueryDslValidatorTest extends TestCase
{
    protected QueryDslValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new QueryDslValidator(new MetricRegistry());
    }

    public function test_valid_query_dsl_passes_validation(): void
    {
        $validDsl = [
            'metric' => 'nps',
            'aggregation' => 'avg',
            'group_by' => ['supervisor'],
            'filters' => [
                ['field' => 'wave', 'operator' => '=', 'value' => 'Wave 14'],
            ],
            'date_range' => [
                'from' => '2026-09-01',
                'to' => '2026-09-30',
            ],
            'limit' => 20,
        ];

        $result = $this->validator->validate($validDsl);
        $this->assertEquals('nps', $result['metric']);
        $this->assertEquals(['supervisor'], $result['group_by']);
    }

    public function test_disallowed_metric_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Disallowed or unknown metric: 'revenue'");

        $this->validator->validate([
            'metric' => 'revenue',
        ]);
    }

    public function test_disallowed_dimension_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Disallowed dimension: 'credit_card'");

        $this->validator->validate([
            'metric' => 'nps',
            'group_by' => ['credit_card'],
        ]);
    }

    public function test_disallowed_filter_operator_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Disallowed filter operator: 'DROP TABLE'");

        $this->validator->validate([
            'metric' => 'csat',
            'filters' => [
                ['field' => 'supervisor', 'operator' => 'DROP TABLE', 'value' => 'test'],
            ],
        ]);
    }
}
