# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP library for auto-healing penny errors when distributing funds to shareholders. Handles rounding issues that arise when splitting monetary amounts into proportional allocations.

## Build & Test Commands

- **Install dependencies:** `composer install`
- **Run all tests:** `./vendor/bin/phpunit`
- **Run a single test:** `./vendor/bin/phpunit --filter TestMethodName`

## Tech Stack

- PHP 8.x with PHPUnit 9.x for testing
- Composer for dependency management (PSR-4 autoloading under `Allocation\` namespace)

## Architecture

The core class is `Allocation\Allocator` (`src/Allocator.php`) with a single static method:

```php
Allocator::allocate(int $amount, int[] $weights, int[] $previousAllocations = [])
```

- **$amount**: new pennies to distribute
- **$weights**: integer share count per shareholder
- **$previousAllocations**: cumulative pennies already allocated per shareholder

The algorithm computes each shareholder's correct cumulative total (proportional to weight) using the largest-remainder method, then subtracts previous allocations. This auto-heals any penny errors from prior rounds — a shareholder who was previously overpaid gets less in the next round, and vice versa. New allocations can be negative if correction requires it.
