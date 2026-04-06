<?php

namespace Allocation;

class Allocator
{
    /**
     * Allocate an amount (in pennies) among shareholders proportional to their
     * integer weights, correcting any rounding errors from previous allocations.
     *
     * @param int   $amount              The new amount to distribute (pennies).
     * @param int[] $weights             Integer weight (shares) per shareholder.
     * @param int[] $previousAllocations Total pennies already allocated to each
     *                                   shareholder (same order as $weights).
     *
     * @return int[] New allocation per shareholder (pennies). The sum of these
     *               values equals $amount exactly.
     */
    public static function allocate(
        $amount,
        array $weights,
        array $previousAllocations = array(),
        $allowNegative = false
    ) {
        $count = count($weights);

        if ($count === 0) {
            return array();
        }

        if (count($previousAllocations) === 0) {
            $previousAllocations = array_fill(0, $count, 0);
        }

        if (count($previousAllocations) !== $count) {
            throw new \InvalidArgumentException(
                'weights and previousAllocations must have the same length'
            );
        }

        $totalWeight = array_sum($weights);

        if ($totalWeight <= 0) {
            throw new \InvalidArgumentException('Total weight must be positive');
        }

        foreach ($weights as $w) {
            if ($w < 0) {
                throw new \InvalidArgumentException('Weights must be non-negative');
            }
        }

        $newAllocations = self::computeAllocations($amount, $weights, $previousAllocations);

        if (!$allowNegative) {
            $newAllocations = self::clampNegatives($amount, $weights, $previousAllocations, $newAllocations);
        }

        return $newAllocations;
    }

    private static function computeAllocations($amount, array $weights, array $previousAllocations)
    {
        $count = count($weights);
        $totalWeight = array_sum($weights);
        $previousTotal = array_sum($previousAllocations);
        $grandTotal = $previousTotal + $amount;

        $floored = array();
        $remainders = array();

        for ($i = 0; $i < $count; $i++) {
            $exact = ($weights[$i] / $totalWeight) * $grandTotal;
            $floored[$i] = (int) floor($exact);
            $remainders[$i] = $exact - $floored[$i];
        }

        $flooredSum = array_sum($floored);
        $leftover = $grandTotal - $flooredSum;

        $indices = range(0, $count - 1);
        usort($indices, function ($a, $b) use ($remainders) {
            $diff = $remainders[$b] - $remainders[$a];
            if ($diff > 0) return 1;
            if ($diff < 0) return -1;
            return $a - $b;
        });

        $correctCumulative = $floored;
        for ($j = 0; $j < $leftover; $j++) {
            $correctCumulative[$indices[$j]]++;
        }

        $newAllocations = array();
        for ($i = 0; $i < $count; $i++) {
            $newAllocations[$i] = $correctCumulative[$i] - $previousAllocations[$i];
        }

        return $newAllocations;
    }

    private static function clampNegatives($amount, array $weights, array $previousAllocations, array $newAllocations)
    {
        $count = count($weights);

        // Iteratively clamp negatives to zero and redistribute among the rest.
        // Each iteration may produce new negatives, so loop until stable.
        while (true) {
            $hasNegative = false;
            $clamped = array_fill(0, $count, false);
            $remainingAmount = $amount;
            $remainingWeights = $weights;

            for ($i = 0; $i < $count; $i++) {
                if ($newAllocations[$i] < 0) {
                    $hasNegative = true;
                    $clamped[$i] = true;
                    $newAllocations[$i] = 0;
                    $remainingWeights[$i] = 0;
                }
            }

            if (!$hasNegative) {
                break;
            }

            // Redistribute: the clamped shareholders get 0, the rest
            // share the full $amount with adjusted previous allocations.
            $adjustedPrev = array();
            for ($i = 0; $i < $count; $i++) {
                $adjustedPrev[$i] = $clamped[$i] ? 0 : $previousAllocations[$i];
                if ($clamped[$i]) {
                    $remainingAmount -= 0; // clamped get 0
                }
            }

            $newAllocations = self::computeAllocations($remainingAmount, $remainingWeights, $adjustedPrev);

            // Force clamped shareholders back to 0
            for ($i = 0; $i < $count; $i++) {
                if ($clamped[$i]) {
                    $newAllocations[$i] = 0;
                }
            }
        }

        return $newAllocations;
    }
}
