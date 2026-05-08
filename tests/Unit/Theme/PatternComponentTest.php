<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Hook\Domain\Contracts\Action;
use Psr\Container\ContainerInterface;

require_once __DIR__.'/../helpers.php';

describe('PatternComponent', function (): void {
    it('resolves PatternServiceInterface from Laravel container', function (): void {
        $mockPatternService = m::mock(PatternServiceInterface::class);
        $mockAction = m::mock(Action::class);
        $mockContainer = m::mock(ContainerInterface::class);

        // Set up container mock to return our dependencies
        $mockContainer->shouldReceive('get')
            ->with(PatternServiceInterface::class)
            ->andReturn($mockPatternService);

        $mockContainer->shouldReceive('get')
            ->with(Action::class)
            ->andReturn($mockAction);

        $component = new PatternComponent($mockContainer);
        expect($component)->toBeInstanceOf(PatternComponent::class);
    });
});
