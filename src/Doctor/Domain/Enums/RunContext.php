<?php

declare(strict_types=1);

namespace Pollora\Doctor\Domain\Enums;

/**
 * Where a check runs.
 *
 * Some failures only exist in a web request — blocks registered under WP-CLI but
 * not over HTTP, once — so those checks run in WordPress's Site Health, never in
 * the console, where they would pass a broken site.
 */
enum RunContext: string
{
    case Console = 'console';
    case Http = 'http';
}
