<?php

/**
 * JBZoo Toolbox - Retry.
 *
 * This file is part of the JBZoo Toolbox project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @see        https://github.com/JBZoo/Retry
 */

declare(strict_types=1);

namespace JBZoo\Retry;

use JBZoo\Retry\Strategies\AbstractStrategy;
use JBZoo\Retry\Strategies\ConstantStrategy;
use JBZoo\Retry\Strategies\ExponentialStrategy;
use JBZoo\Retry\Strategies\LinearStrategy;
use JBZoo\Retry\Strategies\PolynomialStrategy;

class Retry
{
    // Fallback values and global defaults
    public const DEFAULT_MAX_ATTEMPTS   = 5;
    public const DEFAULT_STRATEGY       = self::STRATEGY_POLYNOMIAL;
    public const DEFAULT_JITTER_STATE   = false;
    public const DEFAULT_JITTER_PERCENT = 100;

    // Pre-defined strategies
    public const STRATEGY_CONSTANT    = 'constant';
    public const STRATEGY_LINEAR      = 'linear';
    public const STRATEGY_POLYNOMIAL  = 'polynomial';
    public const STRATEGY_EXPONENTIAL = 'exponential';

    /** @deprecated See README.md "Changing defaults" */
    public static int $defaultMaxAttempts = self::DEFAULT_MAX_ATTEMPTS;

    /**
     * @var AbstractStrategy|string
     * @deprecated See README.md "Changing defaults"
     */
    public static string|AbstractStrategy $defaultStrategy = self::DEFAULT_STRATEGY;

    /** @deprecated See README.md "Changing defaults" */
    public static bool $defaultJitterEnabled = self::DEFAULT_JITTER_STATE;

    /**
     * This callable should take an 'attempt' integer, and return a wait time in milliseconds.
     * @var callable
     */
    protected $strategy;

    protected array $strategies = [
        self::STRATEGY_CONSTANT    => ConstantStrategy::class,
        self::STRATEGY_LINEAR      => LinearStrategy::class,
        self::STRATEGY_POLYNOMIAL  => PolynomialStrategy::class,
        self::STRATEGY_EXPONENTIAL => ExponentialStrategy::class,
    ];

    protected int $maxAttempts;

    /**
     * The max wait time you want to allow, regardless of what the strategy says.
     * @var null|int In milliseconds
     */
    protected ?int $waitCap;

    protected bool $useJitter = false;

    protected int $jitterPercent = self::DEFAULT_JITTER_PERCENT;

    protected int $jitterMinTime = 0;

    /** @var array|non-empty-array<int,\Exception>|non-empty-array<int,\Throwable> */
    protected array $exceptions = [];

    /**
     * This will decide whether to retry or not.
     * @var callable
     */
    protected $decider;

    /**
     * This receive any exceptions we encounter.
     * @var null|callable
     */
    protected $errorHandler;

    public function __construct(
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        mixed $strategy = self::DEFAULT_STRATEGY,
        ?int $waitCap = null,
        ?bool $useJitter = self::DEFAULT_JITTER_STATE,
        ?callable $decider = null,
    ) {
        $this->setMaxAttempts($maxAttempts);
        // @phpstan-ignore-next-line
        $this->setStrategy($strategy ?: self::DEFAULT_STRATEGY);
        $this->setJitter($useJitter ?? self::DEFAULT_JITTER_STATE);
        $this->setWaitCap($waitCap);
        $this->setDecider($decider ?? self::getDefaultDecider());
    }

    public function setMaxAttempts(int $maxAttempts): self
    {
        $this->maxAttempts = $maxAttempts > 0 ? $maxAttempts : self::DEFAULT_MAX_ATTEMPTS;

        return $this;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Set max time period between retries.
     * @param null|int $waitCap Time in milliseconds
     */
    public function setWaitCap(?int $waitCap): self
    {
        $this->waitCap = $waitCap;

        return $this;
    }

    public function getWaitCap(): ?int
    {
        return $this->waitCap;
    }

    public function setJitter(bool $useJitter): self
    {
        $this->useJitter = $useJitter;

        return $this;
    }

    public function setJitterPercent(int $jitterPercent): self
    {
        $this->jitterPercent = $jitterPercent;

        return $this;
    }

    public function getJitterPercent(): int
    {
        return $this->jitterPercent;
    }

    public function setJitterMinCap(int $jitterMinTime): self
    {
        $this->jitterMinTime = \max($jitterMinTime, 0);

        return $this;
    }

    public function getJitterMinCap(): int
    {
        return $this->jitterMinTime;
    }

    public function enableJitter(): self
    {
        $this->setJitter(true);

        return $this;
    }

    public function disableJitter(): self
    {
        $this->setJitter(false);

        return $this;
    }

    public function jitterEnabled(): bool
    {
        return $this->useJitter;
    }

    public function getStrategy(): callable
    {
        return $this->strategy;
    }

    public function setStrategy(callable|int|string $strategy): self
    {
        $this->strategy = $this->buildStrategy($strategy);

        return $this;
    }

    /**
     * @return null|mixed
     * @throws \Exception
     */
    public function run(\Closure $callback)
    {
        $attempt = 0;
        $try = true;
        $result = null;

        while ($try) {
            $exceptionExternal = null;

            $this->wait($attempt);

            try {
                $result = $callback($attempt + 1, $this->getMaxAttempts(), $this);
            } catch (\Exception $exception) {
                $this->exceptions[] = $exception;
                $exceptionExternal = $exception;
            } catch (\Throwable $exception) {
                if ($exception instanceof \Error) {
                    $exception = new \Exception($exception->getMessage(), $exception->getCode(), $exception);
                }
                $this->exceptions[] = $exception;
                $exceptionExternal = $exception;
            }

            $try = \call_user_func($this->decider, ++$attempt, $this->getMaxAttempts(), $result, $exceptionExternal);

            if ($try && isset($this->errorHandler)) {
                \call_user_func($this->errorHandler, $exceptionExternal, $attempt, $this->getMaxAttempts());
            }
        }

        return $result;
    }

    /**
     * Sets the decider callback.
     */
    public function setDecider(callable $callback): self
    {
        $this->decider = $callback;

        return $this;
    }

    /**
     * Sets the error handler callback.
     */
    public function setErrorHandler(callable $callback): self
    {
        $this->errorHandler = $callback;

        return $this;
    }

    public function wait(int $attempt): self
    {
        if ($attempt <= 0) {
            return $this;
        }

        // It's PHP usleep limitation
        $maxTimeForUSleep = 10 ** 6;

        // Helper vars. No magic numbers!
        $divider1k = 1000;
        $divider1kk = $divider1k * $divider1k;

        $microSeconds = $this->getWaitTime($attempt) * $divider1k;

        // It solves cross-platform issue. Check, if it's more than 1 sec.
        // See notes https://www.php.net/manual/en/function.usleep.php
        if ($microSeconds >= $maxTimeForUSleep) {
            $seconds = $microSeconds / $divider1kk;
            $partInMcSeconds = \fmod($seconds, 1) * $divider1kk;

            \sleep(\abs((int)$seconds)); // It works with seconds
            \usleep(\abs((int)$partInMcSeconds)); //  It works with microseconds (1/1000000)
        } else {
            \usleep(\abs($microSeconds));
        }

        return $this;
    }

    /**
     * @return int time in milliseconds
     */
    public function getWaitTime(int $attempt): int
    {
        $waitTime = ($this->getStrategy())($attempt);

        return $this->jitter($this->cap($waitTime));
    }

    /**
     * Builds a callable strategy.
     *
     * @param mixed $strategy Can be a string that matches a key in $strategies, an instance of AbstractStrategy
     *                        (or any other instance that has an __invoke method), a callback function, or
     *                        an integer (which we interpret to mean you want a ConstantStrategy)
     */
    protected function buildStrategy($strategy): callable
    {
        if (\is_string($strategy) && \array_key_exists($strategy, $this->strategies)) {
            /** @var callable $result */
            return new $this->strategies[$strategy]();
        }

        if (\is_callable($strategy)) {
            return $strategy;
        }

        if (\is_int($strategy)) {
            return new ConstantStrategy($strategy);
        }

        throw new \InvalidArgumentException("Invalid strategy: {$strategy}");
    }

    protected function cap(int $waitTime): int
    {
        $waitCap = (int)$this->getWaitCap();

        return $waitCap > 0 ? \min($waitCap, $waitTime) : $waitTime;
    }

    protected function jitter(int $waitTime): int
    {
        if ($this->jitterEnabled()) {
            $minValue = $this->jitterMinTime;
            $maxValue = (int)($waitTime * $this->jitterPercent / 100);

            if ($minValue > $maxValue) {
                $minValue = $maxValue;
            }

            return \random_int($minValue, $maxValue);
        }

        return $waitTime;
    }

    /**
     * Gets a default decider that simply check exceptions and max-attempts.
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @psalm-suppress MissingClosureParamType
     */
    protected static function getDefaultDecider(): \Closure
    {
        return static function (
            int $currentAttempt,
            int $maxAttempts,
            /** @phan-suppress-next-line PhanUnusedClosureParameter */
            $result = null,
            ?\Exception $exception = null,
        ): bool {
            if ($currentAttempt >= $maxAttempts && $exception) {
                throw $exception;
            }

            return $currentAttempt < $maxAttempts && $exception;
        };
    }
}
