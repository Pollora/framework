<?php

declare(strict_types=1);

use Pollora\Container\Infrastructure\ContainerServiceLocator;

describe('ContainerServiceLocator', function (): void {
    it('resolves nothing without a container', function (): void {
        expect((new ContainerServiceLocator(null))->resolve('Foo'))->toBeNull();
    });

    it('reads a service from an array container', function (): void {
        $service = new stdClass;

        expect((new ContainerServiceLocator(['Foo' => $service]))->resolve('Foo'))->toBe($service);
    });

    it('does not resolve a service missing from an array container', function (): void {
        expect((new ContainerServiceLocator(['Foo' => 1]))->resolve('Bar'))->toBeNull();
    });

    it('asks an object container through its get method', function (): void {
        $container = new class
        {
            public function get(string $id): string
            {
                return 'resolved '.$id;
            }
        };

        expect((new ContainerServiceLocator($container))->resolve('Foo'))->toBe('resolved Foo');
    });

    it('returns null when the container throws while resolving', function (): void {
        $container = new class
        {
            public function get(string $id): never
            {
                throw new LogicException('not found');
            }
        };

        expect((new ContainerServiceLocator($container))->resolve('Foo'))->toBeNull();
    });

    it('returns null for an object container without a get method', function (): void {
        expect((new ContainerServiceLocator(new stdClass))->resolve('Foo'))->toBeNull();
    });
});
