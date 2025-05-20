<?php

declare(strict_types=1);

namespace Pollora\Collection\Infrastructure\Services;

use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Collection\Infrastructure\Adapters\LaravelCollectionAdapter;

/**
 * Laravel implementation of the CollectionFactoryInterface.
 */
class LaravelCollectionFactory implements CollectionFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function make(array $items): CollectionInterface
    {
        return new LaravelCollectionAdapter($items);
    }
} 