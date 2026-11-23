<?php

declare(strict_types=1);

use Pollora\Option\Domain\Contracts\OptionManagerInterface;
use Pollora\Support\Facades\Option;

describe('OptionIntegration', function (): void {
    it('facade class exists', function (): void {
        expect(class_exists(Option::class))->toBeTrue();
    });

    it('facade has correct accessor', function (): void {
        $reflection = new ReflectionClass(Option::class);
        $method = $reflection->getMethod('getFacadeAccessor');

        $accessor = $method->invoke(null);

        expect($accessor)->toBe(OptionManagerInterface::class);
    });

    it('facade has forget alias through the option manager', function (): void {
        expect(method_exists(OptionManagerInterface::class, 'forget'))->toBeTrue();
    });
});
