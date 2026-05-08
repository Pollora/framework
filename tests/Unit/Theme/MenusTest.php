<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Domain\Contracts\Action;
use Pollora\Hook\Domain\Contracts\Filter;
use Pollora\Theme\Domain\Models\Menus;
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
});
