<?php

use Allocation\Allocator;
use PHPUnit\Framework\TestCase;

class AllocatorTest extends TestCase
{
    // ---------------------------------------------------------------
    //  Basic proportional allocation (no previous allocations)
    // ---------------------------------------------------------------

    public function testEqualWeightsEvenSplit()
    {
        // 100 pennies split equally among 4 shareholders
        $result = Allocator::allocate(100, array(1, 1, 1, 1));
        $this->assertEquals(array(25, 25, 25, 25), $result);
        $this->assertEquals(100, array_sum($result));
    }

    public function testEqualWeightsUnevenAmount()
    {
        // 101 pennies among 4 equal shareholders — one gets an extra penny
        $result = Allocator::allocate(101, array(1, 1, 1, 1));
        $this->assertEquals(101, array_sum($result));
        // Each gets 25 or 26
        foreach ($result as $v) {
            $this->assertTrue($v === 25 || $v === 26);
        }
    }

    public function testUnequalWeights()
    {
        // 100 pennies: weights 1, 2, 7 → expect 10, 20, 70
        $result = Allocator::allocate(100, array(1, 2, 7));
        $this->assertEquals(array(10, 20, 70), $result);
        $this->assertEquals(100, array_sum($result));
    }

    public function testSingleShareholder()
    {
        $result = Allocator::allocate(999, array(5));
        $this->assertEquals(array(999), $result);
    }

    public function testZeroAmount()
    {
        $result = Allocator::allocate(0, array(3, 7));
        $this->assertEquals(array(0, 0), $result);
    }

    public function testEmptyWeights()
    {
        $result = Allocator::allocate(100, array());
        $this->assertEquals(array(), $result);
    }

    // ---------------------------------------------------------------
    //  Penny-rounding edge cases
    // ---------------------------------------------------------------

    public function testThreeWaySplitRoundingOnePenny()
    {
        // 1 penny among 3 equal shareholders
        $result = Allocator::allocate(1, array(1, 1, 1));
        $this->assertEquals(1, array_sum($result));
        $this->assertEquals(1, max($result));
        $this->assertEquals(0, min($result));
    }

    public function testThreeWaySplitTenPennies()
    {
        // 10 / 3 = 3.333... → two get 3, one gets 4
        $result = Allocator::allocate(10, array(1, 1, 1));
        $this->assertEquals(10, array_sum($result));
        sort($result);
        $this->assertEquals(array(3, 3, 4), $result);
    }

    public function testLargeNumberOfShareholders()
    {
        $weights = array_fill(0, 100, 1);
        $result = Allocator::allocate(1003, $weights);
        $this->assertEquals(1003, array_sum($result));
        foreach ($result as $v) {
            $this->assertTrue($v === 10 || $v === 11);
        }
    }

    // ---------------------------------------------------------------
    //  Previous allocations — healing penny errors
    // ---------------------------------------------------------------

    public function testPreviousAllocationsCorrectNoPennyError()
    {
        // Previous allocation was perfectly correct: 50/50 of 100
        // Now allocating 100 more → each gets 50
        $result = Allocator::allocate(100, array(1, 1), array(50, 50));
        $this->assertEquals(array(50, 50), $result);
        $this->assertEquals(100, array_sum($result));
    }

    public function testPreviousAllocationHealsPennyError()
    {
        // Weights 1:2, previous total was 10 → correct would be 3, 7
        // But previous was 4, 6 (penny error: shareholder 0 got +1, shareholder 1 got -1)
        // New amount 30 → grand total 40 → correct cumulative: 13, 27
        // New allocation: 13-4=9, 27-6=21
        $result = Allocator::allocate(30, array(1, 2), array(4, 6));
        $this->assertEquals(array(9, 21), $result);
        $this->assertEquals(30, array_sum($result));
    }

    public function testPreviousAllocationHealsMultiplePennyErrors()
    {
        // Weights 1:1:1, previous total 10 → correct would be 3,3,4 or similar
        // Previous was 2, 5, 3 (errors: -1, +2, -1 ish)
        // New amount 20 → grand total 30 → correct cumulative: 10, 10, 10
        // New: 10-2=8, 10-5=5, 10-3=7
        $result = Allocator::allocate(20, array(1, 1, 1), array(2, 5, 3));
        $this->assertEquals(array(8, 5, 7), $result);
        $this->assertEquals(20, array_sum($result));

        // Verify cumulative is correct
        $cumulative = array(2 + 8, 5 + 5, 3 + 7);
        $this->assertEquals(array(10, 10, 10), $cumulative);
    }

    public function testZeroPreviousAllocationsDefaultBehavior()
    {
        // Omitting previousAllocations should behave like all zeros
        $withExplicit = Allocator::allocate(100, array(1, 3), array(0, 0));
        $withDefault  = Allocator::allocate(100, array(1, 3));
        $this->assertEquals($withExplicit, $withDefault);
    }

    public function testNegativeNewAllocationToCorrectOverpayment()
    {
        // Weight 1:1, previous was 60, 40 of 100 → correct 50, 50
        // New amount 0 → grand total 100 → correct cumulative: 50, 50
        // New: 50-60 = -10, 50-40 = +10
        $result = Allocator::allocate(0, array(1, 1), array(60, 40));
        $this->assertEquals(array(-10, 10), $result);
        $this->assertEquals(0, array_sum($result));
    }

    public function testHealingAfterMultipleRounds()
    {
        // Simulate two rounds where rounding occurred, then verify healing
        $weights = array(1, 1, 1);

        // Round 1: allocate 10 pennies
        $round1 = Allocator::allocate(10, $weights);
        $this->assertEquals(10, array_sum($round1));

        // Round 2: allocate 10 more, feeding in round 1 as previous
        $round2 = Allocator::allocate(10, $weights, $round1);
        $this->assertEquals(10, array_sum($round2));

        // Cumulative should be close to 20/3 ≈ 6.67 each
        $cumulative = array();
        for ($i = 0; $i < 3; $i++) {
            $cumulative[$i] = $round1[$i] + $round2[$i];
        }
        $this->assertEquals(20, array_sum($cumulative));
        // Difference between max and min cumulative should be at most 1
        $this->assertLessThanOrEqual(1, max($cumulative) - min($cumulative));
    }

    public function testManyRoundsConverge()
    {
        $weights = array(1, 1, 1);
        $cumulative = array(0, 0, 0);

        for ($round = 0; $round < 10; $round++) {
            $allocation = Allocator::allocate(10, $weights, $cumulative);
            $this->assertEquals(10, array_sum($allocation));
            for ($i = 0; $i < 3; $i++) {
                $cumulative[$i] += $allocation[$i];
            }
        }

        // After 10 rounds of 10 pennies each = 100 total
        $this->assertEquals(100, array_sum($cumulative));
        // Each should get 33 or 34
        foreach ($cumulative as $v) {
            $this->assertTrue($v === 33 || $v === 34, "Expected 33 or 34, got $v");
        }
    }

    // ---------------------------------------------------------------
    //  Different weight distributions
    // ---------------------------------------------------------------

    public function testLargeWeightDisparity()
    {
        // 1 share vs 999 shares of 1000 pennies
        $result = Allocator::allocate(1000, array(1, 999));
        $this->assertEquals(1000, array_sum($result));
        $this->assertEquals(1, $result[0]);
        $this->assertEquals(999, $result[1]);
    }

    public function testWeightsWithAZero()
    {
        // Shareholder with zero weight gets nothing
        $result = Allocator::allocate(100, array(0, 1, 1));
        $this->assertEquals(0, $result[0]);
        $this->assertEquals(100, array_sum($result));
    }

    public function testMixedLargeWeights()
    {
        // Weights 10, 20, 30 distributing 120 pennies → 20, 40, 60
        $result = Allocator::allocate(120, array(10, 20, 30));
        $this->assertEquals(array(20, 40, 60), $result);
    }

    // ---------------------------------------------------------------
    //  Validation
    // ---------------------------------------------------------------

    public function testThrowsOnMismatchedArrayLengths()
    {
        $this->expectException(\InvalidArgumentException::class);
        Allocator::allocate(100, array(1, 2), array(10));
    }

    public function testThrowsOnAllZeroWeights()
    {
        $this->expectException(\InvalidArgumentException::class);
        Allocator::allocate(100, array(0, 0, 0));
    }

    public function testThrowsOnNegativeWeight()
    {
        $this->expectException(\InvalidArgumentException::class);
        Allocator::allocate(100, array(-1, 2));
    }

    // ---------------------------------------------------------------
    //  Sum invariant — always exact
    // ---------------------------------------------------------------

    public function testSumInvariantWithVariousInputs()
    {
        $cases = array(
            array(1,    array(1, 1, 1)),
            array(2,    array(1, 1, 1)),
            array(7,    array(3, 3, 3)),
            array(100,  array(3, 5, 7, 11)),
            array(9999, array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10)),
            array(1,    array(1, 1, 1, 1, 1, 1, 1)),
        );

        foreach ($cases as $case) {
            $result = Allocator::allocate($case[0], $case[1]);
            $this->assertEquals(
                $case[0],
                array_sum($result),
                sprintf('Sum mismatch for amount=%d', $case[0])
            );
        }
    }

    public function testSumInvariantWithPreviousAllocations()
    {
        $result = Allocator::allocate(50, array(1, 2, 3), array(10, 15, 25));
        $this->assertEquals(50, array_sum($result));
    }

    // ---------------------------------------------------------------
    //  Large amounts
    // ---------------------------------------------------------------

    public function testLargeAmountDistribution()
    {
        $result = Allocator::allocate(1000000, array(1, 1, 1));
        $this->assertEquals(1000000, array_sum($result));
        // Each should be 333333 or 333334
        foreach ($result as $v) {
            $this->assertTrue($v === 333333 || $v === 333334);
        }
    }
}
