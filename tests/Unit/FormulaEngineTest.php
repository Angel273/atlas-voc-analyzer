<?php

namespace Tests\Unit;

use App\Services\Metrics\Formulas\FormulaEngine;
use InvalidArgumentException;
use Tests\TestCase;

class FormulaEngineTest extends TestCase
{
    protected FormulaEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FormulaEngine();
    }

    public function test_basic_arithmetic_with_precedence(): void
    {
        $res = $this->engine->evaluate('10 + 5 * 2');
        $this->assertEquals(20.0, $res);

        $resWithParens = $this->engine->evaluate('(10 + 5) * 2');
        $this->assertEquals(30.0, $resWithParens);
    }

    public function test_supported_functions(): void
    {
        $this->assertEquals(25.0, $this->engine->evaluate('AVG(20, 30)'));
        $this->assertEquals(50.0, $this->engine->evaluate('SUM(10, 15, 25)'));
        $this->assertEquals(3.0, $this->engine->evaluate('COUNT(1, 2, 3)'));
        $this->assertEquals(10.0, $this->engine->evaluate('MIN(10, 20, 30)'));
        $this->assertEquals(30.0, $this->engine->evaluate('MAX(10, 20, 30)'));
        $this->assertEquals(3.14, $this->engine->evaluate('ROUND(3.14159, 2)'));
    }

    public function test_division_by_zero_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero in formula calculation.');

        $this->engine->evaluate('100 / 0');
    }

    public function test_illegal_tokens_rejected_without_eval(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->engine->evaluate('system("ls")');
    }
}
