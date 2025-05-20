<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Config\Domain\Contracts\ConfigRepositoryInterface;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Infrastructure\Services\Support;
use Psr\Container\ContainerInterface;

require_once __DIR__.'/../helpers.php';

describe('Support', function () {
    it('resolves Action from container', function () {
        $mockAction = m::mock(Action::class);
        $mockContainer = m::mock(ContainerInterface::class);
        $mockConfig = m::mock(ConfigRepositoryInterface::class);
        
        // Set up container mock to return our dependencies
        $mockContainer->shouldReceive('get')
            ->with(Action::class)
            ->andReturn($mockAction);
            
        if (! function_exists('Pollora\\Theme\\config')) {
            eval('namespace Pollora\\Theme; function config($key) { return "/fake/theme/path"; }');
        }
        
        $component = new Support($mockContainer, $mockConfig);
        $ref = new ReflectionProperty($component, 'app');
        $ref->setAccessible(true);
        expect($ref->getValue($component))->toBe($mockContainer);
    });
});
