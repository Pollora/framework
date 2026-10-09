<?php

declare(strict_types=1);

namespace Pollora\Modules\Domain\Events;

/**
 * A module was disabled through the activation connector. It applies from the
 * next request: module providers register before anything can switch them.
 */
final readonly class ModuleDisabled
{
    /**
     * @param  string  $source  "admin" or "console"
     * @param  int|null  $userId  WordPress user who switched it, when known
     */
    public function __construct(
        public string $module,
        public string $source,
        public ?int $userId = null,
    ) {}
}
