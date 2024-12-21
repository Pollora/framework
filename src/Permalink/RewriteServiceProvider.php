<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\Action;

/**
 * Class RewriteServiceProvider
 *
 * A service provider for managing WP URL rewrites.
 * Handles permalink structure modifications and trailing slash removal.
 */
class RewriteServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     * Registers actions for permalink structure management.
     *
     * @return void
     */
    public function boot(): void
    {
        // For remove the trailing slash.
        Action::add('permalink_structure_changed', fn ($oldPermalinkStructure, $new_permalink_structure) => $this->removeTrailingSlash($oldPermalinkStructure, $new_permalink_structure), 90);
    }

    /**
     * Remove trailing slash from permalink structure.
     * Updates the WordPress permalink structure option by removing any trailing slashes.
     *
     * @param string|bool $oldPermalinkStructure The previous permalink structure
     * @param string|bool $permalinkStructure The new permalink structure to be sanitized
     * @return void
     */
    protected function removeTrailingSlash(string|bool $oldPermalinkStructure, string|bool $permalinkStructure): void
    {
        if ($oldPermalinkStructure === '' || $oldPermalinkStructure === '0' || $oldPermalinkStructure === false) {
            return;
        }

        update_option('permalink_structure', rtrim($permalinkStructure, '/'));
    }
}
