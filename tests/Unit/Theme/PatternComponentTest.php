<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\Container\Domain\ServiceLocator;
use Pollora\Gutenberg\Application\Services\PatternRegistrationService;
use Pollora\Gutenberg\UI\PatternComponent;

require_once __DIR__.'/../helpers.php';

describe('PatternComponent', function () {
    it('resolves PatternRegistrationService from ServiceLocator', function () {
        $mockService = m::mock(PatternRegistrationService::class);
        $mockAction = m::mock('Pollora\\Hook\\Infrastructure\\Services\\Action');
        $mockLocator = m::mock(ServiceLocator::class);
        $mockLocator->shouldReceive('resolve')->with(PatternRegistrationService::class)->andReturn($mockService);
        $mockLocator->shouldReceive('resolve')->with('Pollora\\Hook\\Infrastructure\\Services\\Action')->andReturn($mockAction);
        $component = new PatternComponent($mockLocator);
        $ref = new ReflectionProperty($component, 'registrationService');
        $ref->setAccessible(true);
        expect($ref->getValue($component))->toBe($mockService);
    });
});
