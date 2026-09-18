<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Http;

use Pollora\Theme\Application\Services\ThemeAvailability;

/**
 * Warns in wp-admin when the site has no theme to render with.
 *
 * Not dismissable on purpose: the front end is unusable until a theme exists,
 * so this is a setup step rather than a suggestion.
 */
final readonly class MissingThemeNotice
{
    public function __construct(private ThemeAvailability $availability) {}

    public function render(): void
    {
        if (! $this->availability->isMissing()) {
            return;
        }

        $prefix = getenv('IS_DDEV_PROJECT') === 'true' ? 'ddev exec ' : '';

        $intro = __('No theme is installed, so the front end of this site cannot be rendered. Generate one from the project root:', 'pollora');
        $default = __('Default theme', 'pollora');
        $ecommerce = __('E-commerce theme (WooCommerce)', 'pollora');

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong></p><p>%s</p><p>%s<br><code>%s</code></p><p>%s<br><code>%s</code></p></div>',
            esc_html__('Pollora: no theme installed', 'pollora'),
            esc_html($intro),
            esc_html($default),
            esc_html($prefix.'php artisan pollora:make:theme my-theme --repository=pollora/theme-default'),
            esc_html($ecommerce),
            esc_html($prefix.'php artisan pollora:make:theme my-shop --repository=pollora/theme-apiary')
        );
    }
}
