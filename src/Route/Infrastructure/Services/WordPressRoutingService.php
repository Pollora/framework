<?php

declare(strict_types=1);

namespace Pollora\Route\Infrastructure\Services;

use Illuminate\Contracts\Container\Container;
use Pollora\Route\Application\UseCases\BindWordPressParametersUseCase;
use Pollora\Route\Application\UseCases\RegisterWordPressTypesUseCase;
use Pollora\Route\Domain\Models\Route;
use Pollora\Route\Infrastructure\Services\Contracts\WordPressConditionManagerInterface;

/**
 * Service responsible for WordPress-specific routing operations.
 *
 * Encapsulates condition resolution and delegates type registration
 * and parameter binding to dedicated use cases.
 */
class WordPressRoutingService
{
    public function __construct(
        private readonly WordPressConditionManagerInterface $conditionManager,
        private readonly RegisterWordPressTypesUseCase $registerTypesUseCase,
        private readonly BindWordPressParametersUseCase $bindParametersUseCase,
    ) {}

    /**
     * Resolve a condition alias to the actual WordPress function name.
     *
     * @example resolveCondition('single') returns 'is_single'
     * @example resolveCondition('is_page') returns 'is_page'
     */
    public function resolveCondition(string $condition): string
    {
        return $this->conditionManager->resolveCondition($condition);
    }

    /**
     * Get all registered WordPress condition aliases.
     *
     * @return array<string, string>
     */
    public function getConditions(): array
    {
        return $this->conditionManager->getConditions();
    }

    /**
     * Register WordPress types in Laravel's dependency injection container.
     *
     * Binds WP_Post, WP_Term, WP_User, WP_Query, and WP as resolvable types,
     * enabling type-hinted injection in controller methods and closures.
     */
    public function registerWordPressTypes(Container $container): void
    {
        $this->registerTypesUseCase->execute($container);
    }

    /**
     * Bind WordPress parameters to a route based on its action's type-hints.
     *
     * Inspects the route's callable via reflection and injects resolved WordPress
     * objects (WP_Post, WP_Term, etc.) as route parameters.
     */
    public function bindWordPressParameters(Route $route): void
    {
        $this->bindParametersUseCase->execute($route);
    }
}
