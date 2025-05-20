<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Mockery as m;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Services\TemplateHierarchy;
use Pollora\Theme\Infrastructure\Providers\ThemeComponentProvider;
use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;
use Pollora\Theme\Infrastructure\Services\ComponentFactory;

describe('ThemeServiceProvider', function () {
    it('registers services in Laravel container', function () {
        $mockApp = m::mock('Illuminate\\Contracts\\Foundation\\Application');

        // Mock tous les singletons attendus par le provider
        foreach ([
            ThemeService::class,
            'theme',
            'theme.generator',
            'theme.remover',
            ComponentFactory::class,
            ThemeComponentProvider::class,
            TemplateHierarchy::class,
            'Pollora\\Theme\\Infrastructure\\Providers\\TemplateHierarchy',
            'Pollora\\Theme\\Domain\\Services\\TemplateHierarchy',
        ] as $abstract) {
            $mockApp->shouldReceive('singleton')->withArgs(function ($a, $closure) use ($abstract) {
                return $a === $abstract && is_callable($closure);
            })->atMost()->once();
        }

        // Mock register() expectations for config providers
        $mockApp->shouldReceive('register')->with('Pollora\\Config\\Infrastructure\\Providers\\ConfigServiceProvider')->once();
        $mockApp->shouldReceive('register')->with('Pollora\\Support\\Infrastructure\\Providers\\CollectionServiceProvider')->once();

        // Mock ThemeComponentProvider et attente sur register()
        $themeComponentProvider = m::mock(ThemeComponentProvider::class);
        $themeComponentProvider->shouldReceive('register')->once();
        $mockApp->shouldReceive('make')->with(ThemeComponentProvider::class)->andReturn($themeComponentProvider);

        // Mock Action pour injection hexagonale
        $action = m::mock(Action::class);
        $action->shouldReceive('add')->with('after_setup_theme', m::type('array'))->once();
        $mockApp->shouldReceive('make')->with(Action::class)->andReturn($action);

        $provider = new ThemeServiceProvider($mockApp);
        $provider->register();
    });
});
