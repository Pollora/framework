<?php

declare(strict_types=1);

namespace Pollen\Permalink;

use Illuminate\Support\ServiceProvider;
use Pollen\Support\Facades\Action;

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
        Action::add('permalink_structure_changed', fn ($old_permalink_structure, $new_permalink_structure) => $this->removeTrailingSlash($old_permalink_structure, $new_permalink_structure), 90);
    }

    /**
     * Remove trailing slash from permalink structure
     */
    protected function removeTrailingSlash(string|bool $old_permalink_structure, string|bool $permalink_structure): void
    {
        if (!$old_permalink_structure) {
            return;
        }
        
        update_option('permalink_structure', rtrim($permalink_structure, '/'));
    }
}
