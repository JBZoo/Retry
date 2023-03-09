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

use JBZoo\PHPUnit\PHPUnit;
use JBZoo\Retry\Strategies\PolynomialStrategy;

use function JBZoo\PHPUnit\isSame;

class PolynomialStrategyTest extends PHPUnit
{
    public function testDefaults(): void
    {
        $strategy = new PolynomialStrategy();

        isSame(100, $strategy->getBase());
        isSame(2, $strategy->getDegree());
    }

    public function testWaitTimes(): void
    {
        $strategy = new PolynomialStrategy(200, 2);

        isSame(200, $strategy->getWaitTime(1));
        isSame(800, $strategy->getWaitTime(2));
        isSame(1800, $strategy->getWaitTime(3));
        isSame(3200, $strategy->getWaitTime(4));
        isSame(5000, $strategy->getWaitTime(5));
        isSame(7200, $strategy->getWaitTime(6));
        isSame(9800, $strategy->getWaitTime(7));
        isSame(12800, $strategy->getWaitTime(8));
    }
}
