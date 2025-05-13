<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Pollora\Container\Domain\ServiceLocator;
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

beforeEach(function () {
    $this->app = Mockery::mock(Application::class);
    $this->provider = new ThemeServiceProvider($this->app);
});

test('register binds all required singletons, resolves and calls register on ThemeComponentProvider, and registers commands', function () {
    // Expect singleton binding for all known classes used in ThemeServiceProvider
    $this->app->shouldReceive('singleton')->with(ThemeService::class, Mockery::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with('theme', Mockery::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with('theme.generator', Mockery::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with('theme.remover', Mockery::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with(ServiceLocator::class, Mockery::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with(ComponentFactory::class, Mockery::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with(ThemeComponentProvider::class, Mockery::type('callable'))->once();
    $this->app->shouldReceive('singleton')->with(TemplateHierarchy::class, Mockery::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Templates::class, Mockery::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Sidebar::class, Mockery::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Support::class, Mockery::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(Menus::class, Mockery::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(ImageSize::class, Mockery::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('singleton')->with(ThemeInitializer::class, Mockery::type('callable'))->zeroOrMoreTimes();
    $this->app->shouldReceive('commands')->with(['theme.generator', 'theme.remover'])->zeroOrMoreTimes();

    // Ajoute un mock pour le singleton TemplateHierarchy (domaine)
    $this->app->shouldReceive('singleton')->withArgs(function ($a, $closure) {
        return $a === 'Pollora\\Theme\\Domain\\Services\\TemplateHierarchy' && is_callable($closure);
    })->zeroOrMoreTimes();

    // Ajoute un mock pour le singleton TemplateHierarchy
    $this->app->shouldReceive('singleton')->withArgs(function ($a, $closure) {
        return $a === 'Pollora\\Theme\\Infrastructure\\Providers\\TemplateHierarchy' && is_callable($closure);
    })->atMost()->once();

    // Ajout pour ServiceLocator injection hexagonale
    $mockLocator = Mockery::mock(ServiceLocator::class);
    $mockAction = Mockery::mock(Action::class);
    $mockAction->shouldReceive('add')->andReturn($mockAction);
    $mockLocator->shouldReceive('resolve')->with(Action::class)->andReturn($mockAction);
    $this->app->shouldReceive('make')->with(ServiceLocator::class)->andReturn($mockLocator);

    // Mock make() for ThemeComponentProvider and other required services
    $themeComponentProviderMock = Mockery::mock(ThemeComponentProvider::class);
    $themeComponentProviderMock->shouldReceive('register')->once();
    $this->app->shouldReceive('make')->with(ThemeComponentProvider::class)->andReturn($themeComponentProviderMock);
    // Mock Action and Filter services with add() method
    $actionMock = Mockery::mock(Action::class);
    $actionMock->shouldReceive('add')->andReturn($actionMock);
    $this->app->shouldReceive('make')->with(Action::class)->andReturn($actionMock);
    $filterMock = Mockery::mock(Filter::class);
    $filterMock->shouldReceive('add')->andReturn($filterMock);
    $this->app->shouldReceive('make')->with(Filter::class)->andReturn($filterMock);
    // Mock config for ThemeInitializer, etc.
    $this->app->shouldReceive('make')->with('config')->andReturn([]);
    // Mock ComponentFactory
    $factory = Mockery::mock(ComponentFactory::class);
    $factory->shouldReceive('make')->zeroOrMoreTimes()->andReturn(new class implements \Pollora\Theme\Domain\Contracts\ThemeComponent
    {
        public function register(): void {}
    });
    $this->app->shouldReceive('make')->with(ComponentFactory::class)->andReturn($factory);

    $this->provider->register();
});
