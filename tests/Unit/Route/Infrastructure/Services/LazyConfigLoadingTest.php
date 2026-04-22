<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Pollora\Route\Infrastructure\Services\ExtendedRouter;

describe('LazyConfigLoading', function (): void {
    it('router works without config during construction', function (): void {
        $container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $router = new ExtendedRouter($dispatcher, $container);

        $conditions = $router->getConditions();
        expect($conditions)->toHaveKey('single');
        expect($conditions['single'])->toBe('is_single');
    });

    it('router loads config when available later', function (): void {
        $container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $router = new ExtendedRouter($dispatcher, $container);

        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            if ($key === 'wordpress.conditions') {
                return [
                    'is_custom_condition' => 'custom',
                    'is_special_condition' => 'special',
                ];
            }

            if ($key === 'wordpress.plugin_conditions') {
                return [];
            }

            return $default;
        });

        $container->instance('config', $config);

        $result = $router->resolveCondition('custom');
        expect($result)->toBe('is_custom_condition');

        $conditions = $router->getConditions();
        expect($conditions)->toHaveKey('single');
        expect($conditions)->toHaveKey('custom');
        expect($conditions['single'])->toBe('is_single');
        expect($conditions['custom'])->toBe('is_custom_condition');
    });

    it('config is only loaded once', function (): void {
        $container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $router = new ExtendedRouter($dispatcher, $container);

        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->twice()->andReturnUsing(function ($key, $default = null) {
            if ($key === 'wordpress.conditions') {
                return ['is_test' => 'test'];
            }

            if ($key === 'wordpress.plugin_conditions') {
                return [];
            }

            return $default;
        });

        $container->instance('config', $config);

        $router->resolveCondition('test');
        $router->resolveCondition('test');
        $router->getConditions();
        $router->resolveCondition('another');
    });

    it('handles config exceptions gracefully', function (): void {
        $container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $router = new ExtendedRouter($dispatcher, $container);

        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->andThrow(new Exception('Config not ready'));

        $container->instance('config', $config);

        $result = $router->resolveCondition('single');
        expect($result)->toBe('is_single');

        $conditions = $router->getConditions();
        expect($conditions)->toHaveKey('single');
    });

    it('config merges with defaults correctly', function (): void {
        $container = new Container;
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturn(null);

        $router = new ExtendedRouter($dispatcher, $container);

        $config = Mockery::mock(Repository::class);
        $config->shouldReceive('get')->andReturnUsing(function ($key, $default = null) {
            if ($key === 'wordpress.conditions') {
                return [
                    'is_front_page' => 'front',
                    'is_custom_condition' => 'custom',
                ];
            }

            if ($key === 'wordpress.plugin_conditions') {
                return [];
            }

            return $default;
        });

        $container->instance('config', $config);

        expect($router->resolveCondition('front'))->toBe('is_front_page');
        expect($router->resolveCondition('custom'))->toBe('is_custom_condition');
        expect($router->resolveCondition('single'))->toBe('is_single');
        expect($router->resolveCondition('date'))->toBe('is_date');

        $conditions = $router->getConditions();
        expect($conditions)->toHaveKey('front');
        expect($conditions)->toHaveKey('custom');
        expect($conditions)->toHaveKey('single');
        expect($conditions)->toHaveKey('date');
    });
});
