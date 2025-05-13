<?php

declare(strict_types=1);

namespace Pollora\Theme\Domain\Support;

use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;

/**
 * Theme collection utility class.
 * 
 * Provides a clean, object-oriented interface for creating and working 
 * with collections while maintaining hexagonal architecture principles.
 */
class ThemeCollection
{
    private static ?CollectionFactoryInterface $collectionFactory = null;

    /**
     * Set the collection factory.
     */
    public static function setFactory(CollectionFactoryInterface $factory): void
    {
        self::$collectionFactory = $factory;
    }

    /**
     * Create a collection from the given items.
     *
     * @param array $items Items to collect
     * @return CollectionInterface Framework-agnostic collection
     */
    public static function make(array $items = []): CollectionInterface
    {
        if (self::$collectionFactory === null) {
            self::$collectionFactory = app(CollectionFactoryInterface::class);
        }

        return self::$collectionFactory->make($items);
    }
} 