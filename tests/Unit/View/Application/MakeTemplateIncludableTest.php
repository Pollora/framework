<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\View\View;
use Illuminate\View\ViewFinderInterface;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\View\Application\UseCases\MakeTemplateIncludableUseCase;
use Pollora\View\Application\UseCases\RegisterTemplateHierarchyFiltersUseCase;
use Pollora\View\Application\UseCases\ResolveBladeTemplateUseCase;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;
use Pollora\View\Domain\Contracts\TemplateHierarchyFilterInterface;
use Pollora\View\Infrastructure\Services\WordPressTemplateHierarchyFilter;

/**
 * A plugin that answers from template_redirect may include the query template
 * itself (WooCommerce's Review Order 404): a Blade file included by PHP prints
 * its source, so it gets a loader that renders the view (#419).
 */
beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/pollora-includable-'.bin2hex(random_bytes(6));
    mkdir($this->dir, 0777, true);
    touch($this->dir.'/404.blade.php');
    touch($this->dir.'/404.php');

    $this->finder = Mockery::mock(TemplateFinderInterface::class);
    $this->finder->allows('getViewNameFromPath')->andReturn('404');

    $view = Mockery::mock(View::class);
    $view->allows('makeLoader')->andReturn('/compiled/abc-loader.php');

    $this->views = Mockery::mock(ViewFactory::class);
    $this->views->allows('exists')->with('404')->andReturnTrue();
    $this->views->allows('make')->with('404')->andReturn($view);

    $this->useCase = new MakeTemplateIncludableUseCase($this->finder, $this->views);
});

afterEach(function (): void {
    exec('rm -rf '.escapeshellarg($this->dir));
});

it('hands back the loader of a Blade view', function (): void {
    expect($this->useCase->execute($this->dir.'/404.blade.php'))->toBe('/compiled/abc-loader.php');
});

it('leaves a PHP template, a missing file and an unknown view alone', function (): void {
    expect($this->useCase->execute($this->dir.'/404.php'))->toBe($this->dir.'/404.php')
        ->and($this->useCase->execute($this->dir.'/gone.blade.php'))->toBe($this->dir.'/gone.blade.php');

    $views = Mockery::mock(ViewFactory::class);
    $views->allows('exists')->andReturnFalse();

    expect((new MakeTemplateIncludableUseCase($this->finder, $views))->execute($this->dir.'/404.blade.php'))->toBe($this->dir.'/404.blade.php');
});

it('makes a template includable only while template_redirect runs', function (bool $redirecting, string $expected): void {
    Functions\when('doing_action')->alias(fn (string $hook): bool => $redirecting && $hook === 'template_redirect');

    $filter = new WordPressTemplateHierarchyFilter(
        $this->finder,
        Mockery::mock(ResolveBladeTemplateUseCase::class),
        Mockery::mock(ViewFinderInterface::class),
        $this->useCase,
    );

    expect($filter->includableTemplate($this->dir.'/404.blade.php'))->toBe($expected === 'loader' ? '/compiled/abc-loader.php' : $this->dir.'/404.blade.php');
})->with([
    'during template_redirect' => [true, 'loader'],
    // The FrontendController resolves the hierarchy later and needs the Blade path.
    'afterwards' => [false, 'path'],
]);

it('filters every query template last', function (): void {
    $added = [];
    $filter = Mockery::mock(Filter::class);
    $filter->allows('add')->andReturnUsing(function (string $hook, callable $callback, int $priority = 10) use (&$added, $filter): Filter {
        $added[$hook] = $priority;

        return $filter;
    });

    (new RegisterTemplateHierarchyFiltersUseCase($filter, Mockery::mock(TemplateHierarchyFilterInterface::class)->shouldIgnoreMissing()))->execute();

    expect($added)->toHaveKey('404_template', PHP_INT_MAX)
        ->and($added)->toHaveKey('single_template', PHP_INT_MAX)
        ->and($added)->toHaveKey('404_template_hierarchy', 10);
});
