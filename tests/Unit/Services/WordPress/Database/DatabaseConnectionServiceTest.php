<?php

declare(strict_types=1);

use Laravel\Prompts\Key;
use Laravel\Prompts\Output\BufferedConsoleOutput;
use Laravel\Prompts\Prompt;
use Pollora\Services\WordPress\Database\DatabaseConnectionService;
use Pollora\Services\WordPress\Installation\DatabaseConnectionException;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * A configuration pointing at a port nobody listens on, so the connection
 * is refused at once without touching the network.
 */
function covUnreachableDatabase(): DatabaseConfig
{
    return new DatabaseConfig('127.0.0.1', 1, 'nope', 'nobody', '', 'http://localhost');
}

afterEach(function (): void {
    Prompt::setOutput(new ConsoleOutput);
});

describe('DatabaseConnectionService', function (): void {
    it('gives up with a clear exception when the connection fails and the user declines to retry', function (): void {
        Prompt::fake(['n', Key::ENTER]);

        try {
            (new DatabaseConnectionService)->ensureConnection(covUnreachableDatabase());
            $thrown = null;
        } catch (DatabaseConnectionException $databaseConnectionException) {
            $thrown = $databaseConnectionException;
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
        $output = new BufferedConsoleOutput;
        Prompt::setOutput($output);

        expect(fn (): DatabaseConfig => (new DatabaseConnectionService)->ensureConnection(covUnreachableDatabase()))
            ->toThrow(DatabaseConnectionException::class);

        $rendered = $output->content();
        expect($rendered)->toContain('Database host?')
            ->toContain('Site URL?')
            ->and(substr_count($rendered, 'Database connection failed:'))->toBe(2);
    });
});
