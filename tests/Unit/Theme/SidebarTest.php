<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Models\Sidebar;
use Psr\Container\ContainerInterface;

require_once __DIR__.'/../helpers.php';

describe('Sidebar', function () {
    it('resolves Application from Laravel container', function () {
        $mockAction = m::mock(Action::class);
        $mockContainer = m::mock(ContainerInterface::class);
        $mockConfig = m::mock(ConfigRepositoryInterface::class);

        // Container should provide Action when requested
        $mockContainer->shouldReceive('get')
            ->with(Action::class)
            ->andReturn($mockAction);

        $sidebar = new Sidebar($mockContainer, $mockConfig);
        $ref = new ReflectionProperty($sidebar, 'app');
        $ref->setAccessible(true);
        expect($ref->getValue($sidebar))->toBe($mockContainer);
    });
});
