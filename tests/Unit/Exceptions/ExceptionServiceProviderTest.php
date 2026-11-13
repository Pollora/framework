<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Pollora\Exceptions\Infrastructure\Handlers\ModuleAwareExceptionHandler;
use Pollora\Exceptions\Infrastructure\Providers\ExceptionServiceProvider;
use Pollora\Exceptions\Infrastructure\Services\ModuleAwareErrorViewResolver;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();

    $this->app = new Application(sys_get_temp_dir());
    $this->views = Mockery::mock(Factory::class);
    $this->app->instance('view', $this->views);
    $this->app->instance('config', new Repository);

    $this->provider = new ExceptionServiceProvider($this->app);
});

afterEach(function (): void {
    Mockery::close();
    Container::setInstance($this->previousContainer);
});

it('resolves the error view resolver as a shared instance built on the view factory', function (): void {
    $this->provider->register();

    $resolver = $this->app->make(ModuleAwareErrorViewResolver::class);
    $this->views->shouldReceive('exists')->andReturnUsing(fn (string $view): bool => $view === 'errors.404');

    expect($this->app->make(ModuleAwareErrorViewResolver::class))->toBe($resolver)
        ->and($resolver->resolveErrorView(new HttpException(404), Request::create('/'), 404))->toBe('errors.404');
});

it('replaces the exception handler with the module-aware one', function (): void {
    $this->provider->register();

    $handler = $this->app->make(ExceptionHandler::class);

    expect($handler)->toBeInstanceOf(ModuleAwareExceptionHandler::class)
        ->and($this->app->make(ExceptionHandler::class))->toBe($handler);
});

it('merges the default exception configuration under pollora.exceptions', function (): void {
    $this->provider->register();

    $config = $this->app->make('config');

    expect($config->get('pollora.exceptions.prioritize_module_views'))->toBeTrue()
        ->and($config->get('pollora.exceptions.reporting'))->toBe([])
        ->and($config->get('pollora.exceptions.fallback_views'))->toBe([
            '4xx' => 'errors.4xx',
            '5xx' => 'errors.5xx',
            'client-error' => 'errors.client-error',
            'server-error' => 'errors.server-error',
        ])
        ->and($config->get('pollora.exceptions.include_debug_info'))->toBeTrue()
        ->and($config->get('pollora.exceptions.log_view_resolution'))->toBeFalse();
});

it('does not override exception configuration the application already set', function (): void {
    $this->app->make('config')->set('pollora.exceptions', ['prioritize_module_views' => false]);

    $this->provider->register();

    $config = $this->app->make('config');

    expect($config->get('pollora.exceptions.prioritize_module_views'))->toBeFalse()
        ->and($config->get('pollora.exceptions.include_debug_info'))->toBeTrue();
});

it('offers the configuration for publishing under the pollora-exceptions tag', function (): void {
    $this->provider->register();
    $this->provider->boot();

    $paths = ServiceProvider::pathsToPublish(ExceptionServiceProvider::class, 'pollora-exceptions');

    expect(array_keys($paths))->toHaveCount(1)
        ->and(basename((string) array_key_first($paths)))->toBe('exceptions.php')
        ->and(basename((string) array_values($paths)[0]))->toBe('exceptions.php')
        ->and(file_exists((string) array_key_first($paths)))->toBeTrue();
});

it('declares what it provides', function (): void {
    expect($this->provider->provides())->toBe([
        ExceptionHandler::class,
        ModuleAwareExceptionHandler::class,
        ModuleAwareErrorViewResolver::class,
    ]);
});
