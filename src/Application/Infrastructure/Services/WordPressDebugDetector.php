<?php

declare(strict_types=1);

namespace Pollora\Application\Infrastructure\Services;

use Pollora\Application\Domain\Contracts\DebugDetectorInterface;

/**
 * WordPress implementation for detecting debug mode.
 */
class WordPressDebugDetector implements DebugDetectorInterface
{
    /**
     * Determine if the application is in debug mode.
     * Checks the WP_DEBUG constant for WordPress environments.
     *
     * @return bool True if application is in debug mode
     */
    public function isDebugMode(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}
