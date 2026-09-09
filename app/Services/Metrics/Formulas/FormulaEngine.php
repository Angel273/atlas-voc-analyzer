<?php

namespace App\Services\Metrics\Formulas;

use InvalidArgumentException;

class FormulaEngine
{
    /**
     * Safely evaluate a formula string against a dataset or variables dictionary without eval().
     * Supported operators: +, -, *, /, (, )
     * Supported functions: AVG, SUM, COUNT, MIN, MAX, ROUND
     */
    public function evaluate(string $expression, array $variables = []): float
    {
        $tokens = $this->tokenize($expression);
        $pos = 0;
        $result = $this->parseExpression($tokens, $pos, $variables);

        if ($pos < count($tokens)) {
            throw new InvalidArgumentException("Unexpected token '{$tokens[$pos]}' at position {$pos}");
        }

        return (float) $result;
    }

    protected function tokenize(string $expr): array
    {
        $tokens = [];
        $length = strlen($expr);
        $i = 0;

        while ($i < $length) {
            $char = $expr[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            if (in_array($char, ['+', '-', '*', '/', '(', ')', ','], true)) {
                $tokens[] = $char;
                $i++;
                continue;
            }

            // Number
            if (ctype_digit($char) || $char === '.') {
                $num = '';
                while ($i < $length && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $num .= $expr[$i];
                    $i++;
                }
                $tokens[] = $num;
                continue;
            }

            // Word / Identifier / Function
            if (ctype_alpha($char) || $char === '_') {
                $word = '';
                while ($i < $length && (ctype_alnum($expr[$i]) || $expr[$i] === '_')) {
                    $word .= $expr[$i];
                    $i++;
                }
                $tokens[] = $word;
                continue;
            }

            throw new InvalidArgumentException("Illegal character '{$char}' in formula.");
        }

        return $tokens;
    }

    protected function parseExpression(array &$tokens, int &$pos, array $variables): float
    {
        $value = $this->parseTerm($tokens, $pos, $variables);

        while ($pos < count($tokens) && in_array($tokens[$pos], ['+', '-'], true)) {
            $op = $tokens[$pos++];
            $nextTerm = $this->parseTerm($tokens, $pos, $variables);
            if ($op === '+') {
                $value += $nextTerm;
            } else {
                $value -= $nextTerm;
            }
        }

        return $value;
    }

    protected function parseTerm(array &$tokens, int &$pos, array $variables): float
    {
        $value = $this->parseFactor($tokens, $pos, $variables);

        while ($pos < count($tokens) && in_array($tokens[$pos], ['*', '/'], true)) {
            $op = $tokens[$pos++];
            $nextFactor = $this->parseFactor($tokens, $pos, $variables);
            if ($op === '*') {
                $value *= $nextFactor;
            } else {
                if ($nextFactor == 0.0) {
                    throw new InvalidArgumentException("Division by zero in formula calculation.");
                }
                $value /= $nextFactor;
            }
        }

        return $value;
    }

    protected function parseFactor(array &$tokens, int &$pos, array $variables): float
    {
        if ($pos >= count($tokens)) {
            throw new InvalidArgumentException("Unexpected end of formula expression.");
        }

        $token = $tokens[$pos++];

        // Unary minus
        if ($token === '-') {
            return -$this->parseFactor($tokens, $pos, $variables);
        }

        // Subexpression in parentheses
        if ($token === '(') {
            $val = $this->parseExpression($tokens, $pos, $variables);
            if ($pos >= count($tokens) || $tokens[$pos++] !== ')') {
                throw new InvalidArgumentException("Missing closing parenthesis in formula.");
            }
            return $val;
        }

        // Numeric literal
        if (is_numeric($token)) {
            return (float) $token;
        }

        // Built-in Functions: AVG, SUM, COUNT, MIN, MAX, ROUND
        $upperToken = strtoupper($token);
        if (in_array($upperToken, ['AVG', 'SUM', 'COUNT', 'MIN', 'MAX', 'ROUND'], true)) {
            if ($pos >= count($tokens) || $tokens[$pos++] !== '(') {
                throw new InvalidArgumentException("Expected '(' after function {$upperToken}");
            }

            $args = [];
            if ($tokens[$pos] !== ')') {
                while (true) {
                    $args[] = $this->parseExpression($tokens, $pos, $variables);
                    if ($pos < count($tokens) && $tokens[$pos] === ',') {
                        $pos++;
                    } else {
                        break;
                    }
                }
            }

            if ($pos >= count($tokens) || $tokens[$pos++] !== ')') {
                throw new InvalidArgumentException("Expected ')' closing function {$upperToken}");
            }

            return match ($upperToken) {
                'SUM' => empty($args) ? 0.0 : array_sum($args),
                'COUNT' => (float) count($args),
                'AVG' => empty($args) ? 0.0 : array_sum($args) / count($args),
                'MIN' => empty($args) ? 0.0 : min($args),
                'MAX' => empty($args) ? 0.0 : max($args),
                'ROUND' => count($args) >= 2 ? round($args[0], (int) $args[1]) : (empty($args) ? 0.0 : round($args[0])),
            };
        }

        // Variable Lookup
        if (array_key_exists($token, $variables)) {
            $varVal = $variables[$token];
            if (is_array($varVal)) {
                return (float) (array_sum($varVal) / max(1, count($varVal)));
            }
            return (float) $varVal;
        }

        throw new InvalidArgumentException("Unknown identifier or variable '{$token}' in formula.");
    }
}
