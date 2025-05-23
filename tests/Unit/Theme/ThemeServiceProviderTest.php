<?php

declare(strict_types=1);

namespace Tests\Unit\Theme;

use Illuminate\Contracts\Foundation\Application as LaravelApplicationContract;
use Illuminate\Contracts\Translation\Loader as TranslationLoaderInterface;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\ViewFinderInterface;
use Mockery as m;
use Pollora\Application\Application\Services\ConsoleDetectionService;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Infrastructure\Providers\CollectionServiceProvider;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Config\Infrastructure\Providers\ConfigServiceProvider;
use Pollora\Theme\Application\Services\ThemeManager;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;
use Pollora\Theme\Infrastructure\Services\WordPressThemeAdapter;

beforeEach(function () {
    $this->app = m::mock(LaravelApplicationContract::class);
    $this->viewFactory = m::mock(ViewFactory::class);
    $this->translationLoader = m::mock(TranslationLoaderInterface::class);
    $this->provider = new ThemeServiceProvider($this->app);

    $this->app->shouldReceive('environment')->andReturn('testing');
    $this->app->shouldReceive('runningInConsole')->andReturn(false);
});

afterEach(function () {
    m::close();
});

it('registers all services and providers via register method', function () {
    $this->app->shouldReceive('singleton')
        ->with(WordPressThemeInterface::class, WordPressThemeAdapter::class)
        ->once();

    $this->app->shouldReceive('register')->once()->with(ConfigServiceProvider::class);
    $this->app->shouldReceive('register')->once()->with(CollectionServiceProvider::class);

    $this->app->shouldReceive('afterResolving')->once()->with(ConfigRepositoryInterface::class, m::type('Closure'));
    $this->app->shouldReceive('afterResolving')->once()->with(CollectionFactoryInterface::class, m::type('Closure'));

    $this->app->shouldReceive('make')->with('view')->andReturn($this->viewFactory);
    $mockViewFinder = m::mock(ViewFinderInterface::class);
    $this->viewFactory->shouldReceive('getFinder')->andReturn($mockViewFinder);

    $mockActualTranslationLoader = m::mock(TranslationLoaderInterface::class);
    $this->translationLoader->shouldReceive('getLoader')->andReturn($mockActualTranslationLoader);
    $this->app->shouldReceive('make')->with('translator')->andReturn($this->translationLoader);

    $mockConsoleDetectionService = m::mock(ConsoleDetectionService::class);

    $this->app->shouldReceive('singleton')
        ->once()
        ->with(ThemeService::class, m::type('Closure'))
        ->andReturnUsing(function ($class, \Closure $providerClosure) use ($mockViewFinder, $mockActualTranslationLoader, $mockConsoleDetectionService) {
            $instance = new ThemeManager(
                $this->app,
                $mockViewFinder,
                $mockActualTranslationLoader,
                $mockConsoleDetectionService
            );
            expect($instance)->toBeInstanceOf(ThemeManager::class);

            return $instance;
        });

    $this->app->shouldReceive('singleton')
        ->once()
        ->with('theme', m::type('Closure'))
        ->andReturnUsing(function ($alias, $closure) {
            $this->app->shouldReceive('make')->with(ThemeService::class)->once()->andReturn(m::mock(ThemeService::class));

            return $closure($this->app);
        });

    $configMock = m::mock(ConfigRepositoryInterface::class);
    $filesMock = m::mock(Filesystem::class);
    $this->app->shouldReceive('make')->with('config')->andReturn($configMock);
    $this->app->shouldReceive('make')->with('files')->andReturn($filesMock);

    $this->app->shouldReceive('singleton')->once()->with('theme.generator', m::type('Closure'));
    $this->app->shouldReceive('singleton')->once()->with('theme.remover', m::type('Closure'));

    $this->provider->register();
});
