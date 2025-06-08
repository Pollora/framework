<?php

declare(strict_types=1);

namespace Pollora\Plugins\WooCommerce\View;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * WooCommerce template resolver for extending template loader files.
 *
 * This class provides WooCommerce-specific template resolution by extending
 * the template search list with Blade template candidates. It integrates with
 * WooCommerce's 'woocommerce_template_loader_files' filter to inject Blade
 * templates into the search hierarchy.
 */
class WooCommerceTemplateResolver
{
    /**
     * The template finder for locating template files.
     */
    private readonly TemplateFinderInterface $templateFinder;

    /**
     * The view factory for creating views.
     */
    private readonly ViewFactory $viewFactory;

    /**
     * Create a new WooCommerceTemplateResolver instance.
     *
     * @param  TemplateFinderInterface  $templateFinder  Template finder service
     * @param  ViewFactory  $viewFactory  View factory for creating views
     */
    public function __construct(
        TemplateFinderInterface $templateFinder,
        ViewFactory $viewFactory
    ) {
        $this->templateFinder = $templateFinder;
        $this->viewFactory = $viewFactory;
    }

    /**
     * Extend WooCommerce's template loader files with Blade template candidates.
     *
     * This method hooks into the 'woocommerce_template_loader_files' filter
     * to add Blade template versions to the search list. Blade templates
     * are added with higher priority than their PHP counterparts.
     *
     * @param  array  $templates  The current list of template files to search
     * @param  string  $defaultFile  The default template file name
     * @return array The extended list of template files including Blade variants
     */
    public function extendTemplateLoaderFiles(array $templates, string $defaultFile): array
    {
        if (! $defaultFile) {
            return $templates;
        }

        $bladeTemplates = [];

        // Convert existing templates to Blade equivalents
        foreach ($templates as $template) {
            $bladeTemplate = $this->convertToBladeTemplate($template);
            if ($bladeTemplate !== $template) {
                $bladeTemplates[] = $bladeTemplate;
            }
        }

        // Add Blade version of the default file
        $bladeDefaultFile = $this->convertToBladeTemplate($defaultFile);
        if ($bladeDefaultFile !== $defaultFile) {
            $bladeTemplates[] = 'views/' . $bladeDefaultFile;

            // Also add the WooCommerce template path version
            if (function_exists('WC') && WC() && method_exists(WC(), 'template_path')) {
                $wcPath = WC()->template_path();
                $bladeTemplates[] = 'views/' . $wcPath . $bladeDefaultFile;
            }
        }

        // Remove duplicates and merge Blade templates at the beginning for priority
        $bladeTemplates = array_unique($bladeTemplates);

        return array_merge($bladeTemplates, $templates);
    }

    /**
     * Convert a PHP template name to its Blade equivalent.
     *
     * This method takes a template file name and converts it to the
     * corresponding Blade template name if it's a PHP file.
     *
     * @param  string  $template  The template file name
     * @return string The Blade template file name or original if not convertible
     */
    protected function convertToBladeTemplate(string $template): string
    {
        // Only convert PHP files that aren't already Blade files
        if (str_ends_with($template, '.php') && ! str_ends_with($template, '.blade.php')) {
            return str_replace('.php', '.blade.php', $template);
        }

        return $template;
    }
}
