<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Theme\Domain\Contracts\ThemeFeatureRegistrarInterface;
use Pollora\Theme\Domain\Models\Menus;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Psr\Container\ContainerInterface;

require_once __DIR__.'/../helpers.php';

describe('Menus', function (): void {
    it('resolves Action and Filter from container', function (): void {
        $mockAction = m::mock(Action::class);
        $mockFilter = m::mock(Filter::class);
        $mockContainer = m::mock(ContainerInterface::class);
        $mockConfig = m::mock(ConfigRepositoryInterface::class);

        // Set up container mock to return our dependencies
        $mockContainer->shouldReceive('get')
            ->with(Action::class)
            ->andReturn($mockAction);

        $mockContainer->shouldReceive('get')
            ->with(Filter::class)
            ->andReturn($mockFilter);

        if (! function_exists('Pollora\\Theme\\config')) {
            eval('namespace Pollora\\Theme; function config($key) { return "/fake/theme/path"; }');
        }

        $component = new Menus($mockContainer, $mockConfig);
        $ref = new ReflectionProperty($component, 'app');

        expect($ref->getValue($component))->toBe($mockContainer);
    });

    it('registers the configured menu locations through the feature registrar', function (): void {
        $config = m::mock(ConfigRepositoryInterface::class);
        $config->shouldReceive('get')->with('theme.menus', [])->andReturn([
            'primary' => 'Primary menu',
            'footer' => 'Footer menu',
        ]);
        ThemeConfig::setRepository($config);

        $features = m::mock(ThemeFeatureRegistrarInterface::class);
        $features->shouldReceive('registerNavMenus')->once()->with([
            'primary' => 'Primary menu',
            'footer' => 'Footer menu',
        ]);

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(Action::class)->andReturn(m::mock(Action::class));
        $container->shouldReceive('get')->with(Filter::class)->andReturn(m::mock(Filter::class));
        $container->shouldReceive('get')->with(ThemeFeatureRegistrarInterface::class)->andReturn($features);

        (new Menus($container, $config))->registerMenus();

        ThemeConfig::reset();
    });
});
