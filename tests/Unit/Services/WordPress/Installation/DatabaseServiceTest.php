<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Pollora\Services\WordPress\Database\DatabaseConnectionService;
use Pollora\Services\WordPress\Database\EnvironmentFileHandler;
use Pollora\Services\WordPress\Installation\DatabaseService;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;
use Psr\Log\NullLogger;

beforeEach(function (): void {
    // configure() reports through the info() helper, which logs.
    Container::getInstance()->instance('log', new NullLogger);
});

afterEach(function (): void {
    Container::getInstance()->forgetInstance('log');
});

describe('DatabaseService::configure', function (): void {
    it('writes the validated connection to the environment file', function (): void {
        $given = new DatabaseConfig('db', 3306, 'shop', 'shopuser', 's3cret', 'https://shop.test');
        $validated = new DatabaseConfig('db', 3307, 'shop', 'shopuser', 'changed', 'https://shop.test');

        $connection = Mockery::mock(DatabaseConnectionService::class);
        $connection->shouldReceive('ensureConnection')->with($given)->once()->andReturn($validated);

        $handler = Mockery::mock(EnvironmentFileHandler::class);
        $handler->shouldReceive('updateEnvFile')->once()->with([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => 'db',
            'DB_PORT' => '3307',
            'DB_DATABASE' => 'shop',
            'DB_USERNAME' => 'shopuser',
            'DB_PASSWORD' => 'changed',
            'APP_URL' => 'https://shop.test',
        ]);

        (new DatabaseService($connection, $handler))->configure($given);
    });

    it('leaves the environment file alone when no connection can be established', function (): void {
        $connection = Mockery::mock(DatabaseConnectionService::class);
        $connection->shouldReceive('ensureConnection')->andThrow(new RuntimeException('no connection'));

        $handler = Mockery::mock(EnvironmentFileHandler::class);
        $handler->shouldNotReceive('updateEnvFile');

        $service = new DatabaseService($connection, $handler);

        expect(fn () => $service->configure(new DatabaseConfig('db', 3306, 'shop', 'u', 'p', 'https://shop.test')))
            ->toThrow(RuntimeException::class, 'no connection');
    });
});
