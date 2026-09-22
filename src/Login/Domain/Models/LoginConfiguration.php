<?php

declare(strict_types=1);

namespace Pollora\Login\Domain\Models;

/**
 * What a theme declares about its login screen, parsed once.
 *
 * Colours, typography and radii are not here: they are already the theme's
 * truth in theme.json, and duplicating them would mean restyling a theme
 * twice. What is here is everything theme.json has no business holding — a
 * logo file, the link it points at, the words behind it — plus the two
 * switches a project needs, and the role overrides for a theme whose preset
 * names differ from the ones {@see LoginPalette} expects.
 *
 * It reads `config/login.php` of the active theme, which the theme module
 * loads into `theme.login` like every other config file it finds. No config
 * file means no login customisation at all: an existing site that upgrades
 * the framework sees its login screen unchanged until it asks otherwise.
 */
final readonly class LoginConfiguration
{
    /**
     * @param  int|string|null  $logo  An attachment id, a path or a URL — resolved by infrastructure
     * @param  array<string, list<string>|string>  $tokens  Role overrides for the palette
     */
    private function __construct(
        public bool $enabled,
        public int|string|null $logo,
        public ?int $logoWidth,
        public ?int $logoHeight,
        public ?string $url,
        public ?string $text,
        public bool $poweredBy,
        public array $tokens,
    ) {}

    /**
     * Parse the theme's `config/login.php`.
     *
     * @param  array<string, mixed>|null  $config  Null when the theme ships no such file
     */
    public static function fromArray(?array $config): self
    {
        if ($config === null) {
            return self::disabled();
        }

        $logo = $config['logo'] ?? null;

        // `'logo' => 'path/to.svg'` and `'logo' => ['source' => …]` both read
        // naturally, and a theme that only wants to swap the file should not
        // have to learn the longer form.
        $logo = is_array($logo) ? $logo : ['source' => $logo];

        return new self(
            enabled: (bool) ($config['enabled'] ?? true),
            logo: self::source($logo['source'] ?? null),
            logoWidth: self::positiveInt($logo['width'] ?? null),
            logoHeight: self::positiveInt($logo['height'] ?? null),
            url: self::text($logo['url'] ?? null),
            text: self::text($logo['text'] ?? null),
            poweredBy: (bool) ($config['powered_by'] ?? true),
            tokens: is_array($config['tokens'] ?? null) ? $config['tokens'] : [],
        );
    }

    /**
     * The state of a site that has asked for nothing.
     */
    public static function disabled(): self
    {
        return new self(false, null, null, null, null, null, false, []);
    }

    /**
     * Whether a logo was named at all.
     */
    public function hasLogo(): bool
    {
        return $this->logo !== null;
    }

    private static function source(mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        // An attachment id read from a config file is often a numeric string.
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value > 0 ? (int) $value : null;
        }

        return self::text($value);
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
