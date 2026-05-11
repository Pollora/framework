<?php

declare(strict_types=1);

use Pollora\Foundation\Console\Commands\Concerns\ResolvesLocation;

describe('ResolvesLocation', function (): void {
    describe('getResolvedFilePath', function (): void {
        it('builds file path without subpath', function (): void {
            $trait = new ResolvesLocationTestHelper;

            $location = ['source_path' => '/app/src'];
            $result = $trait->getResolvedFilePath($location, 'MyClass');

            expect($result)->toBe('/app/src/MyClass.php');
        });

        it('builds file path with subpath', function (): void {
            $trait = new ResolvesLocationTestHelper;

            $location = ['source_path' => '/app/src'];
            $result = $trait->getResolvedFilePath($location, 'MyModel', 'Models');

            expect($result)->toBe('/app/src/Models/MyModel.php');
        });
    });

    describe('getResolvedNamespace', function (): void {
        it('builds namespace without subpath', function (): void {
            $trait = new ResolvesLocationTestHelper;

            $location = ['source_namespace' => 'App\\'];
            $result = $trait->getResolvedNamespace($location);

            expect($result)->toBe('App\\');
        });

        it('builds namespace with subpath', function (): void {
            $trait = new ResolvesLocationTestHelper;

            $location = ['source_namespace' => 'App\\'];
            $result = $trait->getResolvedNamespace($location, 'Models');

            expect($result)->toBe('App\\Models');
        });

        it('converts slashes to backslashes in subpath', function (): void {
            $trait = new ResolvesLocationTestHelper;

            $location = ['source_namespace' => 'App\\'];
            $result = $trait->getResolvedNamespace($location, 'Http/Controllers');

            expect($result)->toBe('App\\Http\\Controllers');
        });
    });
});

class ResolvesLocationTestHelper
{
    use ResolvesLocation {
        getResolvedFilePath as public;
        getResolvedNamespace as public;
    }

    public function option($key = null)
    {
        return null;
    }
}
