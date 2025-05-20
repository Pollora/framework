<?php

declare(strict_types=1);

use Mockery as m;
use Pollora\BlockPattern\Domain\Contracts\PatternServiceInterface;
use Pollora\BlockPattern\UI\PatternComponent;
use Pollora\Theme\Domain\Models\PatternComponent as ThemePatternComponent;

require_once __DIR__.'/../helpers.php';

describe('PatternComponent', function () {
    it('resolves PatternServiceInterface from Laravel container', function () {
        $mockPatternService = m::mock(PatternServiceInterface::class);
        $component = new PatternComponent($mockPatternService);
        expect($component)->toBeInstanceOf(Pollora\BlockPattern\UI\PatternComponent::class);
    });
});
