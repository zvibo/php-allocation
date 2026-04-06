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
    public static function allocate($amount, array $weights, array $previousAllocations = array())
    {
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

        $previousTotal = array_sum($previousAllocations);
        $grandTotal = $previousTotal + $amount;

        // Calculate the ideal (exact) cumulative allocation for each shareholder.
        // Then the new allocation = ideal cumulative - previous allocation.
        // Use the "largest remainder" method to distribute rounding residuals.
        $idealCumulative = array();
        $floored = array();
        $remainders = array();

        for ($i = 0; $i < $count; $i++) {
            $exact = ($weights[$i] / $totalWeight) * $grandTotal;
            $idealCumulative[$i] = $exact;
            $floored[$i] = (int) floor($exact);
            $remainders[$i] = $exact - $floored[$i];
        }

        // Distribute leftover pennies to shareholders with the largest remainders.
        $flooredSum = array_sum($floored);
        $leftover = $grandTotal - $flooredSum;

        // Build index array sorted by remainder descending, ties broken by index.
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

        // New allocation = correct cumulative total - what was already allocated.
        $newAllocations = array();
        for ($i = 0; $i < $count; $i++) {
            $newAllocations[$i] = $correctCumulative[$i] - $previousAllocations[$i];
        }

        return $newAllocations;
    }
}
