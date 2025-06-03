<?php

declare(strict_types=1);

namespace Pollora\BlockCategory\Domain\Contracts;

use Pollora\Collection\Domain\Contracts\CollectionInterface;

/**
 * Port interface for registering block categories.
 *
 * This is a port in hexagonal architecture that defines how
 * the domain communicates with the outside world regarding
 * block category registration.
 */
interface BlockCategoryRegistrarInterface
{
    /**
     * Register a collection of block categories with the underlying system.
     *
     * @param  CollectionInterface  $categories  Collection of block categories data
     */
    public function registerCategories(CollectionInterface $categories): void;
}
