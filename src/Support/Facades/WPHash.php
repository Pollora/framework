<?php

declare(strict_types=1);

namespace Pollora\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link \Pollora\Hashing\WordPressHasher}.
 *
 * @see \Pollora\Hashing\WordPressHasher
 *
 * @mixin \Pollora\Hashing\WordPressHasher
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
