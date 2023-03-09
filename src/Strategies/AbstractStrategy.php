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

abstract class AbstractStrategy
{
    /**
     * Base wait time in ms.
     * @var int
     */
    protected const DEFAULT_BASE = 100;

    /** @var int */
    protected $base;

    /**
     * @return int Time to wait in ms
     */
    abstract public function getWaitTime(int $attempt): int;

    public function __construct(int $base = self::DEFAULT_BASE)
    {
        $this->base = $base;
    }

    public function __invoke(int $attempt): int
    {
        return $this->getWaitTime($attempt);
    }

    public function getBase(): int
    {
        return $this->base;
    }
}
