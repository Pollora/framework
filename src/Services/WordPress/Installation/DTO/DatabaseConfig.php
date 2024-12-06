<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation\DTO;

use Pollora\Services\WordPress\Installation\LocalEnvironmentDetector;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class DatabaseConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $name,
        public readonly string $username,
        public readonly string $password,
        public readonly string $siteUrl,
    ) {}

    public static function fromEnvironment(): self
    {
        $config = LocalEnvironmentDetector::getConfig();

        return new self(
            host: $config['dbHost'],
            port: (int) $config['dbPort'],
            name: $config['dbName'],
            username: $config['dbUser'],
            password: $config['dbPassword'],
            siteUrl: $config['siteUrl'],
        );
    }

    public static function fromPrompts(): self
    {
        $defaults = self::fromEnvironment();

        return new self(
            host: text(
                label: 'Database host?',
                default: $defaults->host,
                required: 'Database host is required'
            ),
            port: (int) text(
                label: 'Database port?',
                default: (string) $defaults->port,
                validate: fn (string $value): ?string => is_numeric($value) ? null : 'Port must be a number'
            ),
            name: text(
                label: 'Database name?',
                default: $defaults->name,
                required: 'Database name is required'
            ),
            username: text(
                label: 'Database username?',
                default: $defaults->username,
                required: 'Database username is required'
            ),
            password: password(
                label: 'Database password?',
                required: 'Database password is required'
            ),
            siteUrl: text(
                label: 'Site URL?',
                default: $defaults->siteUrl,
                validate: function (string $value): ?string {
                    if (! filter_var($value, FILTER_VALIDATE_URL)) {
                        return 'Please enter a valid URL (e.g., https://pollora.ddev.site)';
                    }
                    // Ensure URL doesn't end with a slash
                    if (str_ends_with($value, '/')) {
                        return 'URL should not end with a slash';
                    }
                    // Check protocol
                    if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
                        return 'URL must start with http:// or https://';
                    }

                    return null;
                },
                required: 'Site URL is required'
            ),
        );
    }
}
