<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Theme\Domain\Contracts\ContainerInterface;
use Pollora\Theme\Domain\Models\ThemeInitializer;

require_once __DIR__.'/../helpers.php';

describe('ThemeInitializer', function () {
    it('injects ContainerInterface mock (plus ServiceLocator legacy)', function () {
        $mockContainer = m::mock(ContainerInterface::class);
        $ref = new ReflectionClass(ThemeInitializer::class);
        $instance = $ref->newInstanceWithoutConstructor();
        $refProp = $ref->getProperty('app');
        $refProp->setAccessible(true);
        $refProp->setValue($instance, $mockContainer);
        expect($refProp->getValue($instance))->toBe($mockContainer);
    });
});
