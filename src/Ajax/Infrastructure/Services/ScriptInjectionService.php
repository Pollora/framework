<?php

declare(strict_types=1);

namespace Pollora\Ajax\Infrastructure\Services;

use Pollora\Hook\Infrastructure\Services\Action;

/**
 * Infrastructure service to inject the AJAX URL as a JS variable in the HTML head.
 */
class ScriptInjectionService
{
    public function __construct(private readonly Action $action) {}

    /**
     * Register the AJAX URL JS variable in the HTML head using wp_head.
     */
    public function registerAjaxUrlScript(): void
    {
        $this->action->add('wp_head', function () {
            echo '<script type="text/javascript">var Pollora = { ajaxurl: "'.esc_url(admin_url('admin-ajax.php')).'" };</script>';
        }, 1);
    }
}
