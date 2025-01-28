<?php

declare(strict_types=1);

namespace Pollora\Attributes;

/**
 * Class Hook
 *
 * Abstract base class for WordPress hook attributes.
 * Provides common properties for hook attributes.
 */
abstract class Hook
{
    /**
     * Constructor for the Hook class.
     *
     * @param string $hook The name of the WordPress hook.
     * @param int $priority The priority of the hook.
     */
    public function __construct(
        public string $hook,
        public int $priority = 10
    ) {}
}
