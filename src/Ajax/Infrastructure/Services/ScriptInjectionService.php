<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Services;

/**
 * Infrastructure service to inject the AJAX URL as a JS variable in the HTML head.
 */
class ScriptInjectionService
{
    /**
     * Register the AJAX URL JS variable in the HTML head using wp_head.
     *
     * @return void
     */
    public function registerAjaxUrlScript(): void
    {
        add_action('wp_head', function () {
            echo '<script type="text/javascript">var Pollora = { ajaxurl: "' . esc_url(admin_url('admin-ajax.php')) . '" };</script>';
        }, 1);
    }
}
