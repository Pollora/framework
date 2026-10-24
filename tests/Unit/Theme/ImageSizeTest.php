<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contract\Action;
use Pollora\Theme\Domain\Contracts\ThemeFeatureRegistrarInterface;
use Pollora\Theme\Domain\Models\ImageSize;
use Pollora\Theme\Domain\Support\ThemeConfig;
use Psr\Container\ContainerInterface;

require_once __DIR__.'/../helpers.php';

describe('ImageSize', function (): void {
    it('resolves Action from Laravel container', function (): void {
        $mockAction = m::mock(Action::class);
        $mockContainer = m::mock(ContainerInterface::class);
        $mockConfig = m::mock(ConfigRepositoryInterface::class);

        // Container should provide Action when requested
        $mockContainer->shouldReceive('get')
            ->with(Action::class)
            ->andReturn($mockAction);

        $imageSize = new ImageSize($mockContainer, $mockConfig);
        expect($imageSize)->toBeInstanceOf(ImageSize::class);
    });

    it('registers every configured image size through the feature registrar', function (): void {
        $config = m::mock(ConfigRepositoryInterface::class);
        $config->shouldReceive('get')->with('theme.images', [])->andReturn([
            'card' => [640, 480, true],
            'banner' => [1600],
        ]);
        ThemeConfig::setRepository($config);

        $features = m::mock(ThemeFeatureRegistrarInterface::class);
        $features->shouldReceive('addImageSize')->once()->with('card', 640, 480, true);
        // Missing dimensions and crop fall back to WordPress's own defaults
        $features->shouldReceive('addImageSize')->once()->with('banner', 1600, 0, false);

        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with(Action::class)->andReturn(m::mock(Action::class));
        $container->shouldReceive('get')->with(ThemeFeatureRegistrarInterface::class)->andReturn($features);

        (new ImageSize($container, $config))->addImageSize();

        ThemeConfig::reset();
    });
});
