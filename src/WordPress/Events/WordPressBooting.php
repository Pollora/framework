<?php

declare(strict_types=1);

namespace Pollora\WordPress\Events;

/**
 * WordPress is about to load: the database constants are defined, and
 * `wp-settings.php` comes next.
 *
 * The last moment to put something in place before WordPress builds its
 * globals. WordPress keeps a `$wpdb` that already exists, for instance, so a
 * profiler can swap in its own database class here — and nowhere later, since
 * by the time any WordPress hook fires, the queries have started.
 *
 * Listen to it from a service provider's `register()`: WordPress loads while
 * providers boot, so a listener added in `boot()` may come too late.
 */
final readonly class WordPressBooting
{
    /**
     * @param  bool  $lightweight  Whether this request loads WordPress without its plugins (theme API routes)
     */
    public function __construct(
        public bool $lightweight,
    ) {}
}
