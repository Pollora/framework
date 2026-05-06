<?php

declare(strict_types=1);

use Illuminate\Contracts\Container\Container;
use Pollora\Hook\Infrastructure\Services\ContainerCallbackResolver;

describe('ContainerCallbackResolver', function (): void {
    it('delegates to container make()', function (): void {
        $expected = new \stdClass;

        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')
            ->once()
            ->with('App\\MyService')
            ->andReturn($expected);

        $resolver = new ContainerCallbackResolver($container);
        $result = $resolver->resolve('App\\MyService');

        expect($result)->toBe($expected);
    });

    it('propagates container exceptions', function (): void {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('make')
            ->andThrow(new \RuntimeException('Cannot resolve'));

        $resolver = new ContainerCallbackResolver($container);

        expect(fn () => $resolver->resolve('App\\Missing'))
            ->toThrow(\RuntimeException::class, 'Cannot resolve');
    });
});
