<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Asset\Application\Services\AssetManager;
use Pollora\Asset\Infrastructure\Laravel\LaravelAssetFileResolver;
use Psr\Log\LoggerInterface;

describe('LaravelAssetFileResolver', function (): void {
    it('gives an empty string and a log line when the container cannot be resolved', function (): void {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('error')->once()->with('Error in AssetFile::__toString', ['error' => 'no manager', 'path' => 'images/logo.svg']);

        $app = new Container;
        $app->bind(AssetManager::class, fn () => throw new RuntimeException('no manager'));
        $app->instance(LoggerInterface::class, $logger);

        expect((new LaravelAssetFileResolver($app))('images/logo.svg', 'theme'))->toBe('');
    });

    it('gives an empty string, without a log line, for a container the manager does not know', function (): void {
        $manager = Mockery::mock(AssetManager::class);
        $manager->shouldReceive('getContainer')->with('ghost')->andReturn(null);

        $app = new Container;
        $app->instance(AssetManager::class, $manager);

        expect((new LaravelAssetFileResolver($app))('images/logo.svg', 'ghost'))->toBe('');
    });
});
