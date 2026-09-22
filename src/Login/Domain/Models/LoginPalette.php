<?php

declare(strict_types=1);

namespace Pollora\Login\Domain\Models;

use Pollora\Login\Domain\Contracts\DesignSettingsRepository;

/**
 * The handful of design roles the login screen styles itself with.
 *
 * A theme's palette is a vocabulary, not a set of roles: apiary names its
 * presets `primary`, `primary-hover`, `subtle`, `outline`, `ring`;
 * theme-default names them `primary`, `primary-vivid`, `secondary`,
 * `background`, `border`. Six slugs are common to both, four are not, and a
 * Tailwind-built theme.json drowns them in 294 primitives — `red-50` through
 * `zinc-950` — that say nothing about what a colour is *for*.
 *
 * So a login screen cannot read a role off a slug. It states the roles it
 * needs, and each role names the slugs it would accept, most specific first.
 * A theme that speaks the common vocabulary is styled without configuring
 * anything; a theme that names things its own way points the roles at its own
 * slugs in `config/login.php`, and one that wants a colour no preset holds
 * writes the value in.
 *
 * Resolution never fails: every role has a last-resort value, so a theme with
 * no theme.json at all still gets a coherent screen rather than WordPress's
 * defaults with half a stylesheet on top.
 */
final readonly class LoginPalette
{
    /**
     * Colour roles, and the preset slugs each will accept.
     *
     * @var array<string, list<string>>
     */
    public const array COLOR_ROLES = [
        'background' => ['background', 'surface-alt', 'surface', 'base'],
        'surface' => ['surface', 'background', 'base'],
        'surface-alt' => ['surface-alt', 'surface', 'background'],
        'foreground' => ['foreground', 'contrast', 'ink'],
        'muted' => ['muted', 'subtle', 'gray-500', 'grey-500'],
        'primary' => ['primary', 'brand', 'accent'],
        'primary-hover' => ['primary-hover', 'primary-vivid', 'primary'],
        'accent' => ['accent', 'secondary', 'primary'],
        'outline' => ['outline', 'border', 'gray-200', 'grey-200'],
        'danger' => ['error', 'danger', 'red-600'],
        'success' => ['success', 'green-600'],
    ];

    /**
     * Radius roles, and the preset slugs each will accept.
     *
     * @var array<string, list<string>>
     */
    public const array RADIUS_ROLES = [
        'radius' => ['lg', 'md', 'xl'],
        'radius-sm' => ['md', 'sm', 'xs'],
    ];

    /**
     * Typography roles, and the preset slugs each will accept.
     *
     * @var array<string, list<string>>
     */
    public const array FONT_ROLES = [
        'font' => ['body', 'sans', 'inter-var', 'base'],
        'heading-font' => ['display', 'heading', 'body', 'sans'],
    ];

    /**
     * What each role is worth when the theme declares nothing it accepts.
     *
     * The colours are Pollora's own, read from pollora.dev's stylesheet: a
     * screen that falls all the way back still looks like it was designed.
     *
     * @var array<string, string>
     */
    public const array FALLBACKS = [
        'background' => '#f6f5f7',
        'surface' => '#ffffff',
        'surface-alt' => '#faf9fb',
        'foreground' => '#1d142a',
        'muted' => '#6b7280',
        'primary' => '#ff5334',
        'primary-hover' => '#fb196d',
        'accent' => '#ff8c12',
        'outline' => '#e5e7eb',
        'danger' => '#d63638',
        'success' => '#00a32a',
        'radius' => '.75rem',
        'radius-sm' => '.5rem',
        'font' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        'heading-font' => 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
    ];

    /**
     * The prefix every variable this palette emits carries.
     */
    public const string VARIABLE_PREFIX = '--pollora-login-';

    /**
     * @param  array<string, string>  $values  role => resolved CSS value
     */
    private function __construct(private array $values) {}

    /**
     * Resolve every role against the theme's settings.
     *
     * @param  array<string, list<string>|string>  $overrides  role => accepted slugs, or a literal CSS value
     */
    public static function resolve(DesignSettingsRepository $settings, array $overrides = []): self
    {
        $values = [];

        foreach (self::COLOR_ROLES as $role => $slugs) {
            $values[$role] = self::pick($role, $slugs, $settings->colors(), $overrides);
        }

        foreach (self::RADIUS_ROLES as $role => $slugs) {
            $values[$role] = self::pick($role, $slugs, $settings->radii(), $overrides);
        }

        foreach (self::FONT_ROLES as $role => $slugs) {
            $values[$role] = self::pick($role, $slugs, $settings->fontFamilies(), $overrides);
        }

        return new self($values);
    }

    /**
     * Build a palette straight from role values, without any theme settings.
     *
     * @param  array<string, string>  $values
     */
    public static function fromValues(array $values): self
    {
        return new self(array_merge(self::FALLBACKS, $values));
    }

    /**
     * The value a role resolved to.
     */
    public function value(string $role): string
    {
        return $this->values[$role] ?? self::FALLBACKS[$role] ?? '';
    }

    /**
     * Every role, keyed by role name.
     *
     * @return array<string, string>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * The palette as CSS custom properties, keyed by property name.
     *
     * @return array<string, string>
     */
    public function toCssVariables(): array
    {
        $variables = [];

        foreach ($this->values as $role => $value) {
            $variables[self::VARIABLE_PREFIX.$role] = $value;
        }

        return $variables;
    }

    /**
     * Resolve one role: an explicit override first, then the slugs it accepts,
     * then its last-resort value.
     *
     * @param  list<string>  $slugs
     * @param  array<string, string>  $presets
     * @param  array<string, list<string>|string>  $overrides
     */
    private static function pick(string $role, array $slugs, array $presets, array $overrides): string
    {
        $override = $overrides[$role] ?? null;

        // A string is the value itself — the way out for a colour no preset
        // holds. An array replaces the slugs this role accepts.
        if (is_string($override) && $override !== '') {
            return $override;
        }

        if (is_array($override) && $override !== []) {
            $slugs = array_values(array_filter($override, is_string(...)));
        }

        foreach ($slugs as $slug) {
            if (isset($presets[$slug]) && $presets[$slug] !== '') {
                return $presets[$slug];
            }
        }

        return self::FALLBACKS[$role] ?? '';
    }
}
