<?php

declare(strict_types=1);

namespace Pollora\Application\Domain\Contracts;

/**
 * Interface for detecting console and WP CLI contexts.
 */
interface ConsoleDetectorInterface
{
    /**
     * Determine if the application is running in console mode.
     */
    public function isConsole(): bool;

    /**
     * Determine if the application is running in WP CLI context.
     */
    public function isWpCli(): bool;
}
