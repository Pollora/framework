<?php

declare(strict_types=1);

namespace Pollora\Login\Infrastructure\Services;

use Pollora\Login\Domain\Models\ResolvedLogo;

/**
 * Turns whatever a theme named as its login logo into something CSS can draw.
 *
 * A file that lives inside the theme has no URL on a Pollora site. Measured:
 * `get_theme_file_uri('resources/assets/css/app.css')` returns an empty
 * string, and the framework's own log says why — the theme directory is not
 * web-served, only the Vite build output under `/build/theme/…` is, and the
 * filter meant to bridge the two prefixes the path a second time
 * (`resources/assets/resources/assets/…`). Putting the logo through the build
 * would work but would make the login screen depend on a build step and on a
 * manifest entry that nothing else references.
 *
 * So a theme file is read from disk and inlined as a data URI. It costs one
 * file read on a screen that is served rarely, and it cannot 404. A URL, an
 * attachment id and a site-root path are all still accepted, and are passed
 * through as they are — a media-library logo is a URL WordPress already
 * serves, and inlining it would be work for nothing.
 */
final readonly class LogoResolver
{
    /**
     * Extensions this will inline, and what to call them in the data URI.
     *
     * @var array<string, string>
     */
    private const array MIME_TYPES = [
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    /**
     * Width a logo is drawn at when the theme names none.
     *
     * Wider than WordPress's 84px square, because the files themes actually
     * use are wordmarks: Pollora's own is 793×320.
     */
    public const int DEFAULT_WIDTH = 240;

    /**
     * Ratio assumed when a file states none — WordPress's own logo box.
     */
    private const float DEFAULT_RATIO = 1.0;

    /**
     * @param  string  $basePath  The theme directory a relative source is read from
     * @param  int  $maxBytes  Above this, a file is not inlined: base64 adds a
     *                         third again, and this is emitted on every login
     *                         request.
     */
    public function __construct(
        private string $basePath,
        private int $maxBytes = 98304,
    ) {}

    /**
     * Resolve a configured source.
     *
     * @param  int|string  $source  An attachment id, a URL, a site-root path, or a path inside the theme
     */
    public function resolve(int|string $source, ?int $width = null, ?int $height = null): ?ResolvedLogo
    {
        if (is_int($source)) {
            return $this->fromAttachment($source, $width, $height);
        }

        if ($this->isAbsoluteReference($source)) {
            return $this->sized($this->cssUrl($source), $width, $height, self::DEFAULT_RATIO);
        }

        return $this->fromFile($this->basePath.'/'.ltrim($source, '/'), $width, $height);
    }

    /**
     * Inline a file read straight from disk.
     */
    public function fromFile(string $path, ?int $width = null, ?int $height = null): ?ResolvedLogo
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! isset(self::MIME_TYPES[$extension]) || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        // Read before measuring, and cast: an unreadable file and an empty
        // one are the same answer here, and collapsing them leaves one
        // branch instead of three.
        $contents = (string) @file_get_contents($path);

        if ($contents === '' || strlen($contents) > $this->maxBytes) {
            return null;
        }

        $dataUri = 'data:'.self::MIME_TYPES[$extension].';base64,'.base64_encode($contents);

        return $this->sized(
            $this->cssUrl($dataUri),
            $width,
            $height,
            $extension === 'svg' ? $this->svgRatio($contents) : $this->rasterRatio($path),
        );
    }

    /**
     * The intrinsic width-to-height ratio an SVG declares.
     *
     * Read from viewBox first — the attribute that survives responsive
     * authoring — and from width/height only if there is no viewBox.
     */
    public function svgRatio(string $contents): float
    {
        if (preg_match('/viewBox\s*=\s*["\']\s*[-\d.eE]+[,\s]+[-\d.eE]+[,\s]+([\d.eE]+)[,\s]+([\d.eE]+)/', $contents, $matches) === 1) {
            $ratio = $this->ratio((float) $matches[1], (float) $matches[2]);

            if ($ratio !== null) {
                return $ratio;
            }
        }

        if (preg_match('/<svg[^>]*\swidth\s*=\s*["\']([\d.]+)/', $contents, $w) === 1
            && preg_match('/<svg[^>]*\sheight\s*=\s*["\']([\d.]+)/', $contents, $h) === 1) {
            $ratio = $this->ratio((float) $w[1], (float) $h[1]);

            if ($ratio !== null) {
                return $ratio;
            }
        }

        return self::DEFAULT_RATIO;
    }

    private function rasterRatio(string $path): float
    {
        $size = @getimagesize($path);

        if (! is_array($size)) {
            return self::DEFAULT_RATIO;
        }

        return $this->ratio((float) $size[0], (float) $size[1]) ?? self::DEFAULT_RATIO;
    }

    private function ratio(float $width, float $height): ?float
    {
        if ($width <= 0.0 || $height <= 0.0) {
            return null;
        }

        return $width / $height;
    }

    private function fromAttachment(int $id, ?int $width, ?int $height): ?ResolvedLogo
    {
        $image = \wp_get_attachment_image_src($id, 'full');

        if (! is_array($image) || ! is_string($image[0] ?? null) || $image[0] === '') {
            return null;
        }

        $ratio = $this->ratio((float) ($image[1] ?? 0), (float) ($image[2] ?? 0)) ?? self::DEFAULT_RATIO;

        return $this->sized($this->cssUrl($image[0]), $width, $height, $ratio);
    }

    /**
     * Settle on the pixel box the logo is drawn in.
     *
     * A theme that states both is obeyed. A theme that states one gets the
     * other from the file's own ratio, which is the point of reading it.
     */
    private function sized(string $cssUrl, ?int $width, ?int $height, float $ratio): ResolvedLogo
    {
        if ($width !== null && $height !== null) {
            return new ResolvedLogo($cssUrl, $width, $height);
        }

        if ($width === null && $height !== null) {
            return new ResolvedLogo($cssUrl, max(1, (int) round($height * $ratio)), $height);
        }

        $width ??= self::DEFAULT_WIDTH;

        return new ResolvedLogo($cssUrl, $width, max(1, (int) round($width / $ratio)));
    }

    private function isAbsoluteReference(string $source): bool
    {
        return (bool) preg_match('#^(https?:)?//#i', $source)
            || str_starts_with($source, 'data:')
            || str_starts_with($source, '/');
    }

    /**
     * Wrap a source in url(), quoted so it cannot escape the declaration.
     *
     * Two escapes, for two different exits. Backslashes, quotes and newlines
     * would end the CSS string; `<` would end the `<style>` element that this
     * whole stylesheet is printed into, which no amount of CSS quoting
     * protects against. Neither belongs in an image reference, so both are
     * removed rather than encoded.
     */
    private function cssUrl(string $source): string
    {
        $source = str_replace(['\\', '"', "\n", "\r", '<', '>'], ['\\\\', '\\"', '', '', '', ''], $source);

        return 'url("'.$source.'")';
    }
}
