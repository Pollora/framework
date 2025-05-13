<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Hook\Infrastructure\Services\Action;
use Pollora\Theme\Domain\Models\ImageSize;

require_once __DIR__.'/../helpers.php';

describe('ImageSize', function () {
    it('resolves Action from ServiceLocator', function () {
        $mockAction = m::mock(Action::class);
        $mockLocator = m::mock(ServiceLocator::class);
        $mockLocator->shouldReceive('resolve')->with(Action::class)->andReturn($mockAction);
        if (! function_exists('Pollora\\Theme\\config')) {
            eval('namespace Pollora\\Theme; function config($key) { return "/fake/theme/path"; }');
        }
        $component = new ImageSize($mockLocator);
        $ref = new ReflectionProperty($component, 'action');
        $ref->setAccessible(true);
        expect($ref->getValue($component))->toBe($mockAction);
    });
});
