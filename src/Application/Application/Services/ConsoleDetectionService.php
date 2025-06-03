<?php

declare(strict_types=1);

namespace Pollora\Application\Application\Services;

use Pollora\Application\Domain\Contracts\ConsoleDetectorInterface;

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
     */
    public function __construct(ConsoleDetectorInterface $detector)
    {
        $this->detector = $detector;
    }

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
