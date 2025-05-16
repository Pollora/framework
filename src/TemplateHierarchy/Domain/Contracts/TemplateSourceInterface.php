<?php

declare(strict_types=1);

namespace Pollora\TemplateHierarchy\Domain\Contracts;

/**
 * Interface for template source components that provide template resolvers.
 */
interface TemplateSourceInterface
{
    /**
     * Return resolvers applicable to the current request.
     *
     * @return TemplateResolverInterface[]
     */
    public function getResolvers(): array;

    /**
     * Get the priority of this template source.
     * Lower numbers have higher priority.
     */
    public function getPriority(): int;

    /**
     * Get the name/identifier of this template source.
     */
    public function getName(): string;
}
