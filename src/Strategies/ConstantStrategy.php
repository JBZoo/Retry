<?php

/**
 * JBZoo Toolbox - Retry
 *
 * This file is part of the JBZoo Toolbox project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package    Retry
 * @license    MIT
 * @copyright  Copyright (C) JBZoo.com, All rights reserved.
 * @link       https://github.com/JBZoo/Retry
 */

declare(strict_types=1);

namespace JBZoo\Retry\Strategies;

/**
 * Class ConstantStrategy
 * @package JBZoo\Retry\Strategies
 */
class ConstantStrategy extends AbstractStrategy
{
    /**
     * @param int $attempt
     * @return int
     * @phan-suppress PhanUnusedPublicMethodParameter
     */
    public function getWaitTime(int $attempt): int
    {
        return $this->base;
    }
}
