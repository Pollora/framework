<?php

namespace Pollora\Console\Domain\Services;

use Pollora\Console\Domain\Contracts\ConsoleDetectorInterface;

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
        return php_sapi_name() === 'cli';
    }

    /**
     * {@inheritdoc}
     */
    public function isWpCli(): bool
    {
        return defined('WP_CLI') && WP_CLI;
    }
}
