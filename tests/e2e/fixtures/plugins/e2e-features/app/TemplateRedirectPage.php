<?php

declare(strict_types=1);

namespace Plugin\E2eFeatures;

use Illuminate\Support\Facades\Request;
use Pollora\Attributes\Action;

/**
 * Answers from template_redirect the way WooCommerce's Review Order page does:
 * it includes the theme's 404 template itself, never going through
 * template_include (#419).
 */
class TemplateRedirectPage
{
    #[Action('template_redirect')]
    public function renderNotFound(): void
    {
        if (Request::query('e2e-template-redirect') === null) {
            return;
        }

        status_header(404);
        nocache_headers();

        $template = get_query_template('404');

        if ($template !== '' && file_exists($template)) {
            include $template;
        }

        exit;
    }
}
