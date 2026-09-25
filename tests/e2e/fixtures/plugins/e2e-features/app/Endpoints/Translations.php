<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures\Endpoints;

use Pollora\Attributes\WpRestRoute;
use Pollora\Attributes\WpRestRoute\Method;

/**
 * What `__()` answers, on each side of the helper that shares the name: WordPress keeps
 * its own as `__wp()`, and `__()` sends a call with a text domain to WordPress and a call
 * with replacements to Laravel. This plugin's fr_FR catalogue is what WordPress finds,
 * whatever the site's language.
 */
#[WpRestRoute('e2e/v1', '/translations')]
class Translations
{
    /** @return array{wordpress: string, untranslated: string, laravel: string, wpNative: string} */
    #[Method('GET')]
    public function show(): array
    {
        // Loaded as the catalogue of the current locale, whatever the site's language:
        // since WordPress 6.5 translations are kept per locale, so loading it as fr_FR
        // on an en_US site would leave __() nothing to find.
        unload_textdomain('e2e-features');
        load_textdomain('e2e-features', WP_PLUGIN_DIR.'/e2e-features/languages/e2e-features-fr_FR.mo', determine_locale());

        return [
            'wordpress' => __('E2E greeting', 'e2e-features'),
            'untranslated' => __('E2E string outside the catalogue', 'e2e-features'),
            'laravel' => __('Shipping :brand', ['brand' => 'Example']),
            'wpNative' => __wp('E2E greeting', 'e2e-features'),
        ];
    }
}
