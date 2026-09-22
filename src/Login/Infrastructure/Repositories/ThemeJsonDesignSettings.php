<?php

declare(strict_types=1);

namespace Pollora\Login\Infrastructure\Repositories;

use Pollora\Login\Domain\Contracts\DesignSettingsRepository;

/**
 * Reads the theme's design tokens out of WordPress's resolved theme.json.
 *
 * `wp_get_global_settings()` answers on the login screen — measured on a site
 * running apiary: 304 colours, three font families, eight radius sizes, with
 * `after_setup_theme` and `init` both already fired. What WordPress does *not*
 * do there is emit any of it as CSS, which is the whole reason this module
 * exists.
 *
 * Three origins are merged, weakest first: WordPress's defaults, then the
 * theme's own theme.json, then whatever the site editor saved. A colour a
 * site owner changed in Global Styles therefore reaches the login screen too,
 * which is the behaviour anyone who just changed it would expect.
 */
final class ThemeJsonDesignSettings implements DesignSettingsRepository
{
    /**
     * Resolved settings, read once per request.
     *
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    /**
     * {@inheritDoc}
     */
    public function colors(): array
    {
        return $this->flatten(['color', 'palette'], 'color');
    }

    /**
     * {@inheritDoc}
     */
    public function radii(): array
    {
        return $this->flatten(['border', 'radiusSizes'], 'size');
    }

    /**
     * {@inheritDoc}
     */
    public function fontFamilies(): array
    {
        return $this->flatten(['typography', 'fontFamilies'], 'fontFamily');
    }

    /**
     * Turn one preset group into a slug-to-value map.
     *
     * @param  list<string>  $path  Where the group lives in the settings tree
     * @param  string  $valueKey  Which key of a preset holds its value
     * @return array<string, string>
     */
    private function flatten(array $path, string $valueKey): array
    {
        $group = $this->settings();

        foreach ($path as $segment) {
            if (! is_array($group) || ! isset($group[$segment])) {
                return [];
            }

            $group = $group[$segment];
        }

        if (! is_array($group)) {
            return [];
        }

        $presets = [];

        foreach (['default', 'theme', 'custom'] as $origin) {
            if (! isset($group[$origin]) || ! is_array($group[$origin])) {
                continue;
            }

            foreach ($group[$origin] as $preset) {
                if (! is_array($preset)) {
                    continue;
                }

                $slug = $preset['slug'] ?? null;
                $value = $preset[$valueKey] ?? null;

                if (! is_string($slug) || $slug === '' || ! is_string($value)) {
                    continue;
                }

                $value = $this->dereference($value);

                if ($value !== null) {
                    $presets[$slug] = $value;
                }
            }
        }

        return $presets;
    }

    /**
     * Resolve a preset that points at a CSS variable, or give up on it.
     *
     * The login screen carries no stylesheet but the one this module prints,
     * so a preset whose value is `var(--something)` names a variable that does
     * not exist there. Measured on apiary, whose Tailwind-built theme.json
     * declares `primary` as `var(--wp--preset--color--primary,#1f2937)` — a
     * reference to itself, which CSS discards as a cycle even on the front
     * end. Its declared fallback is the only colour actually present in the
     * data, so that is what gets used.
     *
     * A value that merely mentions a variable somewhere inside it cannot be
     * salvaged the same way, so it is dropped and the role falls through to
     * the next slug it accepts.
     *
     * @return string|null The usable value, or null if there is none
     */
    private function dereference(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (stripos($value, 'var(') === false) {
            return $value;
        }

        // Bounded: a fallback chain deep enough to exhaust this is a mistake,
        // not a design, and an unbounded loop here would hang the login page.
        for ($depth = 0; $depth < 8; $depth++) {
            $arguments = $this->varArguments($value);

            if ($arguments === null) {
                return null;
            }

            [, $fallback] = $arguments;

            if ($fallback === null) {
                return null;
            }

            $value = trim($fallback);

            if (stripos($value, 'var(') === false) {
                return $value === '' ? null : $value;
            }
        }

        return null;
    }

    /**
     * Split `var(--name, fallback)` into its two parts, if that is all the
     * value is.
     *
     * @return array{0: string, 1: string|null}|null
     */
    private function varArguments(string $value): ?array
    {
        if (stripos($value, 'var(') !== 0 || ! str_ends_with($value, ')')) {
            return null;
        }

        $inner = substr($value, 4, -1);

        // A custom property name holds neither a comma nor a parenthesis, so
        // whichever of the two comes first settles it: a comma separates the
        // name from its fallback, and everything after it — nested commas
        // included — is that fallback. A closing parenthesis instead means
        // this var() ended before the value did, so the value is more than
        // one reference and none of it can be read as a single one.
        $end = strcspn($inner, ',)');

        if ($end === strlen($inner)) {
            return [$inner, null];
        }

        if ($inner[$end] === ')') {
            return null;
        }

        return [substr($inner, 0, $end), substr($inner, $end + 1)];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $settings = \wp_get_global_settings();

        return $this->settings = is_array($settings) ? $settings : [];
    }
}
