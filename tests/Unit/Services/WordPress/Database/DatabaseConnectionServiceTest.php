<?php

declare(strict_types=1);

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Pollora\Services\WordPress\Database\DatabaseConnectionService;
use Pollora\Services\WordPress\Installation\DatabaseConnectionException;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

/**
 * A configuration pointing at a port nobody listens on, so the connection
 * is refused at once without touching the network.
 */
function unreachableDatabase(): DatabaseConfig
{
    return new DatabaseConfig('127.0.0.1', 1, 'nope', 'nobody', '', 'http://localhost');
}

describe('DatabaseConnectionService', function (): void {
    it('gives up with a clear exception when the connection fails and the user declines to retry', function (): void {
        Prompt::fake(['n', Key::ENTER]);

        try {
            (new DatabaseConnectionService)->ensureConnection(unreachableDatabase());
            $thrown = null;
        } catch (DatabaseConnectionException $e) {
            $thrown = $e;
        }

        expect($thrown)->toBeInstanceOf(DatabaseConnectionException::class)
            ->and($thrown->getMessage())->toBe('Could not establish database connection. Please check your configuration.')
            ->and($thrown->getPrevious())->not->toBeNull();
    });

    it('asks for new credentials when the user agrees to retry, then gives up if they fail too', function (): void {
        $answers = [
            'y', Key::ENTER,
            // host, port, name, username, password, site URL: defaults kept, the name typed
            Key::ENTER, Key::ENTER, ...str_split('retry'), Key::ENTER, Key::ENTER, Key::ENTER, Key::ENTER,
            'n', Key::ENTER,
        ];
        Prompt::fake($answers);

        expect(fn (): DatabaseConfig => (new DatabaseConnectionService)->ensureConnection(unreachableDatabase()))
            ->toThrow(DatabaseConnectionException::class);
    });
});
