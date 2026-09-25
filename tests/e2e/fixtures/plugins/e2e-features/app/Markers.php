<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures;

use Pollora\Attributes\Action;
use Pollora\Attributes\Filter;
use Pollora\Support\Facades\Asset;

/**
 * Hooks declared by attribute, each leaving a marker in the page.
 */
class Markers
{
    #[Filter('the_content')]
    public function markContent(string $content): string
    {
        return $content.'<p data-e2e-filter="the_content">filtered</p>';
    }

    /**
     * Also hands the test what only a web request can answer: the URL the active theme's
     * built entry resolves to through get_theme_file_uri().
     */
    #[Action('wp_footer')]
    public function markFooter(): void
    {
        $entry = $this->themeEntry();

        printf(
            '<script type="application/json" id="e2e-features">%s</script>',
            wp_json_encode([
                'action' => 'wp_footer',
                'themeEntry' => $entry,
                'themeFileUri' => $entry === null ? null : get_theme_file_uri($entry),
            ])
        );
    }

    /** A file enqueued through the Asset facade with a plain URL, no Vite: it proves it ran. */
    #[Action('init')]
    public function enqueueScript(): void
    {
        Asset::add('e2e-features/script', plugins_url('e2e-features/assets/e2e-features.js'))
            ->toFrontend();
    }

    /** The first entry of the active theme's Vite manifest, as the theme names it. */
    private function themeEntry(): ?string
    {
        $manifest = public_path('build/theme/'.get_stylesheet().'/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        foreach ((array) json_decode((string) file_get_contents($manifest), true) as $source => $chunk) {
            if (($chunk['isEntry'] ?? false) === true) {
                return (string) $source;
            }
        }

        return null;
    }
}
