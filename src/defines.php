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

function retry(
    \Closure $callback,
    int $maxAttempts = Retry::DEFAULT_MAX_ATTEMPTS,
    mixed $strategy = Retry::DEFAULT_STRATEGY,
    ?int $waitCap = null,
    bool $useJitter = Retry::DEFAULT_JITTER_STATE,
): mixed {
    return (new Retry($maxAttempts, $strategy, $waitCap, $useJitter))->run($callback);
}
