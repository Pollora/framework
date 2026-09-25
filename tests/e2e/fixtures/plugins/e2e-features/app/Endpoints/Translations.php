<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures\Endpoints;

use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;

/**
 * What `__()` answers, on each side of the helper that shares the name: WordPress keeps
 * its own as `__wp()`, and `__()` sends a call with a text domain to WordPress and a call
 * with replacements to Laravel. This plugin's catalogue is loaded in fr_FR whatever the
 * site's language.
 */
#[WpRestRoute('e2e/v1', '/translations')]
class Translations
{
    /** @return array{wordpress: string, untranslated: string, laravel: string, wpNative: string} */
    #[Method('GET')]
    public function show(): array
    {
        add_filter('plugin_locale', static fn (string $locale, string $domain): string => $domain === 'e2e-features' ? 'fr_FR' : $locale, 10, 2);
        unload_textdomain('e2e-features');
        load_plugin_textdomain('e2e-features', false, 'e2e-features/languages');

        return [
            'wordpress' => __('E2E greeting', 'e2e-features'),
            'untranslated' => __('E2E string outside the catalogue', 'e2e-features'),
            'laravel' => __('Shipping :brand', ['brand' => 'Example']),
            'wpNative' => __wp('E2E greeting', 'e2e-features'),
        ];
    }
}
