<?php

declare(strict_types=1);

namespace Pollora\Theme\Infrastructure\Services;

/**
 * Resolves a theme's compiled stylesheets so the block editor can show what
 * the front end shows.
 *
 * A theme declaring `editor-styles` support is telling WordPress it wants its
 * own styles inside the editor, but that support alone loads nothing: it only
 * changes how WordPress treats the styles it is given. Something still has to
 * name the files, and the themes here never did — their assets are registered
 * with `toFrontend()` only, so the editor rendered blocks with theme.json's
 * variables and none of the rules that give them their shape.
 *
 * Enqueueing on `enqueue_block_editor_assets` would not fix it either: the post
 * editor runs in an iframe, and assets enqueued there land in the admin page
 * around it. `add_editor_style()` is what WordPress injects into the iframe.
 */
class EditorStyleResolver
{
    /**
     * Cache of resolved stylesheet URLs, keyed by theme slug.
     *
     * @var array<string, list<string>>
     */
    private array $cache = [];

    public function __construct(
        private readonly string $publicPath,
    ) {}

    /**
     * Stylesheets built for this theme, as paths relative to the public
     * directory, in manifest order.
     *
     * Paths rather than URLs: the caller turns them into URLs with WordPress's
     * own helper, which is the only thing that knows where the site is served
     * from — and it is not loaded when this service is constructed.
     *
     * Returns an empty list when the theme has no build yet, which is the
     * normal state before `npm run build` has run.
     *
     * @return list<string>
     */
    public function resolve(string $themeSlug): array
    {
        if (array_key_exists($themeSlug, $this->cache)) {
            return $this->cache[$themeSlug];
        }

        return $this->cache[$themeSlug] = $this->readManifest($themeSlug);
    }

    /**
     * @return list<string>
     */
    private function readManifest(string $themeSlug): array
    {
        $buildPath = 'build/theme/'.$themeSlug;
        $manifestPath = rtrim($this->publicPath, '/').'/'.$buildPath.'/manifest.json';

        if (! is_readable($manifestPath)) {
            return [];
        }

        $contents = file_get_contents($manifestPath);

        if ($contents === false) {
            return [];
        }

        $manifest = json_decode($contents, true);

        if (! is_array($manifest)) {
            return [];
        }

        $styles = [];

        foreach ($manifest as $chunk) {
            if (! is_array($chunk)) {
                continue;
            }

            // Only entry points: a shared chunk's CSS is already pulled in by
            // the entry that depends on it, and listing it twice would have
            // WordPress inject the same rules more than once.
            if (($chunk['isEntry'] ?? false) !== true) {
                continue;
            }

            foreach ($chunk['css'] ?? [] as $file) {
                if (is_string($file) && $file !== '') {
                    $styles[$buildPath.'/'.$file] = true;
                }
            }

            // A pure CSS entry — a block's editor.css, say — has no `css` key:
            // the stylesheet is the chunk's own output file.
            $file = $chunk['file'] ?? null;

            if (is_string($file) && str_ends_with($file, '.css')) {
                $styles[$buildPath.'/'.$file] = true;
            }
        }

        return array_keys($styles);
    }
}
