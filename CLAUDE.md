# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

This project uses a Makefile with commands from `jbzoo/toolbox-dev`. The primary commands are:

- `make update` - Install/Update all 3rd party dependencies via Composer
- `make test` - Run PHPUnit tests
- `make codestyle` - Run code quality checks (PHP CS Fixer, PHPStan, Psalm, etc.)
- `make test-all` - Run both tests and code style checks
- `make report-all` - Generate comprehensive reports (coverage, metrics, etc.)

The project requires PHP 8.2+ and uses `jbzoo/toolbox-dev` for development tooling.

## Architecture Overview

This is a PHP retry/backoff library with the following key components:

### Core Classes
- `JBZoo\Retry\Retry` - Main retry orchestrator class
- `JBZoo\Retry\Exception` - Base exception for the library

### Strategy Pattern Implementation
All retry strategies inherit from `AbstractStrategy` in the `JBZoo\Retry\Strategies` namespace:
- `ConstantStrategy` - Fixed delay between retries
- `LinearStrategy` - Linearly increasing delay (attempt * baseTime)
- `PolynomialStrategy` - Polynomial delay (attempt^degree * baseTime), default degree=2 (quadratic)
- `ExponentialStrategy` - Exponential delay (2^attempt * baseTime)

### Key Features
- **Jitter Support**: Configurable randomness to prevent retry collisions
- **Wait Cap**: Maximum wait time limit for retry strategies
- **Custom Retry Logic**: Pluggable decider callbacks for advanced retry conditions
- **Error Handling**: Custom error handler callbacks for logging/monitoring
- **Backward Compatibility**: Aliases for the original `stechstudio/backoff` library

### Configuration
The `Retry` class supports both constructor injection and fluent setters:
- Constructor: `new Retry($maxAttempts, $strategy, $waitCap, $useJitter)`
- Fluent API: `(new Retry())->setStrategy('exponential')->enableJitter()->run($callback)`

### Function Interface
Global `retry()` function provides a simple interface:
```php
use function JBZoo\Retry\retry;
retry($callback, $maxAttempts, $strategy, $waitCap, $useJitter);
```

### Testing Structure
- PHPUnit tests in `tests/` directory
- Strategy-specific tests in `tests/Strategies/`
- Package integration tests and alias compatibility tests
- CI runs tests on PHP 8.2, 8.3, 8.4 with both `--prefer-lowest` and latest dependencies

### Development Notes
- Project enforces strict typing (`declare(strict_types=1)`)
- Uses PSR-4 autoloading with namespace `JBZoo\Retry`
- Includes global function definitions in `src/defines.php` and backward compatibility aliases in `src/aliases.php`
- All classes are marked `final` for better encapsulation
- Constants define default values and strategy names