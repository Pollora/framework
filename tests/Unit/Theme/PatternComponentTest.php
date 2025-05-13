<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Container\Domain\ServiceLocator;

require_once __DIR__.'/../helpers.php';

describe('PatternComponent', function () {
    it('resolves PatternServiceInterface from ServiceLocator', function () {
        $mockService = m::mock(PatternServiceInterface::class);
        $mockAction = m::mock('Pollora\\Hook\\Infrastructure\\Services\\Action');
        $mockLocator = m::mock(ServiceLocator::class);
        $mockLocator->shouldReceive('resolve')->with(PatternServiceInterface::class)->andReturn($mockService);
        $mockLocator->shouldReceive('resolve')->with('Pollora\\Hook\\Infrastructure\\Services\\Action')->andReturn($mockAction);
        $component = new PatternComponent($mockLocator);
        $ref = new ReflectionProperty($component, 'registrationService');
        $ref->setAccessible(true);
        expect($ref->getValue($component))->toBe($mockService);
    });
});
