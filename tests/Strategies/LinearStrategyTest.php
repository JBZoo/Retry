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

namespace JBZoo\PHPUnit\Strategies;

use JBZoo\Retry\Strategies\LinearStrategy;
use PHPUnit\Framework\TestCase;

use function JBZoo\PHPUnit\isSame;

class LinearStrategyTest extends TestCase
{
    public function testDefaults(): void
    {
        $strategy = new LinearStrategy();

        isSame(100, $strategy->getBase());
    }

    public function testWaitTimes(): void
    {
        $strategy = new LinearStrategy(100);

        isSame(100, $strategy->getWaitTime(1));
        isSame(200, $strategy->getWaitTime(2));
        isSame(300, $strategy->getWaitTime(3));
    }
}
