<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Pollora\PostType\Domain\Contracts\PostTypeFactoryInterface;

/**
 * Facade of the post type factory.
 *
 * The facade exposes the factory's `make()` only. The fluent methods
 * (`setHierarchical()`, `supports()`...) belong to the post type object that `make()` returns,
 * not to the facade: `PostType::make('book')->setHierarchical(true)`.
 *
 * @method static \Pollora\Entity\Domain\Model\PostType make(string $slug, ?string $singular = null, ?string $plural = null, array $args = [], int $priority = 5)
 *
 * @see PostTypeFactoryInterface
 * @see \Pollora\Entity\Domain\Model\PostType
 */
class PostType extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PostTypeFactoryInterface::class;
    }
}
