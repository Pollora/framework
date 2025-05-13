<?php

declare(strict_types=1);

use Illuminate\Contracts\Foundation\Application;
use Mockery as m;
use Pollora\Theme\Domain\Models\ThemeInitializer;

require_once __DIR__.'/../helpers.php';

describe('ThemeInitializer', function () {
    it('injects Application from ServiceLocator (without constructor)', function () {
        $mockApp = m::mock(Application::class);
        $ref = new ReflectionClass(ThemeInitializer::class);
        $instance = $ref->newInstanceWithoutConstructor();
        $refProp = $ref->getProperty('app');
        $refProp->setAccessible(true);
        $refProp->setValue($instance, $mockApp);
        expect($refProp->getValue($instance))->toBe($mockApp);
    });
});
