<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router;
use Pollora\Modules\Domain\Contracts\ModuleDiscoveryOrchestratorInterface;

require_once dirname(__DIR__, 4).'/src/Modules/UI/Helpers/discovery_functions.php';

beforeEach(function (): void {
    $this->container = new Container;
    Container::setInstance($this->container);
});

afterEach(function (): void {
    Container::setInstance(null);
    Mockery::close();
});

describe('discovery helper functions', function (): void {
    it('return nothing when no orchestrator is bound', function (string $function, array $arguments): void {
        expect($function(...$arguments))->toBe([]);
    })->with([
        'module' => ['pollora_discover_module', ['/m']],
        'theme' => ['pollora_discover_theme', ['/t']],
        'plugin' => ['pollora_discover_plugin', ['/p']],
        'path' => ['pollora_discover_in_path', ['/x']],
        'all in path' => ['pollora_discover_all_in_path', ['/x']],
    ]);

    it('hand the given directory to the orchestrator and return its findings', function (string $function, string $path): void {
        $orchestrator = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
        $orchestrator->shouldReceive('discoverAndReturn')->once()->with($path)->andReturn(['hooks' => ['A']]);
        $this->container->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);

        expect($function($path))->toBe(['hooks' => ['A']]);
    })->with([
        'module' => ['pollora_discover_module', '/srv/modules/Crm'],
        'theme' => ['pollora_discover_theme', '/srv/themes/nectar'],
        'plugin' => ['pollora_discover_plugin', '/srv/plugins/seo'],
        'path' => ['pollora_discover_in_path', '/srv/any'],
        'all in path' => ['pollora_discover_all_in_path', '/srv/all'],
    ]);

    it('report a failing discovery and return nothing instead of throwing', function (string $function): void {
        $orchestrator = Mockery::mock(ModuleDiscoveryOrchestratorInterface::class);
        $orchestrator->shouldReceive('discoverAndReturn')->andThrow(new RuntimeException('scan failed'));
        $handler = Mockery::mock(ExceptionHandler::class);
        $reported = [];
        $handler->shouldReceive('report')->andReturnUsing(function (Throwable $e) use (&$reported): void {
            $reported[] = $e->getMessage();
        });
        $this->container->instance(ModuleDiscoveryOrchestratorInterface::class, $orchestrator);
        $this->container->instance(ExceptionHandler::class, $handler);

        expect($function('/x'))->toBe([])
            ->and($reported)->toBe(['scan failed']);
    })->with(['pollora_discover_module', 'pollora_discover_theme', 'pollora_discover_plugin', 'pollora_discover_in_path', 'pollora_discover_all_in_path']);
});

describe('route debugging helpers', function (): void {
    beforeEach(function (): void {
        $this->router = new Router(new Dispatcher($this->container), $this->container);
        $this->container->instance('router', $this->router);
    });

    it('describe every route and set the catch-all apart as the fallback', function (): void {
        $this->router->get('blog', fn (): string => 'x')->name('blog.index')->middleware('web');
        $this->router->get('{any}', fn (): string => 'fallback');
        $this->container->instance('route.fallback.registered', true);

        $debug = pollora_debug_route_registration();

        expect($debug['routes_count'])->toBe(2)
            ->and($debug['fallback_registered'])->toBeTrue()
            ->and($debug['routes'])->toHaveCount(1)
            ->and($debug['routes'][0])->toMatchArray(['uri' => 'blog', 'name' => 'blog.index', 'middleware' => ['web']])
            ->and($debug['routes'][0]['methods'])->toContain('GET')
            ->and($debug['fallback_route']['uri'])->toBe('{any}')
            ->and($debug['modules_event_fired'])->toBeFalse()
            ->and($debug)->not->toHaveKey('error');
    });

    it('say the fallback is not registered until it is', function (): void {
        $debug = pollora_debug_route_registration();

        expect($debug['routes_count'])->toBe(0)
            ->and($debug['fallback_registered'])->toBeFalse()
            ->and($debug['fallback_route'])->toBeNull();
    });

    it('put the failure in the report instead of throwing', function (): void {
        $this->container->forgetInstance('router');
        $this->container->bind('router', fn () => throw new RuntimeException('no router'));

        expect(pollora_debug_route_registration()['error'])->toBe('no router');
    });

    it('list only routes handled by a Modules namespace or mentioning the wishlist', function (): void {
        $this->router->get('blog', 'Modules\\Blog\\Http\\PostController@index')->name('blog');
        $this->router->get('my/wishlist', fn (): string => 'x');
        $this->router->get('other', 'App\\Http\\OtherController@index');
        $this->router->get('{any}', 'Modules\\Core\\Fallback@handle');

        $routes = pollora_list_module_routes();

        expect(array_column($routes, 'uri'))->toBe(['blog', 'my/wishlist'])
            ->and($routes[0])->toMatchArray(['name' => 'blog', 'action' => 'Modules\\Blog\\Http\\PostController@index']);
    });

    it('list no module route when the router is unavailable', function (): void {
        $this->container->forgetInstance('router');
        $this->container->bind('router', fn () => throw new RuntimeException('no router'));

        expect(pollora_list_module_routes())->toBe([]);
    });
});
