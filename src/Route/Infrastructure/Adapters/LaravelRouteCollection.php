<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Adapters;

use Pollora\Route\Domain\Models\RouteCollectionEntity;
use ReflectionClass;

/**
 * Laravel adapter for RouteCollection entities.
 *
 * This class provides bidirectional conversion between domain RouteCollectionEntity
 * objects and Laravel/Illuminate RouteCollection objects.
 */
class LaravelRouteCollection
{
    /**
     * @param LaravelRoute $routeAdapter
     */
    public function __construct(
        private LaravelRoute $routeAdapter
    ) {}

    /**
     * Convert a domain RouteCollectionEntity to a framework RouteCollection.
     *
     * @param RouteCollectionEntity $collection Domain collection entity
     * @return RouteCollection Framework route collection
     */
    public function toFrameworkCollection(RouteCollectionEntity $collection): RouteCollection
    {
        $frameworkCollection = new RouteCollection();

        // Convert each route entity to a framework route and add to collection
        foreach ($collection->getRoutes() as $routeEntity) {
            // Create a framework route prototype
            $routePrototype = new Route(
                $routeEntity->getMethods(),
                $routeEntity->getUri(),
                $routeEntity->getAction()
            );

            // Convert to full framework route
            $frameworkRoute = $this->routeAdapter->toFrameworkRoute($routeEntity, $routePrototype);

            // Add to framework collection using its internal methods
            $this->addRouteToCollection($frameworkCollection, $frameworkRoute);
        }

        return $frameworkCollection;
    }

    /**
     * Convert a framework RouteCollection to a domain RouteCollectionEntity.
     *
     * @param RouteCollection $collection Framework route collection
     * @return RouteCollectionEntity Domain collection entity
     */
    public function toDomainCollection(RouteCollection $collection): RouteCollectionEntity
    {
        $domainCollection = new RouteCollectionEntity();

        // Convert each framework route to a domain entity and add to collection
        foreach ($collection->getRoutes() as $frameworkRoute) {
            $routeEntity = $this->routeAdapter->toDomainEntity($frameworkRoute);
            $domainCollection->addRoute($routeEntity);
        }

        return $domainCollection;
    }

    /**
     * Add a route to the framework collection using reflection to access protected methods.
     *
     * @param RouteCollection $collection Framework collection
     * @param Route $route Framework route
     * @return void
     */
    private function addRouteToCollection(RouteCollection $collection, Route $route): void
    {
        // Use reflection to access the protected addToCollections method
        $reflectionClass = new ReflectionClass($collection);
        $method = $reflectionClass->getMethod('addToCollections');
        $method->setAccessible(true);
        $method->invoke($collection, $route);
    }
}
