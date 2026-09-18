<?php

declare(strict_types=1);

namespace Pollora\Theme\Application\Services;

use Illuminate\Contracts\Container\Container;
use Pollora\Theme\Domain\Contracts\ThemeModuleInterface;
use Pollora\Theme\Domain\Contracts\ThemeRegistrarInterface;
use Throwable;

/**
 * Tells whether the site has a usable theme.
 *
 * A Pollora site installed through the WordPress web installer ends up with the
 * `stylesheet` option pointing at WP_DEFAULT_THEME while `themes/` is still
 * empty, because only `pollora:install` scaffolds a theme. Rendering then fails
 * on the first missing view, so the condition is worth naming explicitly.
 */
final readonly class ThemeAvailability
{
    public function __construct(private Container $app) {}

    /**
     * Determine whether the site is missing a theme it can render with.
     *
     * Deliberately conservative: anything that looks like a working theme —
     * a registered one, or a directory where WordPress expects it — counts.
     */
    public function isMissing(): bool
    {
        if ($this->hasRegisteredTheme()) {
            return false;
        }

        if (function_exists('get_stylesheet_directory') && is_dir(get_stylesheet_directory())) {
            return false;
        }

        return true;
    }

    private function hasRegisteredTheme(): bool
    {
        try {
            $registrar = $this->app->make(ThemeRegistrarInterface::class);
        } catch (Throwable) {
            return false;
        }

        return $registrar->getActiveTheme() instanceof ThemeModuleInterface;
    }
}
