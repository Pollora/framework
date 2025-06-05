<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce\View;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Str;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * WooCommerce template resolver using the new template hierarchy system.
 *
 * This class provides WooCommerce-specific template resolution
 * that integrates with Pollora's hexagonal template hierarchy architecture.
 */
class WooCommerceTemplateResolver
{
    public function __construct(
        private readonly TemplateFinderInterface $templateFinder,
        private readonly ViewFactory $viewFactory
    ) {}

    /**
     * Extend WooCommerce's template loader files with Blade template candidates.
     * This hooks into 'woocommerce_template_loader_files' filter.
     */
    public function extendTemplateLoaderFiles(array $templates, string $defaultFile): array
    {
        if (! $defaultFile) {
            return $templates;
        }

        $bladeTemplates = [];

        // For each template in the search list, add Blade equivalents
        foreach ($templates as $template) {
            // Add Blade version before PHP version
            if (str_ends_with($template, '.php') && ! str_ends_with($template, '.blade.php')) {
                $bladeTemplate = str_replace('.php', '.blade.php', $template);
                $bladeTemplates[] = 'views' . DIRECTORY_SEPARATOR . $bladeTemplate;
            }
        }

        // Add Blade version of default file
        if (str_ends_with($defaultFile, '.php')) {
            $bladeDefaultFile = str_replace('.php', '.blade.php', $defaultFile);
            $bladeTemplates[] = 'views' . DIRECTORY_SEPARATOR . $bladeDefaultFile;
            $bladeTemplates[] = 'views' . DIRECTORY_SEPARATOR . WC()->template_path().$bladeDefaultFile;
        }

        // Merge Blade templates at the beginning so they have priority
        return array_merge($bladeTemplates, $templates);
    }
}
