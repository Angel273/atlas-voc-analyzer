<?php

namespace App\Services\DriverAnalysis\Support;

class MatrixHelper
{
    /**
     * Transpose matrix A (m x n -> n x m).
     */
    public static function transpose(array $A): array
    {
        $m = count($A);
        if ($m === 0) {
            return [];
        }
        $n = count($A[0]);
        $AT = [];
        for ($j = 0; $j < $n; $j++) {
            $row = [];
            for ($i = 0; $i < $m; $i++) {
                $row[] = $A[$i][$j];
            }
            $AT[] = $row;
        }

        return $AT;
    }

    /**
     * Multiply two matrices A (m x p) and B (p x n) -> (m x n).
     */
    public static function multiply(array $A, array $B): array
    {
        $m = count($A);
        if ($m === 0) {
            return [];
        }
        $p = count($A[0]);
        $n = count($B[0]);

        $C = array_fill(0, $m, array_fill(0, $n, 0.0));

        for ($i = 0; $i < $m; $i++) {
            for ($k = 0; $k < $p; $k++) {
                $aik = $A[$i][$k];
                if (abs($aik) < 1e-12) {
                    continue;
                }
                for ($j = 0; $j < $n; $j++) {
                    $C[$i][$j] += $aik * $B[$k][$j];
                }
            }
        }

        return $C;
    }

    /**
     * Multiply matrix A (m x p) by vector v (p) -> (m).
     */
    public static function multiplyVector(array $A, array $v): array
    {
        $m = count($A);
        $p = count($v);
        $res = array_fill(0, $m, 0.0);

        for ($i = 0; $i < $m; $i++) {
            $sum = 0.0;
            for ($k = 0; $k < $p; $k++) {
                $sum += $A[$i][$k] * $v[$k];
            }
            $res[$i] = $sum;
        }

        return $res;
    }

    /**
     * Invert square matrix A (n x n) using Gauss-Jordan elimination with partial pivoting.
     * With optional Tikhonov / ridge regularization on diagonal if ill-conditioned.
     */
    public static function invert(array $A, float $ridge = 1e-7): ?array
    {
        $n = count($A);
        if ($n === 0 || count($A[0]) !== $n) {
            return null;
        }

        // Augmented matrix [A | I] with slight ridge regularization for numerical stability
        $aug = [];
        for ($i = 0; $i < $n; $i++) {
            $aug[$i] = [];
            for ($j = 0; $j < $n; $j++) {
                $val = (float) $A[$i][$j];
                if ($i === $j) {
                    $val += $ridge;
                }
                $aug[$i][$j] = $val;
            }
            for ($j = 0; $j < $n; $j++) {
                $aug[$i][$n + $j] = ($i === $j) ? 1.0 : 0.0;
            }
        }

        for ($i = 0; $i < $n; $i++) {
            // Find pivot
            $maxRow = $i;
            $maxVal = abs($aug[$i][$i]);
            for ($k = $i + 1; $k < $n; $k++) {
                $absVal = abs($aug[$k][$i]);
                if ($absVal > $maxVal) {
                    $maxVal = $absVal;
                    $maxRow = $k;
                }
            }

            if ($maxVal < 1e-12) {
                // Matrix singular
                return null;
            }

            // Swap rows
            if ($maxRow !== $i) {
                $temp = $aug[$i];
                $aug[$i] = $aug[$maxRow];
                $aug[$maxRow] = $temp;
            }

            // Normalize pivot row
            $pivot = $aug[$i][$i];
            for ($j = 0; $j < 2 * $n; $j++) {
                $aug[$i][$j] /= $pivot;
            }

            // Eliminate column
            for ($k = 0; $k < $n; $k++) {
                if ($k !== $i) {
                    $factor = $aug[$k][$i];
                    if (abs($factor) > 1e-12) {
                        for ($j = 0; $j < 2 * $n; $j++) {
                            $aug[$k][$j] -= $factor * $aug[$i][$j];
                        }
                    }
                }
            }
        }

        // Extract inverse
        $inv = [];
        for ($i = 0; $i < $n; $i++) {
            $invRow = [];
            for ($j = 0; $j < $n; $j++) {
                $invRow[] = $aug[$i][$n + $j];
            }
            $inv[] = $invRow;
        }

        return $inv;
    }

    /**
     * Compute dot product of two vectors of equal length.
     */
    public static function dot(array $u, array $v): float
    {
        $sum = 0.0;
        $n = count($u);
        for ($i = 0; $i < $n; $i++) {
            $sum += $u[$i] * $v[$i];
        }

        return $sum;
    }
}
