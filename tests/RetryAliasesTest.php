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
use STS\Backoff\Backoff;

use function JBZoo\Retry\retry;

class RetryAliasesTest extends PHPUnit
{
    public function testSuccessWithDefaults(): void
    {
        $result = retry(static fn () => 'success');

        isSame('success', $result);
    }

    public function testFailureWithDefaults(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('failure');

        retry(static function (): void {
            throw new \RuntimeException('failure');
        }, 2);
    }

    public function testStrategy(): void
    {
        $realNumberOfAttempts = 0;
        $start                = \microtime(true);

        // We're going to run a test for 100 attempts, just to verify we were able to
        // set our own strategy with a low sleep time.

        try {
            retry(static function () use (&$realNumberOfAttempts): void {
                $realNumberOfAttempts++;
                throw new \RuntimeException('failure');
            }, 100, new ConstantStrategy(1));
        } catch (\Exception $exception) {
        }

        $end = \microtime(true);

        isSame(100, $realNumberOfAttempts);

        $elapsedMS = ($end - $start) * 1000;

        // We expect that this took just a bit over the 100ms that we slept
        isTrue(
            $elapsedMS > 100 && $elapsedMS < 300,
            "Expected elapsedMS between 100 & 300, got: {$elapsedMS}",
        );
    }

    public function testWaitCap(): void
    {
        $start = \microtime(true);

        // We're going to specify a really long sleep time, but with a short cap to override.

        try {
            retry(static function (): void {
                throw new \RuntimeException('failure');
            }, 2, new ConstantStrategy(1000), 100);
        } catch (\Exception $exception) {
        }

        $end = \microtime(true);

        $elapsedMS = ($end - $start) * 1000;

        // We expect that this took just a bit over the 100ms that we slept
        isTrue(
            $elapsedMS > 90 && $elapsedMS < 250,
            "Expected elapsedMS between 90 & 250, got: {$elapsedMS}",
        );
    }

    public function testClassAlias(): void
    {
        $backoff = new Backoff();

        $backoff->enableJitter();
        isTrue($backoff->jitterEnabled());
        $backoff->disableJitter();
        isFalse($backoff->jitterEnabled());

        $result = $backoff->run(static fn () => 123);

        isSame(123, $result);
    }

    public function testJitter(): void
    {
        $retry = new Retry();
        $retry->setStrategy(new ExponentialStrategy(100));
        $retry->setWaitCap(1000000);

        isFalse($retry->jitterEnabled());
        isSame($retry->getWaitTime(50), $retry->getWaitTime(50));

        $retry->enableJitter();
        isTrue($retry->jitterEnabled());
        isNotSame($retry->getWaitTime(10), $retry->getWaitTime(10));

        $retry->disableJitter();
        isFalse($retry->jitterEnabled());
        isSame($retry->getWaitTime(50), $retry->getWaitTime(50));
    }

    public function testFunctionAlias(): void
    {
        $result = backoff(static fn () => 'success');

        isSame('success', $result);
    }
}
