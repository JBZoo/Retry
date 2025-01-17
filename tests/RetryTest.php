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

namespace JBZoo\PHPUnit;

use JBZoo\Retry\Retry;
use JBZoo\Retry\Strategies\ConstantStrategy;
use JBZoo\Retry\Strategies\ExponentialStrategy;
use JBZoo\Retry\Strategies\LinearStrategy;
use JBZoo\Retry\Strategies\PolynomialStrategy;

class RetryTest extends PHPUnit
{
    public function testDefaults(): void
    {
        $retry = new Retry();

        isSame(5, $retry->getMaxAttempts());
        self::assertInstanceOf(PolynomialStrategy::class, $retry->getStrategy());
        isFalse($retry->jitterEnabled());

        $retry->setMaxAttempts(0);
        isSame(5, $retry->getMaxAttempts());
    }

    public function testFluidApi(): void
    {
        $retry = new Retry();

        $retry
            ->setStrategy('constant')
            ->setMaxAttempts(10)
            ->setWaitCap(5)
            ->enableJitter();

        isSame(10, $retry->getMaxAttempts());
        isSame(5, $retry->getWaitCap());
        isTrue($retry->jitterEnabled());
        self::assertInstanceOf(ConstantStrategy::class, $retry->getStrategy());
    }

    public function testNotChangingStaticDefaults(): void
    {
        Retry::$defaultMaxAttempts   = 15;
        Retry::$defaultStrategy      = 'constant';
        Retry::$defaultJitterEnabled = true;

        $retry = new Retry();

        isSame(Retry::DEFAULT_MAX_ATTEMPTS, $retry->getMaxAttempts());
        self::assertInstanceOf(PolynomialStrategy::class, $retry->getStrategy());
        isSame(Retry::DEFAULT_JITTER_STATE, $retry->jitterEnabled());

        Retry::$defaultStrategy = new LinearStrategy(250);

        $retry = new Retry();

        self::assertInstanceOf(PolynomialStrategy::class, $retry->getStrategy());

        // I don't care about put them back. They dont' work at all and deprecated.
        // Retry::$defaultMaxAttempts = 5;
        // Retry::$defaultStrategy = "polynomial";
        // Retry::$defaultJitterEnabled = false;
    }

    public function testConstructorParams(): void
    {
        $retry = new Retry(10, 'linear');

        isSame(10, $retry->getMaxAttempts());
        self::assertInstanceOf(LinearStrategy::class, $retry->getStrategy());
    }

    public function testStrategyKeys(): void
    {
        $retry = new Retry();

        $retry->setStrategy('constant');
        $retry->setStrategy(Retry::STRATEGY_CONSTANT);
        self::assertInstanceOf(ConstantStrategy::class, $retry->getStrategy());

        $retry->setStrategy('linear');
        $retry->setStrategy(Retry::STRATEGY_LINEAR);
        self::assertInstanceOf(LinearStrategy::class, $retry->getStrategy());

        $retry->setStrategy('polynomial');
        $retry->setStrategy(Retry::STRATEGY_POLYNOMIAL);
        self::assertInstanceOf(PolynomialStrategy::class, $retry->getStrategy());

        $retry->setStrategy('exponential');
        $retry->setStrategy(Retry::STRATEGY_EXPONENTIAL);
        self::assertInstanceOf(ExponentialStrategy::class, $retry->getStrategy());
    }

    public function testStrategyInstances(): void
    {
        $retry = new Retry();

        $retry->setStrategy(new ConstantStrategy());
        self::assertInstanceOf(ConstantStrategy::class, $retry->getStrategy());

        $retry->setStrategy(new LinearStrategy());
        self::assertInstanceOf(LinearStrategy::class, $retry->getStrategy());

        $retry->setStrategy(new PolynomialStrategy());
        self::assertInstanceOf(PolynomialStrategy::class, $retry->getStrategy());

        $retry->setStrategy(new ExponentialStrategy());
        self::assertInstanceOf(ExponentialStrategy::class, $retry->getStrategy());
    }

    public function testClosureStrategy(): void
    {
        $retry = new Retry();

        $strategy = static fn () => 'hi there';

        $retry->setStrategy($strategy);

        isSame('hi there', ($retry->getStrategy())());
    }

    public function testIntegerReturnsConstantStrategy(): void
    {
        $retry = new Retry();

        $retry->setStrategy(500);

        self::assertInstanceOf(ConstantStrategy::class, $retry->getStrategy());
    }

    public function testInvalidStrategy(): void
    {
        $retry = new Retry();

        $this->expectException(\InvalidArgumentException::class);
        $retry->setStrategy('foo');
    }

    public function testWaitTimes(): void
    {
        $retry = new Retry(1, 'linear');

        isSame(100, $retry->getStrategy()->getBase());

        isSame(100, $retry->getWaitTime(1));
        isSame(200, $retry->getWaitTime(2));
    }

    public function testWaitCap(): void
    {
        $retry = new Retry(1, new LinearStrategy(5000));

        isSame(10000, $retry->getWaitTime(2));

        $retry->setWaitCap(5000);

        isSame(5000, $retry->getWaitTime(2));
    }

    public function testWaitLessOneSecond(): void
    {
        $retry = new Retry(1, new LinearStrategy(50));

        $start = \microtime(true);

        $retry->wait(2);

        $end = \microtime(true);

        $elapsedMS = ($end - $start) * 1000;

        // We expect that this took just barely over the 100ms we asked for
        isTrue(
            $elapsedMS > 90 && $elapsedMS < 175,
            "Expected elapsedMS between 90 & 175, got: {$elapsedMS}",
        );
    }

    public function testWaitMoreOneSecond(): void
    {
        $retry = new Retry(1, new LinearStrategy(400));

        $start = \microtime(true);

        $retry->wait(3); // ~1.2 seconds

        $end = \microtime(true);

        $elapsedMS = ($end - $start) * 1000;

        // We expect that this took just barely over the 100ms we asked for
        isTrue(
            $elapsedMS > 1200 && $elapsedMS < 1400,
            "Expected elapsedMS between 1200 & 1400, got: {$elapsedMS}",
        );
    }

    public function testSuccessfulWork(): void
    {
        $retry = new Retry();

        $result = $retry->run(static fn () => 'done');

        isSame('done', $result);
    }

    public function testFirstAttemptDoesNotCallStrategy(): void
    {
        $retry = new Retry();
        $retry->setStrategy(static function (): void {
            throw new \Exception("We shouldn't be here");
        });

        $result = $retry->run(static fn () => 'done');

        isSame('done', $result);
    }

    public function testFailedWorkReThrowsException(): void
    {
        $retry = new Retry(2, new ConstantStrategy(0));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('failure');

        $retry->run(static function (): void {
            throw new \RuntimeException('failure');
        });
    }

    public function testHandleErrorsPhp7(): void
    {
        $retry = new Retry(2, new ConstantStrategy(0));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Modulo by zero');

        $retry->run(static function () {
            if (\version_compare(\PHP_VERSION, '7.0.0') >= 0) {
                /** @noinspection PhpDivisionByZeroInspection */
                return 1 % 0;
            }

            // Handle version < 7
            throw new \Error('Modulo by zero');
        });
    }

    public function testAttempts(): void
    {
        $retry = new Retry(10, new ConstantStrategy(0));

        $attempt = 0;

        $result = $retry->run(static function () use (&$attempt) {
            $attempt++;

            if ($attempt < 5) {
                throw new \Exception('failure');
            }

            return 'success';
        });

        isSame(5, $attempt);
        isSame('success', $result);
    }

    public function testCustomDeciderAttempts(): void
    {
        $retry = new Retry(10, new ConstantStrategy(0));
        $retry->setDecider(static fn ($retry, $maxAttempts, $result = null, $exception = null) => !($retry >= $maxAttempts || $result === 'success'));

        $attempt = 0;

        $result = $retry->run(static function () use (&$attempt) {
            $attempt++;

            if ($attempt < 5) {
                throw new \RuntimeException('failure');
            }

            if ($attempt < 7) {
                return 'not yet';
            }

            return 'success';
        });

        isSame(7, $attempt);
        isSame('success', $result);
    }

    public function testErrorHandler(): void
    {
        $log = [];

        $retry = new Retry(10, new ConstantStrategy(0));
        $retry->setErrorHandler(static function ($exception, $attempt, $maxAttempts) use (&$log): void {
            $log[] = "Attempt {$attempt} of {$maxAttempts}: " . $exception->getMessage();
        });

        $attempt = 0;

        $result = $retry->run(static function () use (&$attempt) {
            $attempt++;

            if ($attempt < 5) {
                throw new \Exception('failure');
            }

            return 'success';
        });

        isSame(4, \count($log));
        isSame('Attempt 4 of 10: failure', \array_pop($log));
        isSame('success', $result);
    }

    public function testJitter(): void
    {
        $retry = new Retry(10, new ConstantStrategy(1000));

        // First without jitter
        isSame(1000, $retry->getWaitTime(1));

        // Now with jitter
        $retry->enableJitter();

        // Because it's still possible that I could get 1000 back even with jitter, I'm going to generate two
        $waitTime1 = $retry->getWaitTime(1);
        $waitTime2 = $retry->getWaitTime(1);

        // And I'm banking that I didn't hit the _extremely_ rare chance that both were randomly chosen to be 1000 still
        isTrue($waitTime1 < 1000 || $waitTime2 < 1000);
    }

    public function testUndefinedStrategy(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid strategy: UndefinedStrategy');

        $retry = new Retry();
        $retry->setStrategy('UndefinedStrategy');
    }

    public function testErrorHandlerVersion2(): void
    {
        $messages = [];

        $retry = new Retry();
        $retry->setErrorHandler(static function ($exception, $attempt, $maxAttempts) use (&$messages): void {
            $messages[] = "On run {$attempt}/{$maxAttempts} we hit a problem: {$exception->getMessage()}";
        });

        try {
            $retry->run(static function (int $currentAttempt, int $maxAttempts): void {
                throw new \Error("failure #{$currentAttempt}/{$maxAttempts}");
            });
        } catch (\Exception $exception) {
        }

        isSame([
            'On run 1/5 we hit a problem: failure #1/5',
            'On run 2/5 we hit a problem: failure #2/5',
            'On run 3/5 we hit a problem: failure #3/5',
            'On run 4/5 we hit a problem: failure #4/5',
        ], $messages);
    }

    public function testWaitingTime(): void
    {
        $retry = (new Retry())
            ->setStrategy(new ExponentialStrategy(100));

        isSame([100, 400, 800, 1600, 3200], [
            $retry->getWaitTime(1),
            $retry->getWaitTime(2),
            $retry->getWaitTime(3),
            $retry->getWaitTime(4),
            $retry->getWaitTime(5),
        ]);
    }

    public function testWaitingTimeWithJitter(): void
    {
        $retry = (new Retry())
            ->setStrategy(new ExponentialStrategy(100))
            ->enableJitter();

        isNotSame([100, 400, 800, 1600, 3200], [
            $retry->getWaitTime(1),
            $retry->getWaitTime(2),
            $retry->getWaitTime(3),
            $retry->getWaitTime(4),
            $retry->getWaitTime(5),
        ]);
    }

    public function testJitterPercent(): void
    {
        $originalPeriod = 100;
        $retry          = (new Retry(1, $originalPeriod))->enableJitter();

        // Check default
        isSame(100, $retry->getJitterPercent());
        isSame(0, $retry->getJitterMinCap());

        // Check min jitter period
        $retry->setJitterMinCap(-1);
        isSame(0, $retry->getJitterMinCap());
        $retry->setJitterMinCap(1);
        isSame(1, $retry->getJitterMinCap());
        $retry->setJitterMinCap(1000000);
        isSame(100, $retry->getWaitTime(1));

        // If jitter = 1%
        $retry->setJitterPercent(1);
        $retry->setJitterMinCap(1);
        isSame(1, $retry->getJitterPercent());
        isSame(1, $retry->getWaitTime(1));

        // If jitter = 1,000,000%
        $retry->setJitterPercent(1000000);
        isSame(1000000, $retry->getJitterPercent());
        isTrue($retry->getWaitTime(1) > $originalPeriod);

        // Revert to default
        $retry->setJitterPercent(Retry::DEFAULT_JITTER_PERCENT);
        isSame(100, $retry->getJitterPercent());
        isTrue($retry->getWaitTime(1) <= $originalPeriod);
    }
}
