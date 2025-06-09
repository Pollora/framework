<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce\Domain\Contracts;

/**
 * Interface for WooCommerce template resolution services.
 *
 * This interface defines the contract for extending WooCommerce's
 * template system with additional template formats like Blade.
 */
interface TemplateResolverInterface
{
    /**
     * Extend WooCommerce's template loader files with additional template candidates.
     *
     * @param  array  $templates  The current list of template files to search
     * @param  string  $defaultFile  The default template file name
     * @return array The extended list of template files
     */
    public function extendTemplateLoaderFiles(array $templates, string $defaultFile): array;
}
