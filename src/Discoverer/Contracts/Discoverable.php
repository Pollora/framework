<?php

declare(strict_types=1);

namespace Pollora\Discoverer\Contracts;

/**
 * Interface for discoverable classes.
 *
 * Classes implementing this interface can be automatically discovered
 * and will have their register method called upon discovery.
 */
interface Discoverable
{
    /**
     * Register the discoverable instance.
     *
     * This method is called when the class is discovered during application boot.
     *
     * @return void
     */
    public function register(): void;
}
