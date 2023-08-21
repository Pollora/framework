<?php

declare(strict_types=1);

namespace Pollen\Support\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for {@link \Pollen\Hashing\WordPressHasher}.
 *
 * @see \Pollen\Hashing\WordPressHasher
 *
 * @mixin \Pollen\Hashing\WordPressHasher
 *
 * @author Jordan Doyle <jordan@doyle.wf>
 */
class WPHash extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'wp.hash';
    }
}
