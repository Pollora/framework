<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Models\ImageSize;
use Psr\Container\ContainerInterface;

require_once __DIR__.'/../helpers.php';

describe('ImageSize', function () {
    it('resolves Action from Laravel container', function () {
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
});
