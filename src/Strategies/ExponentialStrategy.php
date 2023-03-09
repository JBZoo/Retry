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

namespace JBZoo\Retry\Strategies;

class ExponentialStrategy extends AbstractStrategy
{
    public function getWaitTime(int $attempt): int
    {
        return $attempt === 1
            ? $this->base
            : (2 ** $attempt) * $this->base;
    }
}
