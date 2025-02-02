<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for WordPress Password Hashing functionality.
 *
 * Provides a secure interface to WordPress password hashing and verification
 * with proper type hints and modern PHP syntax.
 *
 * @mixin \Pollora\Hashing\WordPressHasher
 *
 * @see \Pollora\Hashing\WordPressHasher
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WPHash extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'wp.hash';
    }
}
