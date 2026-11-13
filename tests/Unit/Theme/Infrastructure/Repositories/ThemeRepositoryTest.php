<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Collection\Domain\Contracts\CollectionFactoryInterface;
use Pollora\Collection\Domain\Contracts\CollectionInterface;
use Pollora\Modules\Domain\Exceptions\ModuleException;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Pollora\Theme\Domain\Models\ThemeModule;
use Pollora\Theme\Infrastructure\Repositories\ThemeRepository;
use Pollora\Theme\Infrastructure\Services\WordPressThemeParser;
use Psr\Log\LoggerInterface;

function repoTheme(string $name, bool $enabled = true, int $priority = 0): ThemeModule
{
    $theme = new ThemeModule($name, '/themes/'.$name);
    $theme->setEnabled($enabled);
    $theme->set('priority', $priority);

    return $theme;
}

function repoRegistrar(?ThemeModule $active, ?Throwable $throws = null): ThemeRegistrarInterface
{
    $registrar = Mockery::mock(ThemeRegistrarInterface::class);
    $expectation = $registrar->shouldReceive('getActiveTheme');
    if ($throws instanceof Throwable) {
        $expectation->andThrow($throws);
    } else {
        $expectation->andReturn($active);
    }

    return $registrar;
}

function repoMake(?ThemeRegistrarInterface $registrar, ?CollectionFactoryInterface $factory = null): ThemeRepository
{
    $app = new Container;
    if ($registrar instanceof ThemeRegistrarInterface) {
        $app->instance(ThemeRegistrarInterface::class, $registrar);
    }

    return new ThemeRepository($app, new WordPressThemeParser, $factory ?? Mockery::mock(CollectionFactoryInterface::class));
}

describe('ThemeRepository', function (): void {
    it('is empty when no registrar is bound', function (): void {
        $repo = repoMake(null);

        expect($repo->all())->toBe([])->and($repo->count())->toBe(0)->and($repo->has('x'))->toBeFalse();
    });

    it('exposes the registrar active theme keyed by its lowercase name', function (): void {
        $theme = repoTheme('Demo');
        $repo = repoMake(repoRegistrar($theme));

        expect($repo->all())->toBe(['demo' => $theme])
            ->and($repo->find('DEMO'))->toBe($theme)
            ->and($repo->has('demo'))->toBeTrue()
            ->and($repo->findOrFail('Demo'))->toBe($theme)
            ->and($repo->count())->toBe(1);
    });

    it('throws a not-found exception when the theme is unknown', function (): void {
        repoMake(repoRegistrar(repoTheme('demo')))->findOrFail('other');
    })->throws(ModuleException::class);

    it('ignores a registrar without an active theme', function (): void {
        expect(repoMake(repoRegistrar(null))->all())->toBe([]);
    });

    it('filters themes by enabled status', function (): void {
        $on = repoMake(repoRegistrar(repoTheme('on', true)));
        $off = repoMake(repoRegistrar(repoTheme('off', false)));

        expect(array_keys($on->allEnabled()))->toBe(['on'])
            ->and($on->allDisabled())->toBe([])
            ->and(array_keys($off->allDisabled()))->toBe(['off'])
            ->and($off->allEnabled())->toBe([])
            ->and(array_keys($off->getByStatus(false)))->toBe(['off']);
    });

    it('logs and keeps working when the registrar throws', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Failed to load active theme from registrar: kaput');
        $app = new Container;
        $app->instance(ThemeRegistrarInterface::class, repoRegistrar(null, new RuntimeException('kaput')));
        $app->instance(LoggerInterface::class, $logger);

        $repo = new ThemeRepository($app, new WordPressThemeParser, Mockery::mock(CollectionFactoryInterface::class));

        expect($repo->all())->toBe([]);
    });

    it('survives a registrar failure when no logger is available', function (): void {
        $repo = repoMake(repoRegistrar(null, new RuntimeException('kaput')));

        expect($repo->all())->toBe([]);
    });

    it('loads the themes once until the cache is reset', function (): void {
        $first = repoTheme('first');
        $second = repoTheme('second');
        $registrar = Mockery::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('getActiveTheme')->twice()->andReturn($first, $second);
        $repo = repoMake($registrar);

        expect(array_keys($repo->all()))->toBe(['first'])
            ->and(array_keys($repo->all()))->toBe(['first']);

        $repo->resetCache();

        expect(array_keys($repo->all()))->toBe(['second']);
    });

    it('reloads from the registrar on scan and syncWithRegistrar', function (): void {
        $registrar = Mockery::mock(ThemeRegistrarInterface::class);
        $registrar->shouldReceive('getActiveTheme')->times(3)->andReturn(repoTheme('a'), repoTheme('b'), repoTheme('c'));
        $repo = repoMake($registrar);

        expect(array_keys($repo->all()))->toBe(['a'])
            ->and(array_keys($repo->scan()))->toBe(['b'])
            ->and($repo->syncWithRegistrar())->toBe($repo)
            ->and(array_keys($repo->all()))->toBe(['c']);
    });

    it('wraps the themes in a collection through the factory', function (): void {
        $theme = repoTheme('demo');
        $collection = Mockery::mock(CollectionInterface::class);
        $factory = Mockery::mock(CollectionFactoryInterface::class);
        $factory->shouldReceive('make')->once()->with(['demo' => $theme])->andReturn($collection);

        expect(repoMake(repoRegistrar($theme), $factory)->toCollection())->toBe($collection);
    });

    it('registers and boots the enabled themes only', function (): void {
        $enabled = Mockery::mock(ThemeModule::class.'[register,boot]', ['on', '/p']);
        $enabled->setEnabled(true);
        $enabled->shouldReceive('register')->once();
        $enabled->shouldReceive('boot')->once();
        $repo = repoMake(repoRegistrar($enabled));

        $repo->register();
        $repo->boot();

        $disabled = Mockery::mock(ThemeModule::class.'[register,boot]', ['off', '/p']);
        $disabled->shouldReceive('register')->never();
        $disabled->shouldReceive('boot')->never();
        $other = repoMake(repoRegistrar($disabled));
        $other->register();
        $other->boot();
    });

    it('orders the enabled themes by priority in both directions', function (): void {
        $repo = repoMake(null);
        $themes = ['a' => repoTheme('a', true, 5), 'b' => repoTheme('b', true, 1), 'c' => repoTheme('c', true, 5), 'd' => repoTheme('d', true, 3)];
        (new ReflectionProperty($repo, 'cachedThemes'))->setValue($repo, $themes);
        (new ReflectionProperty($repo, 'cacheLoaded'))->setValue($repo, true);

        expect(array_keys($repo->getOrdered()))->toBe(['b', 'd', 'a', 'c'])
            ->and(array_keys($repo->getOrdered('desc')))->toBe(['a', 'c', 'd', 'b']);
    });
});
