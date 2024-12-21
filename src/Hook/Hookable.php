<?php

declare(strict_types=1);

namespace Pollora\Hook;

use Illuminate\Contracts\Container\Container;
use Pollora\Hook\Contracts\HookableInterface;

/**
 * Abstract base class for hookable components.
 * 
 * Provides base functionality for classes that need to register
 * WordPress hooks with dependency injection support.
 */
abstract class Hookable implements HookableInterface
{
    /**
     * The hook(s) to register.
     *
     * @var string|array|null
     */
    public $hook;

    /**
     * The priority for the hook(s).
     *
     * @var int
     */
    public int $priority = 10;

    /**
     * Create a new hookable instance.
     *
     * @param Container $container The application container instance
     */
    public function __construct(protected Container $container) {}

    /**
     * Register the hook(s).
     * 
     * @return mixed
     */
    abstract public function register();
}
