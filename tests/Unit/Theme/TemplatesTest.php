<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Mockery as m;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Theme\Domain\Models\Templates;

require_once __DIR__.'/../helpers.php';

describe('Templates', function () {
    it('resolves Application from ServiceLocator', function () {
        $mockApp = m::mock(Application::class);
        $mockAction = m::mock('Pollora\\Hook\\Infrastructure\\Services\\Action');
        $mockLocator = m::mock(ServiceLocator::class);
        $mockLocator->shouldReceive('resolve')->with(Application::class)->andReturn($mockApp);
        $mockLocator->shouldReceive('resolve')->with('Pollora\\Hook\\Infrastructure\\Services\\Action')->andReturn($mockAction);
        if (! function_exists('Pollora\\Theme\\config')) {
            eval('namespace Pollora\\Theme; function config($key) { return "/fake/theme/path"; }');
        }
        $component = new Templates($mockLocator);
        $ref = new ReflectionProperty($component, 'app');
        $ref->setAccessible(true);
        expect($ref->getValue($component))->toBe($mockApp);
    });
});
