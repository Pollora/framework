<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;
use Pollora\Route\Infrastructure\Services\Resolvers\WordPressTypeResolver;
use Pollora\Route\Infrastructure\Services\WordPressConditionManager;

describe('ExtendedRouter dependency injection', function (): void {
    beforeEach(function (): void {
        $this->container = new Container;
        $dispatcher = new Dispatcher($this->container);

        $conditionManager = new WordPressConditionManager($this->container);
        $typeResolver = new WordPressTypeResolver;

        $this->router = new ExtendedRouter(
            $dispatcher,
            $this->container,
            $conditionManager,
            $typeResolver
        );
    });

    it('registers WordPress types in the container', function (): void {
        $expectedTypes = ['WP_Post', 'WP_Term', 'WP_User', 'WP_Query', 'WP'];

        foreach ($expectedTypes as $type) {
            expect($this->container->bound($type))->toBeTrue(sprintf('WordPress type %s should be bound in the container', $type));
        }
    });

    it('can resolve conditions', function (): void {
        $conditions = $this->router->getConditions();

        expect($conditions)->toBeArray();
        expect($conditions)->toHaveKey('home');
        expect($conditions['home'])->toBe('is_home');

        expect($this->router->resolveCondition('single'))->toBe('is_single');
        expect($this->router->resolveCondition('unknown'))->toBe('unknown');
    });
});
