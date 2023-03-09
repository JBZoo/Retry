# JBZoo / Retry

[![CI](https://github.com/JBZoo/Retry/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/JBZoo/Retry/actions/workflows/main.yml?query=branch%3Amaster)    [![Coverage Status](https://coveralls.io/repos/github/JBZoo/Retry/badge.svg?branch=master)](https://coveralls.io/github/JBZoo/Retry?branch=master)    [![Psalm Coverage](https://shepherd.dev/github/JBZoo/Retry/coverage.svg)](https://shepherd.dev/github/JBZoo/Retry)    [![Psalm Level](https://shepherd.dev/github/JBZoo/Retry/level.svg)](https://shepherd.dev/github/JBZoo/Retry)    [![CodeFactor](https://www.codefactor.io/repository/github/jbzoo/retry/badge)](https://www.codefactor.io/repository/github/jbzoo/retry/issues)    
[![Stable Version](https://poser.pugx.org/jbzoo/retry/version)](https://packagist.org/packages/jbzoo/retry/)    [![Total Downloads](https://poser.pugx.org/jbzoo/retry/downloads)](https://packagist.org/packages/jbzoo/retry/stats)    [![Dependents](https://poser.pugx.org/jbzoo/retry/dependents)](https://packagist.org/packages/jbzoo/retry/dependents?order_by=downloads)    [![Visitors](https://visitor-badge.glitch.me/badge?page_id=jbzoo.retry)]()    [![GitHub License](https://img.shields.io/github/license/jbzoo/retry)](https://github.com/JBZoo/Retry/blob/master/LICENSE)


 1. 4 retry strategies (plus the ability to use your own)
 2. Optional jitter / randomness to spread out retries and minimize collisions
 3. Wait time cap
 4. Callbacks for custom retry logic or error handling


Notes:
 * This is a fork. You can find the original project [here](https://github.com/stechstudio/backoff).
 * Now the codebase super strict, and it's covered with tests as much as possible. The original author is great, but the code was smelly :) It's sooo easy, and it took just one my evening... ;) 
 * I don't like wording "backoff" in the code. Yeah, it's fun but... I believe "retry" is more obvious. Sorry :)
 * There is nothing wrong to use import instead of global namespace for function. Don't use old-school practices.
 * Static variables with default values are deprecated and disabled. See dump of thoughts below.
 * New methods `setJitterPercent|getJitterPercent`, `setJitterMinCap|getJitterMinCap` to have fine-tuning.
 * My project has [aliases](./src/aliases.php) for backward compatibility with the original. ;)


## Installation

```
composer require jbzoo/retry
```

## Defaults

This library provides sane defaults, so you can hopefully just jump in for most of your use cases.

By default, the retry is quadratic with a 100ms base time (`attempt^2 * 100`), a max of 5 retries, and no jitter.

## Quickstart

The simplest way to use Retry is with the `retry` helper function:

```php
use function JBZoo\Retry\retry;

$result = retry(function() {
    return doSomeWorkThatMightFail();
});
```

If successful `$result` will contain the result of the closure. If max attempts are exceeded the inner exception is re-thrown.

You can of course provide other options via the helper method if needed.

Method parameters are `$callback`, `$maxAttempts`, `$strategy`, `$waitCap`, `$useJitter`.

## Retry class usage

The Retry class constructor parameters are `$maxAttempts`, `$strategy`, `$waitCap`, `$useJitter`.

```php
use JBZoo\Retry\Retry;

$retry = new Retry(10, 'exponential', 10000, true);
$result = $retry->run(function() {
    return doSomeWorkThatMightFail();
});
```

Or if you are injecting the Retry class with a dependency container, you can set it up with setters after the fact. Note that setters are chainable.

```php
use JBZoo\Retry\Retry;

// Assuming a fresh instance of $retry was handed to you
$result = (new Retry())
    ->setStrategy('constant')
    ->setMaxAttempts(10)
    ->enableJitter()
    ->run(function() {
        return doSomeWorkThatMightFail();
    });
```

## Changing defaults

**Important Note:** It's a fork. So I left it here just for backward compatibility. Static variables are deprecated and don't work at all!

This is terrible practice! Explicit is better than implicit. ;)

 * Example #1. Different parts of your project can have completely different settings.
 * Example #2. Imagine what would happen if some third3-party library (in `./vendor`) uses its own default settings. Let's fight!
 * Example #3. It's just an attempt to store variables in a global namespace. Do you see it?


So the next variables are deprecated, and they don't influence anything.
```php
use JBZoo\Retry\Retry;

Retry::$defaultMaxAttempts;
Retry::$defaultStrategy;
Retry::$defaultJitterEnabled;
```

Just use dependencies injection or so and don't warm your head.

## Strategies

There are four built-in strategies available: constant, linear, polynomial, and exponential.

The default base time for all strategies is 100 milliseconds.

### Constant

```php
use JBZoo\Retry\Strategies\ConstantStrategy;

$strategy = new ConstantStrategy(500);
```

This strategy will sleep for 500 milliseconds on each retry loop.

### Linear

```php
use JBZoo\Retry\Strategies\LinearStrategy;
$strategy = new LinearStrategy(200);
```

This strategy will sleep for `attempt * baseTime`, providing linear retry starting at 200 milliseconds.

### Polynomial

```php
use JBZoo\Retry\Strategies\PolynomialStrategy;
$strategy = new PolynomialStrategy(100, 3);
```

This strategy will sleep for `(attempt^degree) * baseTime`, so in this case `(attempt^3) * 100`.

The default degree if none provided is 2, effectively quadratic time.

### Exponential

```php
use JBZoo\Retry\Strategies\ExponentialStrategy;
$strategy = new ExponentialStrategy(100);
```

This strategy will sleep for `(2^attempt) * baseTime`.

## Specifying strategy

In our earlier code examples we specified the strategy as a string:

```php
use JBZoo\Retry\Retry;
use function JBZoo\Retry\retry;

retry(function() {
    // ...
}, 10, 'constant');

// OR

$retry = new Retry(10, 'constant');
```

This would use the `ConstantStrategy` with defaults, effectively giving you a 100 millisecond sleep time.

You can create the strategy instance yourself in order to modify these defaults:

```php
use JBZoo\Retry\Retry;
use JBZoo\Retry\Strategies\LinearStrategy;
use function JBZoo\Retry\retry;

retry(function() {
    // ...
}, 10, new LinearStrategy(500));

// OR

$retry = new Retry(10, new LinearStrategy(500));
```

You can also pass in an integer as the strategy, will translate to a ConstantStrategy with the integer as the base time in milliseconds:

```php
use JBZoo\Retry\Retry;
use function JBZoo\Retry\retry;

retry(function() {
    // ...
}, 10, 1000);

// OR

$retry = new Retry(10, 1000);
```

Finally, you can pass in a closure as the strategy if you wish. This closure should receive an integer `attempt` and return a sleep time in milliseconds.

```php
use JBZoo\Retry\Retry;
use function JBZoo\Retry\retry;

retry(function() {
    // ...
}, 10, function($attempt) {
    return (100 * $attempt) + 5000;
});

// OR

$retry = new Retry(10);
$retry->setStrategy(function($attempt) {
    return (100 * $attempt) + 5000;
});
```

## Wait cap

You may want to use a fast growing retry time (like exponential) but then also set a max wait time so that it levels out after a while.

This cap can be provided as the fourth argument to the `retry` helper function, or using the `setWaitCap()` method on the Retry class.

## Jitter

If you have a lot of clients starting a job at the same time and encountering failures, any of the above retry strategies could mean the workers continue to collide at each retry.

The solution for this is to add randomness. See here for a good explanation:

https://aws.amazon.com/ru/blogs/architecture/exponential-backoff-and-jitter

You can enable jitter by passing `true` in as the fifth argument to the `retry` helper function, or by using the `enableJitter()` method on the Retry class.

By default, we use the "FullJitter" approach outlined in the above article, where a random number between 0 and the sleep time provided by your selected strategy is used.

But you can change the maximum time for Jitter with method `setJitterPercent(). It's 100 by default. Also you can set min value for jitter with `setJitterMinCap` (it's `0` by default).

## Custom retry decider

By default, Retry will retry if an exception is encountered, and if it has not yet hit max retries.

You may provide your own retry decider for more advanced use cases. Perhaps you want to retry based on time rather than number of retries, or perhaps there are scenarios where you would want retry even when an exception was not encountered.

Provide the decider as a callback, or an instance of a class with an `__invoke` method. Retry will hand it four parameters: the current attempt, max attempts, the last result received, and the exception if one was encountered. Your decider needs to return true or false.

```php
use JBZoo\Retry\Retry;

$retry = new Retry();
$retry->setDecider(function($attempt, $maxAttempts, $result, $exception = null) {
    return someCustomLogic();
});
```

## Error handler callback

You can provide a custom error handler to be notified anytime an exception occurs, even if we have yet to reach max attempts. This is a useful place to do logging for example.

```php
use JBZoo\Retry\Retry;

$retry = new Retry();
$retry->setErrorHandler(function($exception, $attempt, $maxAttempts) {
    Log::error("On run {$attempt}/{$maxAttempts} we hit a problem: {$exception->getMessage()}");
});
```


## Unit tests and check code style
```sh
make update
make test-all
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
