<?php

declare(strict_types=1);

namespace Pollora\Application\Domain\Contracts;

/**
 * Contract for detecting if the application is in debug mode.
 */
interface DebugDetectorInterface
{
    /**
     * Determine if the application is in debug mode.
     *
     * @return bool True if application is in debug mode
     */
    public function isDebugMode(): bool;
}
