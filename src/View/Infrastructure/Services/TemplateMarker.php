<?php

declare(strict_types=1);

namespace Pollora\View\Infrastructure\Services;

/**
 * Says which template answered, in the page itself, while debugging.
 *
 * WordPress gives no way to see which template rendered a request short of
 * reading the code and guessing. With a Blade hierarchy on top of it, the
 * guess gets harder: several files can plausibly have answered, and the one
 * that did is the whole question when a page comes back wrong.
 *
 * Themes had started solving this by hand — theme-apiary marks its views one
 * by one, some with a `data-pollora-template` attribute and some with an HTML
 * comment. That drifts: its front page carries no marker at all, so anything
 * relying on it silently measures nothing there. Doing it once, from the
 * template the request actually resolved to, cannot drift.
 *
 * Emitted only when WP_DEBUG is on, and as an HTML comment, so it changes no
 * markup, no styling and no production output.
 */
final class TemplateMarker
{
    private ?string $template = null;

    private bool $emitted = false;

    /**
     * Remember the template the request resolved to.
     *
     * Hooked on `template_include`, which is the single point every path goes
     * through — Pollora's own frontend controller applies it too.
     *
     * @param  mixed  $template  The template path WordPress settled on
     * @return mixed The template, untouched
     */
    public function capture(mixed $template): mixed
    {
        if (is_string($template) && $template !== '') {
            $this->template = $template;
        }

        return $template;
    }

    /**
     * Write the marker into <head>.
     *
     * Hooked on `wp_head`, which every theme calls — WordPress requires it —
     * unlike a body class or a wrapper element, which only a theme that
     * cooperates would carry.
     */
    public function emit(): void
    {
        // Once per request. wp_head fires more than once on some pages —
        // measured on a front page — and a marker that appears twice reads
        // like two templates answered.
        if ($this->template === null || $this->emitted) {
            return;
        }

        $this->emitted = true;

        printf(
            "<!-- pollora:template=\"%s\" path=\"%s\" -->\n",
            $this->escape($this->name($this->template)),
            $this->escape($this->relativePath($this->template))
        );
    }

    /**
     * The template's name in the WordPress hierarchy: `single`, `category`,
     * `archive`, `index`…
     *
     * Both extensions are stripped, so a Blade template and a classic PHP one
     * of the same rank report the same name.
     */
    public function name(string $template): string
    {
        $name = basename($template);

        foreach (['.blade.php', '.php'] as $extension) {
            if (str_ends_with($name, $extension)) {
                return substr($name, 0, -strlen($extension));
            }
        }

        return $name;
    }

    /**
     * The path, relative to the project, so the marker names a file someone
     * can open — and never leaks where the site lives on disk.
     */
    public function relativePath(string $template): string
    {
        $base = $this->basePath();

        if ($base !== '' && str_starts_with($template, $base.DIRECTORY_SEPARATOR)) {
            return substr($template, strlen($base) + 1);
        }

        return basename($template);
    }

    private function basePath(): string
    {
        if (! function_exists('base_path')) {
            return '';
        }

        try {
            return rtrim(base_path(), DIRECTORY_SEPARATOR);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Keep the comment a comment.
     *
     * A template path is not user input, but it passes through filters any
     * plugin can touch, and `-->` inside an HTML comment ends it early.
     */
    private function escape(string $value): string
    {
        return str_replace(['--', '<', '>'], ['- -', '', ''], $value);
    }
}
