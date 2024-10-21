<?php

declare(strict_types=1);

namespace Pollora\Permalink;

use Illuminate\Support\ServiceProvider;
use Pollora\Support\Facades\Action;

/**
 * Class RewriteServiceProvider
 *
 * A service provider for managing WP URL rewrites.
 */
class RewriteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // For remove the trailing slash.
        Action::add('permalink_structure_changed', fn ($oldPermalinkStructure, $new_permalink_structure) => $this->removeTrailingSlash($oldPermalinkStructure, $new_permalink_structure), 90);
    }

    /**
     * Remove trailing slash from permalink structure
     */
    protected function removeTrailingSlash(string|bool $oldPermalinkStructure, string|bool $permalinkStructure): void
    {
        if ($oldPermalinkStructure === '' || $oldPermalinkStructure === '0' || $oldPermalinkStructure === false) {
            return;
        }

        update_option('permalink_structure', rtrim($permalinkStructure, '/'));
    }
}
