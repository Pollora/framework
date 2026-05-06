<?php

declare(strict_types=1);

namespace Pollora\Hook\Domain\Contracts;

/**
 * Resolves class instances for hook callbacks.
 *
 * This interface abstracts class instantiation so the domain layer
 * remains framework-agnostic while infrastructure implementations
 * can leverage dependency injection containers.
 */
interface CallbackResolverInterface
{
    /**
     * Resolve an instance of the given class.
     *
     * @param  string  $className  Fully qualified class name
     * @return object The resolved instance
     */
    public function resolve(string $className): object;
}
