<?php

declare(strict_types=1);

namespace Pollora\Hook\Contracts;

/**
 * Interface for components that can be hooked into WordPress.
 *
 * Defines the contract for classes that need to register WordPress hooks.
 */
interface HookableInterface
{
    /**
     * Register the component's hooks.
     *
     * @return mixed
     */
    public function register();
}
