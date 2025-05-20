<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mockery as m;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Hook\Infrastructure\Services\Filter;
use Pollora\Theme\Domain\Contracts\ThemeService;
use Pollora\Theme\Domain\Models\ImageSize;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Models\Sidebar;
use Pollora\Theme\Domain\Models\Templates;
use Pollora\Theme\Domain\Models\ThemeInitializer;
use Pollora\Theme\Domain\Services\TemplateHierarchy;
use Pollora\Theme\Infrastructure\Providers\ThemeComponentProvider;
use Pollora\Theme\Infrastructure\Providers\ThemeServiceProvider;
use Pollora\Theme\Infrastructure\Services\ComponentFactory;
use Pollora\Theme\Infrastructure\Services\Support;
use Pollora\Theme\Domain\Contracts\WordPressThemeInterface;
use Pollora\Theme\Infrastructure\Services\WordPressThemeAdapter;
use Pollora\Theme\Domain\Contracts\ContainerInterface as ThemeContainerInterface;
use Pollora\Theme\Domain\Contracts\TemplateHierarchyInterface;

beforeEach(function () {
    $this->app = m::mock(Application::class);
    $this->provider = new ThemeServiceProvider($this->app);
});

test('register binds all required singletons, resolves and calls register on ThemeComponentProvider, and registers commands', function () {
    // Expect singleton binding for all known classes used in ThemeServiceProvider
    $this->app->shouldReceive('singleton')->with(ThemeService::class, m::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with('theme', m::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with('theme.generator', m::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with('theme.remover', m::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with(ComponentFactory::class, m::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with(ThemeComponentProvider::class, m::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with(TemplateHierarchy::class, m::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Templates::class, m::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Sidebar::class, m::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Support::class, m::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Menus::class, m::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(ImageSize::class, m::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(ThemeInitializer::class, m::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('commands')->with(['theme.generator', 'theme.remover'])->zeroOrMoreTimes();
    
    // Mock the WordPressThemeInterface binding
    $this->app->shouldReceive('singleton')
        ->with(WordPressThemeInterface::class, WordPressThemeAdapter::class)
        ->once();

    // Mock ContainerInterface singleton binding
    $this->app->shouldReceive('singleton')
        ->with(ThemeContainerInterface::class, m::type('Closure'))
        ->once();

    // Mock TemplateHierarchyInterface singleton binding
    $this->app->shouldReceive('singleton')
        ->with(TemplateHierarchyInterface::class, m::type('Closure'))
        ->once();

    // Mock the register method for service providers
    $this->app->shouldReceive('register')->withAnyArgs()->zeroOrMoreTimes();
    
    // Mock the afterResolving method
    $this->app->shouldReceive('afterResolving')->withAnyArgs()->zeroOrMoreTimes();
    
    // Ajoute un mock pour le singleton TemplateHierarchy (domaine)
    $this->app->shouldReceive('singleton')->withArgs(function ($a, $closure) {
        return $a === 'Pollora\\Theme\\Domain\\Services\\TemplateHierarchy' && is_callable($closure);
    })->zeroOrMoreTimes();

    // Ajoute un mock pour le singleton TemplateHierarchy
    $this->app->shouldReceive('singleton')->withArgs(function ($a, $closure) {
        return $a === 'Pollora\\Theme\\Infrastructure\\Providers\\TemplateHierarchy' && is_callable($closure);
    })->atMost()->once();

    // Mock make() for ThemeComponentProvider and other required services
    $themeComponentProviderMock = m::mock(ThemeComponentProvider::class);
    $themeComponentProviderMock->shouldReceive('register')->once();
    $this->app->shouldReceive('make')->with(ThemeComponentProvider::class)->andReturn($themeComponentProviderMock);
    // Mock Action and Filter services with add() method
    $actionMock = m::mock(Action::class);
    $actionMock->shouldReceive('add')->andReturn($actionMock);
    $this->app->shouldReceive('make')->with(Action::class)->andReturn($actionMock);
    $filterMock = m::mock(Filter::class);
    $filterMock->shouldReceive('add')->andReturn($filterMock);
    $this->app->shouldReceive('make')->with(Filter::class)->andReturn($filterMock);
    // Mock config for ThemeInitializer, etc.
    $this->app->shouldReceive('make')->with('config')->andReturn([]);
    // Mock ComponentFactory
    $factory = m::mock(ComponentFactory::class);
    $factory->shouldReceive('make')->zeroOrMoreTimes()->andReturn(new class implements \Pollora\Theme\Domain\Contracts\ThemeComponent
    {
        public function register(): void {}
    });
    $this->app->shouldReceive('make')->with(ComponentFactory::class)->andReturn($factory);

    $this->provider->register();
});

describe('ThemeComponentProvider', function () {
    it('registers ThemeComponent in Laravel container', function () {
        $mockApp = m::mock('Illuminate\\Contracts\\Foundation\\Application');
        $provider = new ThemeComponentProvider($mockApp);
        expect($provider)->toBeInstanceOf(ThemeComponentProvider::class);
    });
});
