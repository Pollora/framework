<?php

declare(strict_types=1);

namespace Pollora\Asset\Infrastructure\Laravel;

use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Asset\Domain\Contracts\RuntimeContext;

/**
 * The command line, as Pollora's console detection sees it.
 */
final readonly class LaravelRuntimeContext implements RuntimeContext
{
    public function __construct(private ConsoleDetectionService $console) {}

    public function isCommandLine(): bool
    {
        return $this->console->isConsole() || $this->console->isWpCli();
    }
}
