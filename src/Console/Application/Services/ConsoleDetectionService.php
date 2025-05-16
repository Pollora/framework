<?php

namespace Pollora\Console\Application\Services;

use Pollora\Console\Domain\Contracts\ConsoleDetectorInterface;

/**
 * Application service to orchestrate console detection use cases.
 */
class ConsoleDetectionService
{
    /**
     * @var ConsoleDetectorInterface
     */
    protected $detector;

    /**
     * Constructor.
     *
     * @param ConsoleDetectorInterface $detector
     */
    public function __construct(ConsoleDetectorInterface $detector)
    {
        $this->detector = $detector;
    }

    /**
     * Check if running in console.
     *
     * @return bool
     */
    public function isConsole(): bool
    {
        return $this->detector->isConsole();
    }

    /**
     * Check if running in WP CLI.
     *
     * @return bool
     */
    public function isWpCli(): bool
    {
        return $this->detector->isWpCli();
    }
}
