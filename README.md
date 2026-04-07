# php-allocation

A PHP library for distributing funds among shareholders proportionally — and automatically correcting penny errors across multiple rounds of allocation.

## The Problem

When you split money among shareholders by percentage, rounding creates errors. Distribute $100 among three equal shareholders and someone gets 34 cents while the others get 33. That's fine for a single round. But in systems that make **repeated partial distributions** — loan repayments, dividend payouts, fund disbursements — these rounding errors compound. Over time, shareholders drift away from their fair share.

Most systems deal with this by either accepting the drift, recomputing everything from scratch, or tracking error balances as a separate bookkeeping concern.

This library takes a different approach: **each new allocation automatically corrects all previous rounding errors**. You pass in what was previously distributed, and the algorithm figures out the new allocation that brings cumulative totals back in line.

## How It Works

```php
use Allocation\Allocator;

// Simple one-shot: split 100 pennies among weights 1, 2, 7
$result = Allocator::allocate(100, [1, 2, 7]);
// → [10, 20, 70]

// Multi-round with auto-healing:
$round1 = Allocator::allocate(10, [1, 1, 1]);
// → [4, 3, 3] (someone got the extra penny)

$round2 = Allocator::allocate(10, [1, 1, 1], $round1);
// → [3, 3, 4] (a different shareholder gets the extra penny this time)

// Cumulative: [7, 6, 7] — as fair as possible with integers
```

### Correcting Past Errors

If previous allocations were wrong (due to bugs, manual adjustments, or a different algorithm), the next call heals them:

```php
// Weights 1:1:1, but previous allocation was uneven: [2, 5, 3]
$result = Allocator::allocate(20, [1, 1, 1], [2, 5, 3]);
// → [8, 5, 7]
// Cumulative: [10, 10, 10] — perfectly corrected
```

### Preventing Negative Allocations

By default, shareholders who were overpaid won't receive a negative allocation. Instead, they're clamped to zero and the remaining amount is redistributed fairly among eligible shareholders:

```php
// Shareholder 0 was massively overpaid
$result = Allocator::allocate(10, [1, 1], [80, 20]);
// → [0, 10] (no clawback; shareholder 1 gets everything this round)
```

To allow negative allocations (e.g., for systems that support clawbacks):

```php
$result = Allocator::allocate(10, [1, 1], [80, 20], true);
// → [-25, 35]
```

## API

```php
Allocator::allocate(
    int   $amount,              // Pennies to distribute this round
    int[] $weights,             // Integer shares per shareholder
    int[] $previousAllocations, // Cumulative pennies already given (default: all zeros)
    bool  $allowNegative        // Allow negative corrections (default: false)
): int[]                        // New allocation per shareholder (sums to $amount exactly)
```

### Guarantees

- **Exact sum**: the returned allocations always sum to exactly `$amount` — no missing or extra pennies
- **Proportional fairness**: cumulative allocations track each shareholder's weight as closely as integer arithmetic allows
- **Self-healing**: any prior rounding errors are automatically corrected in the next round
- **Deterministic**: same inputs always produce the same output

## The Algorithm

The core is the [largest-remainder method](https://en.wikipedia.org/wiki/Largest_remainder_method) (Hamilton method), a well-known apportionment algorithm used in proportional representation. For each shareholder:

1. Compute the ideal cumulative total: `(weight / totalWeight) * grandTotal`
2. Floor each value, then distribute leftover pennies to shareholders with the largest fractional remainders
3. Subtract what was previously allocated to get the new allocation

When `allowNegative` is `false`, shareholders with negative new allocations are clamped to zero and the remaining amount is redistributed among eligible shareholders using the same method, iterating until stable.

## Similar Libraries

Several libraries implement the largest-remainder method for one-shot allocation:

- **PHP**: [brick/money](https://github.com/brick/money) — `Money::allocateByRatios()`
- **JavaScript**: `largest-remainder-round` on npm; also used internally by D3
- **Python**: `apportion`
- **Ruby**: the `money` gem's `allocate` method

**None of these support cumulative correction across multiple rounds.** They treat each allocation as independent. The round-over-round penny healing is what this library adds.

## What Can Go Wrong Without This

- **Penny drift**: after 100 rounds of 3-way splits, one shareholder could be off by dozens of pennies
- **Inconsistent corrections**: manual fixes in one round create new errors in the next
- **Off-by-one on totals**: naive `round()` calls can produce allocations that don't sum to the input amount
- **Unfair redistribution**: when clamping negatives, a naive approach gives all surplus to one shareholder instead of distributing fairly

## Installation

```bash
composer require zvibo/php-allocation
```

## Testing

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT
