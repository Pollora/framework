<?php

declare(strict_types=1);

namespace Pollora\Application\Domain\Services;

use Pollora\Application\Domain\Contracts\ConsoleDetectorInterface;

/**
 * Pure domain service for console context detection (no external dependency).
 */
class ConsoleDetector implements ConsoleDetectorInterface
{
    /**
     * {@inheritdoc}
     */
    public function isConsole(): bool
    {
        // Default logic, can be overridden by infrastructure
        return PHP_SAPI === 'cli';
    }

    /**
     * {@inheritdoc}
     */
    public function isWpCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }
}
