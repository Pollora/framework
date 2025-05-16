<?php

namespace Pollora\Console\Domain\Contracts;

/**
 * Interface for detecting console and WP CLI contexts.
 */
interface ConsoleDetectorInterface
{
    /**
     * Determine if the application is running in console mode.
     *
     * @return bool
     */
    public function isConsole(): bool;

    /**
     * Determine if the application is running in WP CLI context.
     *
     * @return bool
     */
    public function isWpCli(): bool;
}
