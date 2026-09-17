<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Domain\Contracts;

/**
 * Interface for handling WooCommerce Coming Soon page interception.
 *
 * Replaces WooCommerce's default Coming Soon template with a Blade
 * template from the active theme when available.
 */
interface ComingSoonHandlerInterface
{
    /**
     * Filter the template_include to render a Blade coming soon page.
     *
     * @param  string  $template  The current template path
     * @return string The filtered template path
     */
    public function handleTemplateInclude(string $template): string;
}
