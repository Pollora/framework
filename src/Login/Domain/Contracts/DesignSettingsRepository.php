<?php

declare(strict_types=1);

namespace Pollora\Login\Domain\Contracts;

/**
 * The theme's resolved design settings, as flat slug-to-value maps.
 *
 * theme.json is the design truth of a Pollora theme — apiary declares 304
 * colours there, theme-default ten — but WordPress does not emit any of it on
 * wp-login.php. Measured: zero occurrences of `wp--preset--color` in the HTML
 * of a login screen. The settings themselves are readable there, though;
 * `wp_get_global_settings()` answers in full, `after_setup_theme` and `init`
 * having both fired by then.
 *
 * So the login screen can style itself from the theme's own tokens, provided
 * something reads them and writes them out. That something needs the values,
 * not WordPress's nested preset structure, which is what this contract hands
 * over — and what makes the resolution testable without WordPress.
 */
interface DesignSettingsRepository
{
    /**
     * Colour presets declared by the theme, keyed by slug.
     *
     * @return array<string, string> e.g. ['primary' => '#ff5334']
     */
    public function colors(): array;

    /**
     * Border radius presets declared by the theme, keyed by slug.
     *
     * @return array<string, string> e.g. ['lg' => '.5rem']
     */
    public function radii(): array;

    /**
     * Font families declared by the theme, keyed by slug.
     *
     * @return array<string, string> e.g. ['sans' => 'Inter var, system-ui, sans-serif']
     */
    public function fontFamilies(): array;
}
