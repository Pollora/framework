<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\View\ViewFinderInterface;
use Mockery as m;
use Pollora\Filesystem\Filesystem;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Hook\Domain\Contract\HookInterface;
use Pollora\View\Application\Services\TemplateHierarchyService;
use Pollora\View\Application\UseCases\MakeTemplateIncludableUseCase;
use Pollora\View\Application\UseCases\RegisterTemplateHierarchyFiltersUseCase;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\View\Domain\Contracts\TemplateHierarchyFilterInterface;
use Pollora\View\Infrastructure\Providers\TemplateHierarchyServiceProvider;
use Pollora\View\Infrastructure\Services\FileSystemTemplateFinder;
use Pollora\View\Infrastructure\Services\TemplateMarker;
use Pollora\View\Infrastructure\Services\WordPressTemplateHierarchyFilter;

function hierarchyProviderApp(?Filter $filter = null): Application
{
    $app = new Application(sys_get_temp_dir());
    $finder = m::mock(ViewFinderInterface::class);
    $view = m::mock(Illuminate\View\Factory::class);
    $view->shouldReceive('getFinder')->andReturn($finder);
    $app->instance('view', $view);
    $app->instance(Filter::class, $filter ?? m::mock(Filter::class)->shouldIgnoreMissing());
    $app->instance(Action::class, m::mock(Action::class)->shouldIgnoreMissing());

    return $app;
}

beforeEach(function (): void {
    $this->previousContainer = Container::getInstance();
});

afterEach(function (): void {
    Container::setInstance($this->previousContainer);
});

describe('TemplateHierarchyServiceProvider bindings', function (): void {
    it('wires the finder, the filter and the use cases together', function (): void {
        $app = hierarchyProviderApp();
        (new TemplateHierarchyServiceProvider($app))->register();

        expect($app->make(Filesystem::class))->toBe($app->make(Filesystem::class))
            ->and($app->make(TemplateFinderInterface::class))->toBeInstanceOf(FileSystemTemplateFinder::class)
            ->and($app->make(TemplateHierarchyFilterInterface::class))->toBeInstanceOf(WordPressTemplateHierarchyFilter::class)
            ->and($app->make(ResolveBladeTemplateUseCase::class))->toBeInstanceOf(ResolveBladeTemplateUseCase::class)
            ->and($app->make(MakeTemplateIncludableUseCase::class))->toBeInstanceOf(MakeTemplateIncludableUseCase::class)
            ->and($app->make(RegisterTemplateHierarchyFiltersUseCase::class))->toBeInstanceOf(RegisterTemplateHierarchyFiltersUseCase::class)
            ->and($app->make(TemplateHierarchyService::class))->toBe($app->make(TemplateHierarchyService::class));
    });

    it('shares one marker between template_include and wp_head', function (): void {
        $app = hierarchyProviderApp();
        (new TemplateHierarchyServiceProvider($app))->register();

        expect($app->make(TemplateMarker::class))->toBe($app->make(TemplateMarker::class));
    });

    it('declares every service it provides', function (): void {
        $provides = (new TemplateHierarchyServiceProvider(hierarchyProviderApp()))->provides();

        expect($provides)->toContain(
            Filesystem::class,
            TemplateFinderInterface::class,
            TemplateHierarchyFilterInterface::class,
            ResolveBladeTemplateUseCase::class,
            RegisterTemplateHierarchyFiltersUseCase::class,
            TemplateHierarchyService::class,
            TemplateMarker::class,
        )->and($provides)->toHaveCount(7);
    });
});

describe('TemplateHierarchyServiceProvider boot', function (): void {
    it('registers the template_include filter of the hierarchy on boot', function (): void {
        $hooks = [];
        $filter = m::mock(Filter::class);
        $filter->shouldReceive('add')->andReturnUsing(function (string $hook) use (&$hooks): HookInterface {
            $hooks[] = $hook;

            return m::mock(HookInterface::class);
        });
        $app = hierarchyProviderApp($filter);
        $provider = new TemplateHierarchyServiceProvider($app);
        $provider->register();

        $provider->boot();

        expect($hooks)->toContain('template_include', 'index_template_hierarchy', 'theme_templates');
    });
});
