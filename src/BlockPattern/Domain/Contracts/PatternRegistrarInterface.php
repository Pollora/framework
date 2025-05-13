<?php

declare(strict_types=1);

namespace Pollora\BlockPattern\Domain\Contracts;

use Pollora\BlockPattern\Domain\Models\Pattern;

/**
 * Port interface for registering block patterns.
 *
 * This is a port in hexagonal architecture that defines how
 * the domain communicates with the outside world regarding
 * block pattern registration.
 */
interface PatternRegistrarInterface
{
    /**
     * Register a pattern with the underlying system.
     *
     * @param Pattern $pattern The pattern to register
     */
    public function registerPattern(Pattern $pattern): void;
} 