<?php

declare(strict_types=1);

namespace Pollora\Login\Infrastructure\Services;

use Pollora\Hook\Domain\Contract\Filter;
use Pollora\Login\Domain\Contracts\DesignSettingsRepository;
use Pollora\Login\Domain\Models\LoginConfiguration;
use Pollora\Login\Domain\Models\LoginPalette;
use Pollora\Login\Domain\Models\LoginStylesheet;
use Pollora\Login\Domain\Models\ResolvedLogo;

/**
 * Dresses wp-login.php, and the screens that share it, in the theme's design.
 *
 * Everything is emitted on `login_head`, which every login-family screen runs
 * — sign-in, lost password, reset, register, the confirm-admin-email prompt —
 * so none of them is left half-styled. `login_enqueue_scripts` was the
 * obvious hook and is not enough on its own: the stylesheet lives in the
 * package, under vendor/, which is not web-served, so there is no URL to
 * enqueue. Printing it is also what lets the theme's tokens ride along in the
 * same element.
 *
 * The work is done once per request and only when something asks for it; a
 * site whose theme ships no config/login.php never reaches this class.
 */
final class LoginScreen
{
    /**
     * Where the framework tells people it is the framework.
     */
    private const string CREDIT_URL = 'https://pollora.dev';

    private ?string $css = null;

    public function __construct(
        private readonly LoginConfiguration $configuration,
        private readonly DesignSettingsRepository $settings,
        private readonly LogoResolver $logos,
        private readonly LoginStylesheet $stylesheet,
        private readonly Filter $filter,
    ) {}

    /**
     * Mark the screen as ours, so the stylesheet can be scoped to it.
     *
     * @param  mixed  $classes  The classes WordPress is about to print
     * @return mixed The classes, with ours added
     */
    public function bodyClass(mixed $classes): mixed
    {
        if (! is_array($classes)) {
            return $classes;
        }

        $classes[] = 'pollora-login';

        return $classes;
    }

    /**
     * Print the stylesheet into the login screen's head.
     */
    public function printStyles(): void
    {
        $css = $this->css();

        if ($css === '') {
            return;
        }

        echo "<style id=\"pollora-login\">\n".$css."</style>\n";
    }

    /**
     * Where the logo links to.
     *
     * WordPress sends people to wordpress.org from a screen wearing the
     * site's own logo, which reads as a mistake. The site's home page is the
     * only destination that matches what is drawn above it.
     *
     * The filtered value is deliberately ignored rather than passed through:
     * wordpress.org is the only thing that ever arrives here, and a plugin
     * that wants another destination has `login_headerurl` itself.
     */
    public function headerUrl(): string
    {
        if ($this->configuration->url !== null) {
            return $this->configuration->url;
        }

        return \home_url('/');
    }

    /**
     * The words behind the logo — read by screen readers, and shown when the
     * logo fails to load.
     *
     * @param  mixed  $text  WordPress's default text
     */
    public function headerText(mixed $text): mixed
    {
        if ($this->configuration->text !== null) {
            return $this->configuration->text;
        }

        $name = \get_bloginfo('name', 'display');

        return is_string($name) && trim($name) !== '' ? $name : $text;
    }

    /**
     * The discreet mention in the footer.
     */
    public function printCredit(): void
    {
        if (! $this->configuration->poweredBy) {
            return;
        }

        $html = sprintf(
            '<p class="pollora-login-credit"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>',
            $this->escapeUrl(self::CREDIT_URL),
            $this->escapeHtml(__('Powered by Pollora', 'pollora'))
        );

        $html = $this->filter->apply('pollora/login/credit', $html);

        if (is_string($html)) {
            echo $html."\n";
        }
    }

    /**
     * The stylesheet for this request, built once.
     */
    public function css(): string
    {
        if ($this->css !== null) {
            return $this->css;
        }

        $palette = LoginPalette::resolve($this->settings, $this->configuration->tokens);

        $values = $this->filter->apply('pollora/login/palette', $palette->values());

        if (is_array($values)) {
            $palette = LoginPalette::fromValues(array_filter($values, is_string(...)));
        }

        $logo = $this->filter->apply('pollora/login/logo', $this->logo());

        $css = $this->stylesheet->render($palette, $logo instanceof ResolvedLogo ? $logo : null);

        $css = $this->filter->apply('pollora/login/styles', $css);

        return $this->css = is_string($css) ? $css : '';
    }

    /**
     * The logo the theme named, or the one the site set in the customiser.
     *
     * Falling back to `custom_logo` means a site that never opens
     * config/login.php still gets its own mark on the login screen, because
     * it already told WordPress what that mark is.
     */
    private function logo(): ?ResolvedLogo
    {
        if ($this->configuration->hasLogo()) {
            return $this->logos->resolve(
                $this->configuration->logo,
                $this->configuration->logoWidth,
                $this->configuration->logoHeight,
            );
        }

        $attachment = \get_theme_mod('custom_logo');

        if (! is_numeric($attachment) || (int) $attachment <= 0) {
            return null;
        }

        return $this->logos->resolve(
            (int) $attachment,
            $this->configuration->logoWidth,
            $this->configuration->logoHeight,
        );
    }

    private function escapeHtml(string $value): string
    {
        return \esc_html($value);
    }

    private function escapeUrl(string $value): string
    {
        return \esc_url($value);
    }
}
