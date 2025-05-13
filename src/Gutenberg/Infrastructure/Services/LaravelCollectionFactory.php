<?php

declare(strict_types=1);

namespace Pollora\Gutenberg\Infrastructure\Services;

use Illuminate\Support\Collection;
use Pollora\Gutenberg\Domain\Contracts\CollectionFactoryInterface;

/**
 * Laravel implementation of the CollectionFactoryInterface.
 */
class LaravelCollectionFactory implements CollectionFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function make(array $items): Collection
    {
        return collect($items);
    }
}
