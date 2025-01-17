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

use JBZoo\Retry\Strategies\ConstantStrategy;
use PHPUnit\Framework\TestCase;

class ConstantStrategyTest extends TestCase
{
    public function testDefaults(): void
    {
        $strategy = new ConstantStrategy();

        isSame(100, $strategy->getBase());
    }

    public function testWaitTimes(): void
    {
        $s = new ConstantStrategy(100);

        isSame(100, $s->getWaitTime(1));
        isSame(100, $s->getWaitTime(2));
        isSame(100, $s->getWaitTime(3));
    }
}
