<?php

declare(strict_types=1);

namespace Pollora\Services\WordPress\Installation\DTO;

use Pollora\Services\WordPress\Installation\LanguageService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Data Transfer Object for WordPress installation configuration.
 *
 * This class encapsulates WordPress site configuration parameters and provides
 * factory methods for creating configurations from interactive prompts.
 */
class InstallationConfig
{
    /**
     * Create a new installation configuration instance.
     *
     * @param  string  $title  Site title
     * @param  string  $description  Site description
     * @param  string  $adminUser  Admin username
     * @param  string  $adminEmail  Admin email address
     * @param  string  $adminPassword  Admin password
     * @param  string  $locale  Site language locale
     * @param  bool  $isPublic  Whether site should be indexed by search engines
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $adminUser,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        public readonly string $locale,
        public readonly bool $isPublic,
    ) {}

    /**
     * Create configuration from interactive prompts.
     *
     * Prompts user for WordPress installation settings with validation.
     * Includes:
     * - Site information (title, description)
     * - Admin account details
     * - Language preferences
     * - Search engine visibility
     *
     * @throws \RuntimeException If user input validation fails
     */
    public static function fromPrompts(): self
    {
        return new self(
            title: text(
                label: 'Site title?',
                required: 'Site title is required'
            ),
            description: text(
                label: 'Site description?'
            ),
            adminUser: text(
                label: 'Admin username?',
                required: 'Admin username is required'
            ),
            adminEmail: text(
                label: 'Admin email?',
                required: 'Admin email is required',
                validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Invalid email address'
            ),
            adminPassword: password(
                label: 'Admin password?',
                required: 'Admin password is required',
                validate: fn (string $value): ?string => strlen($value) < 8 ? 'Password must be at least 8 characters' : null
            ),
            locale: (new LanguageService)->promptForLanguage(),
            isPublic: confirm(
                label: 'Allow search engine indexing?',
                default: true
            ),
        );
    }
}
