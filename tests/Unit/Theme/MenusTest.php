<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Mockery as m;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Theme\Domain\Models\Menus;

require_once __DIR__.'/../helpers.php';

describe('Menus', function () {
    it('resolves Application from ServiceLocator', function () {
        $mockApp = m::mock(Application::class);
        $mockLocator = m::mock(ServiceLocator::class);
        $mockLocator->shouldReceive('resolve')->with(Application::class)->andReturn($mockApp);
        $mockAction = m::mock('Pollora\\Hook\\Infrastructure\\Services\\Action');
        $mockFilter = m::mock('Pollora\\Hook\\Infrastructure\\Services\\Filter');
        $mockLocator->shouldReceive('resolve')->with('Pollora\\Hook\\Infrastructure\\Services\\Action')->andReturn($mockAction);
        $mockLocator->shouldReceive('resolve')->with('Pollora\\Hook\\Infrastructure\\Services\\Filter')->andReturn($mockFilter);
        if (! function_exists('Pollora\\Theme\\config')) {
            eval('namespace Pollora\\Theme; function config($key) { return "/fake/theme/path"; }');
        }
        $component = new Menus($mockLocator);
        $ref = new ReflectionProperty($component, 'app');
        $ref->setAccessible(true);
        expect($ref->getValue($component))->toBe($mockApp);
    });
});
