<?php

declare(strict_types=1);

use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;
use Pollora\Services\WordPress\Installation\DTO\DatabaseConfig;

/**
 * Answers the six prompts of the database setup: host, port, name, username,
 * password and site URL. The defaults are kept, except for the name, which has none.
 *
 * @param  list<string>  $password  The keys typed at the password prompt
 * @return list<string>
 */
function databasePromptKeys(array $password): array
{
    return [
        Key::ENTER,
        Key::ENTER,
        ...str_split('pollora'),
        Key::ENTER,
        Key::ENTER,
        ...$password,
        Key::ENTER,
        Key::ENTER,
    ];
}

it('accepts an empty database password', function (): void {
    Prompt::fake(databasePromptKeys([]));

    $config = DatabaseConfig::fromPrompts();

    expect($config->password)->toBe('')
        ->and($config->name)->toBe('pollora')
        ->and($config->username)->toBe('root');
});

it('still takes a database password that is typed', function (): void {
    Prompt::fake(databasePromptKeys(str_split('s3cret')));

    expect(DatabaseConfig::fromPrompts()->password)->toBe('s3cret');
});
