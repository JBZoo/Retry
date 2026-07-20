# JBZoo / Retry

[![CI](https://github.com/JBZoo/Retry/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/JBZoo/Retry/actions/workflows/main.yml?query=branch%3Amaster)
[![Coverage Status](https://coveralls.io/repos/github/JBZoo/Retry/badge.svg?branch=master)](https://coveralls.io/github/JBZoo/Retry?branch=master)
[![Psalm Coverage](https://shepherd.dev/github/JBZoo/Retry/coverage.svg)](https://shepherd.dev/github/JBZoo/Retry)
[![Psalm Level](https://shepherd.dev/github/JBZoo/Retry/level.svg)](https://shepherd.dev/github/JBZoo/Retry)
[![CodeFactor](https://www.codefactor.io/repository/github/jbzoo/retry/badge)](https://www.codefactor.io/repository/github/jbzoo/retry/issues)

[![Stable Version](https://poser.pugx.org/jbzoo/retry/version)](https://packagist.org/packages/jbzoo/retry/)
[![Total Downloads](https://poser.pugx.org/jbzoo/retry/downloads)](https://packagist.org/packages/jbzoo/retry/stats)
[![Dependents](https://poser.pugx.org/jbzoo/retry/dependents)](https://packagist.org/packages/jbzoo/retry/dependents?order_by=downloads)
[![GitHub License](https://img.shields.io/github/license/jbzoo/retry)](https://github.com/JBZoo/Retry/blob/master/LICENSE)

Tiny PHP library providing retry functionality with multiple backoff strategies and jitter support.

## Features

- **4 retry strategies** (plus the ability to use your own)
- **Optional jitter/randomness** to spread out retries and minimize collisions
- **Wait time cap** to limit maximum retry delays
- **Custom callbacks** for retry logic and error handling
- **Type-safe** with strict typing and comprehensive test coverage
- **Backward compatible** with [stechstudio/backoff](https://github.com/stechstudio/backoff)

## Requirements

- PHP 8.3 or higher

## About This Fork

This library is a modernized fork of [stechstudio/backoff](https://github.com/stechstudio/backoff) with several improvements:

- **Strict typing** and comprehensive test coverage
- **Explicit configuration** instead of global static defaults
- **Better naming** - "retry" terminology instead of "backoff"
- **Enhanced jitter control** with `setJitterPercent()` and `setJitterMinCap()` methods
- **Backward compatibility** through [aliases](./src/aliases.php)


## Installation

```bash
composer require jbzoo/retry
```

## Quick Start

This library provides sane defaults for immediate use. By default: quadratic strategy with 100ms base time (`attempt^2 * 100`), maximum 5 retries, and no jitter.

### Simple Function Usage

The simplest way to use Retry is with the `retry` helper function:

```php
use function JBZoo\Retry\retry;

$result = retry(function() {
    return doSomeWorkThatMightFail();
});
```

If successful `$result` will contain the result of the closure. If max attempts are exceeded the inner exception is re-thrown.

You can of course provide other options via the helper method if needed.

Parameters: `$callback`, `$maxAttempts`, `$strategy`, `$waitCap`, `$useJitter`.

### Class-Based Usage

Constructor parameters: `$maxAttempts`, `$strategy`, `$waitCap`, `$useJitter`.

```php
use JBZoo\Retry\Retry;

$retry = new Retry(10, 'exponential', 10000, true);
$result = $retry->run(function() {
    return doSomeWorkThatMightFail();
});
```

### Fluent Interface

For dependency injection scenarios, use chainable setters:

```php
use JBZoo\Retry\Retry;

$result = (new Retry())
    ->setStrategy('constant')
    ->setMaxAttempts(10)
    ->enableJitter()
    ->run(function() {
        return doSomeWorkThatMightFail();
    });
```

## Configuration Philosophy

This library enforces **explicit configuration** over global defaults. Unlike the original library, static configuration variables are deprecated and disabled. This design choice ensures:

- Different parts of your project can have completely different retry settings
- No conflicts with third-party libraries using their own defaults
- Clear, explicit dependency injection patterns

Use dependency injection or direct instantiation instead of global configuration.

## Retry Strategies

Four built-in strategies are available, each with a default base time of 100 milliseconds:

### Constant Strategy
Sleeps for a fixed time on each retry.
```php
use JBZoo\Retry\Strategies\ConstantStrategy;
$strategy = new ConstantStrategy(500); // 500ms each retry
```

### Linear Strategy
Sleep time increases linearly: `attempt × baseTime`.
```php
use JBZoo\Retry\Strategies\LinearStrategy;
$strategy = new LinearStrategy(200); // 200ms, 400ms, 600ms...
```

### Polynomial Strategy
Sleep time follows polynomial growth: `(attempt^degree) × baseTime`.
```php
use JBZoo\Retry\Strategies\PolynomialStrategy;
$strategy = new PolynomialStrategy(100, 3); // (attempt^3) × 100ms
// Default degree is 2 (quadratic): 100ms, 400ms, 900ms...
```

### Exponential Strategy
Sleep time grows exponentially: `(2^attempt) × baseTime`.
```php
use JBZoo\Retry\Strategies\ExponentialStrategy;
$strategy = new ExponentialStrategy(100); // 200ms, 400ms, 800ms...
```

## Strategy Usage Options

### String-Based Configuration

```php
use JBZoo\Retry\Retry;
use function JBZoo\Retry\retry;

retry(fn() => doWork(), 10, 'constant'); // Uses ConstantStrategy with 100ms default
$retry = new Retry(10, 'constant');
```

### Instance-Based Configuration

```php
use JBZoo\Retry\Retry;
use JBZoo\Retry\Strategies\LinearStrategy;
use function JBZoo\Retry\retry;

retry(fn() => doWork(), 10, new LinearStrategy(500));
$retry = new Retry(10, new LinearStrategy(500));
```

### Integer-Based Configuration
Passing an integer creates a ConstantStrategy with that base time:

```php
retry(fn() => doWork(), 10, 1000); // 1000ms constant delay
$retry = new Retry(10, 1000);
```

### Custom Closure Strategy
Define your own strategy with a closure:

```php
// Closure receives attempt number and returns sleep time in milliseconds
retry(fn() => doWork(), 10, fn($attempt) => (100 * $attempt) + 5000);

$retry = new Retry(10);
$retry->setStrategy(fn($attempt) => (100 * $attempt) + 5000);
```

## Wait Cap

Limit maximum wait time for fast-growing strategies (like exponential):

```php
retry(fn() => doWork(), 10, 'exponential', 5000); // Cap at 5 seconds
$retry = new Retry()->setWaitCap(5000);
```

## Jitter

Prevent retry collisions by adding randomness to wait times. This is crucial when multiple clients might retry simultaneously.

```php
retry(fn() => doWork(), 10, 'exponential', null, true); // Enable jitter
$retry = new Retry()->enableJitter();
```

### Advanced Jitter Control

Fine-tune jitter behavior with additional methods:

```php
$retry = new Retry()
    ->enableJitter()
    ->setJitterPercent(75)    // Use 75% of calculated wait time as max
    ->setJitterMinCap(100);   // Minimum jitter time of 100ms
```

By default, this library uses "FullJitter" - a random time between 0 and the calculated wait time. See [AWS's excellent explanation](https://aws.amazon.com/blogs/architecture/exponential-backoff-and-jitter/) for more details.

## Advanced Usage

### Custom Retry Logic

Implement custom retry conditions beyond simple exception handling:

```php
use JBZoo\Retry\Retry;

$retry = new Retry();
$retry->setDecider(function($attempt, $maxAttempts, $result, $exception = null) {
    // Custom logic: retry based on time, specific exceptions, return values, etc.
    return $attempt < 3 && ($exception instanceof SpecificException);
});
```

### Error Handling

Add logging or monitoring for retry attempts:

```php
use JBZoo\Retry\Retry;

$retry = new Retry();
$retry->setErrorHandler(function($exception, $attempt, $maxAttempts) {
    error_log("Retry {$attempt}/{$maxAttempts}: {$exception->getMessage()}");
});
```

## Development

### Running Tests

```bash
make update    # Install dependencies
make test      # Run PHPUnit tests
make codestyle # Run code quality checks
make test-all  # Run both tests and code style
```


## License

MIT


## See Also

- [CI-Report-Converter](https://github.com/JBZoo/CI-Report-Converter) - Converting different error reports for deep compatibility with popular CI systems.
- [Composer-Diff](https://github.com/JBZoo/Composer-Diff) - See what packages have changed after `composer update`.
- [Composer-Graph](https://github.com/JBZoo/Composer-Graph) - Dependency graph visualization of composer.json based on mermaid-js.
- [Mermaid-PHP](https://github.com/JBZoo/Mermaid-PHP) - Generate diagrams and flowcharts with the help of the mermaid script language.
- [Utils](https://github.com/JBZoo/Utils) - Collection of useful PHP functions, mini-classes, and snippets for every day.
- [Image](https://github.com/JBZoo/Image) - Package provides object-oriented way to manipulate with images as simple as possible.
- [Data](https://github.com/JBZoo/Data) - Extended implementation of ArrayObject. Use files as config/array.
- [SimpleTypes](https://github.com/JBZoo/SimpleTypes) - Converting any values and measures - money, weight, exchange rates, length, ...
