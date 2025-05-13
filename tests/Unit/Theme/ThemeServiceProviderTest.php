<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Services\TemplateHierarchy;
use Pollora\Theme\Infrastructure\Providers\ThemeComponentProvider;
use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;
use Pollora\Theme\Infrastructure\Services\ComponentFactory;

describe('ThemeServiceProvider', function () {
    it('enregistre les singletons et appelle register sur ThemeComponentProvider', function () {
        $app = \Mockery::mock(Application::class);

        // Mock tous les singletons attendus par le provider
        foreach ([
            ThemeService::class,
            'theme',
            'theme.generator',
            'theme.remover',
            ServiceLocator::class,
            ComponentFactory::class,
            ThemeComponentProvider::class,
            TemplateHierarchy::class,
            'Pollora\\Theme\\Infrastructure\\Providers\\TemplateHierarchy',
            'Pollora\\Theme\\Domain\\Services\\TemplateHierarchy',
        ] as $abstract) {
            $app->shouldReceive('singleton')->withArgs(function ($a, $closure) use ($abstract) {
                return $a === $abstract && is_callable($closure);
            })->atMost()->once();
        }

        // Mock ThemeComponentProvider et attente sur register()
        $themeComponentProvider = \Mockery::mock(ThemeComponentProvider::class);
        $themeComponentProvider->shouldReceive('register')->once();
        $app->shouldReceive('make')->with(ThemeComponentProvider::class)->andReturn($themeComponentProvider);

        // Mock ServiceLocator pour injection hexagonale
        $locator = \Mockery::mock(ServiceLocator::class);
        $action = \Mockery::mock(Action::class);
        $action->shouldReceive('add')->with('after_setup_theme', \Mockery::type('array'))->once();
        $locator->shouldReceive('resolve')->with(Action::class)->andReturn($action);
        $app->shouldReceive('make')->with(ServiceLocator::class)->andReturn($locator);

        $provider = new ThemeServiceProvider($app);
        $provider->register();
    });
});
