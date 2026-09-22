<?php

declare(strict_types=1);

namespace Pollora\Login\Domain\Models;

/**
 * Builds the CSS the login screen is dressed with: the theme's tokens as
 * custom properties, followed by the rules that consume them.
 *
 * Kept in the domain, and given the stylesheet as a string rather than a path,
 * so what it produces can be asserted without a filesystem or WordPress.
 */
final readonly class LoginStylesheet
{
    /**
     * Where the logo rules start and end in the stylesheet.
     *
     * They are cut out when no logo resolved: `background-image: var(--x)`
     * with `--x` undeclared is invalid at computed-value time, and the anchor
     * would end up with no background at all — erasing WordPress's own logo
     * rather than replacing it.
     */
    private const string LOGO_BLOCK = '/\/\* pollora:logo-start \*\/.*?\/\* pollora:logo-end \*\//s';

    public function __construct(private string $rules) {}

    /**
     * Render the complete stylesheet.
     */
    public function render(LoginPalette $palette, ?ResolvedLogo $logo): string
    {
        $declarations = $this->declarations($palette->toCssVariables());
        $rules = $this->rules;

        if ($logo instanceof ResolvedLogo) {
            $declarations .= $this->logoDeclarations($logo);
        } else {
            $rules = (string) preg_replace(self::LOGO_BLOCK, '', $rules);
        }

        return ":root {\n".$declarations."}\n\n".trim($rules)."\n";
    }

    /**
     * The palette's declarations, each value checked before it is written.
     *
     * @param  array<string, string>  $variables
     */
    private function declarations(array $variables): string
    {
        $declarations = '';

        foreach ($variables as $property => $value) {
            $value = $this->sanitise($value);

            if ($value === '') {
                continue;
            }

            $declarations .= "\t".$property.': '.$value.";\n";
        }

        return $declarations;
    }

    /**
     * The logo's declarations.
     *
     * These skip {@see sanitise()} deliberately. A data URI carries the very
     * characters that check rejects — `data:image/svg+xml;base64,` has a
     * semicolon in it, and base64 contains `+` and `/` — and running it
     * through a palette's rules silently dropped the logo. The value does not
     * need that check: it is built by LogoResolver, which quotes it and
     * strips the only sequence that could end the `<style>` element.
     */
    private function logoDeclarations(ResolvedLogo $logo): string
    {
        return "\t".LoginPalette::VARIABLE_PREFIX.'logo: '.$logo->cssUrl.";\n"
            ."\t".LoginPalette::VARIABLE_PREFIX.'logo-width: '.$logo->width."px;\n"
            ."\t".LoginPalette::VARIABLE_PREFIX.'logo-height: '.$logo->height."px;\n";
    }

    /**
     * Keep a value from being anything but a value.
     *
     * Most of these come from a theme.json committed to a repository, but the
     * `custom` origin is whatever the site editor saved in the database, and
     * that is an admin-editable string landing inside a `<style>` element. A
     * value carrying a brace, a semicolon, a comment terminator or a tag is
     * not a colour; it is dropped, and the role falls back to its default
     * through CSS's own cascade.
     */
    private function sanitise(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        foreach (['{', '}', ';', '<', '>', '*/', '@import', 'expression('] as $forbidden) {
            if (stripos($value, $forbidden) !== false) {
                return '';
            }
        }

        return $value;
    }
}
