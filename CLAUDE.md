# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP library for auto-healing penny errors when distributing funds to shareholders. Handles rounding issues that arise when splitting monetary amounts into proportional allocations.

## Build & Test Commands

- **Install dependencies:** `composer install`
- **Run all tests:** `./vendor/bin/phpunit`
- **Run a single test:** `./vendor/bin/phpunit --filter TestMethodName`

## Tech Stack

- PHP with PHPUnit 4.8 for testing
- Composer for dependency management
