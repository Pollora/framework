<?php

declare(strict_types=1);

namespace Pollora\Application\Application\Services;

/**
 * Application service to orchestrate console detection use cases.
 */
class ConsoleDetectionService
{
    /**
     * Constructor.
     */
    public function __construct(protected \Pollora\Application\Domain\Contracts\ConsoleDetectorInterface $detector) {}

    /**
     * Check if running in console.
     */
    public function isConsole(): bool
    {
        return $this->detector->isConsole();
    }

    /**
     * Check if running in WP CLI.
     */
    public function isWpCli(): bool
    {
        return $this->detector->isWpCli();
    }
}
