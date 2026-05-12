<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

/**
 * Domain contract defining WordPress-specific route capabilities.
 *
 * This interface abstracts the WordPress routing behavior from the
 * infrastructure implementation, keeping the Domain layer free from
 * framework dependencies.
 */
interface WordPressRouteInterface
{
    /**
     * Check if this is a WordPress route.
     */
    public function isWordPressRoute(): bool;

    /**
     * Set whether this is a WordPress route.
     *
     * @return $this
     */
    public function setIsWordPressRoute(bool $isWordPressRoute): static;

    /**
     * Get the resolved WordPress condition function name.
     */
    public function getCondition(): string;

    /**
     * Set the WordPress condition.
     *
     * @return $this
     */
    public function setCondition(string $condition): static;

    /**
     * Check if route has a WordPress condition.
     */
    public function hasCondition(): bool;

    /**
     * Get the condition parameters.
     *
     * @return array<mixed>
     */
    public function getConditionParameters(): array;

    /**
     * Set the condition parameters.
     *
     * @param  array<mixed>  $parameters
     * @return $this
     */
    public function setConditionParameters(array $parameters): static;

    /**
     * Set the condition resolver instance.
     *
     * @return $this
     */
    public function setConditionResolver(ConditionResolverInterface $resolver): static;
}
