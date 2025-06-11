<?php

declare(strict_types=1);

namespace Pollora\ThirdParty\WooCommerce\Infrastructure\Services;

use Illuminate\Contracts\Container\Container as ContainerContract;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Str;
use Pollora\ThirdParty\WooCommerce\Domain\Contracts\WooCommerceIntegrationInterface;
use Pollora\ThirdParty\WooCommerce\Domain\Services\WooCommerceService;
use Pollora\ThirdParty\WooCommerce\Infrastructure\Adapters\WordPressWooCommerceAdapter;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * Infrastructure implementation of WooCommerce integration.
 *
 * This class provides the concrete implementation of WooCommerce integration
 * using Laravel's service container and WordPress/WooCommerce functions.
 */
class WooCommerce implements WooCommerceIntegrationInterface
{
    public function __construct(
        private readonly ContainerContract $app,
        private readonly TemplateFinderInterface $templateFinder,
        private readonly ViewFactory $viewFactory,
        private readonly WooCommerceService $domainService,
        private readonly WordPressWooCommerceAdapter $adapter
    ) {}

    /**
     * {@inheritDoc}
     */
    public function loadThemeTemplateHooks(): void
    {
        $this->adapter->locateTemplate('wc-template-hooks.php', true, true);
    }

    /**
     * {@inheritDoc}
     */
    public function addThemeSupport(): void
    {
        $this->adapter->addThemeSupport('woocommerce');
    }

    /**
     * {@inheritDoc}
     */
    public function reviewsTemplate(string $template): string
    {
        $templateObj = $this->domainService->createTemplate($template);

        if (! $templateObj->isWooCommerceTemplate($this->domainService->getAllTemplatePaths())) {
            return $template;
        }

        return $this->template($template);
    }

    /**
     * {@inheritDoc}
     */
    public function template(string $template, string $templateName = ''): string
    {
        // Locate any matching template within the theme
        $themeTemplate = $this->locateThemeTemplate($templateName ?: $template);

        if (! $themeTemplate) {
            return $template;
        }

        // Return filename for WooCommerce status screen
        if ($this->domainService->isWooCommerceStatusScreen(
            $this->adapter->isAdmin(),
            $this->adapter->isDoingAjax(),
            $this->adapter->getCurrentScreen()
        )) {
            return $themeTemplate;
        }

        // Include directly unless it's a Blade file
        if (! Str::endsWith($themeTemplate, '.blade.php')) {
            return $themeTemplate;
        }

        // We have a Blade template, get the view name and create a loader
        $viewName = $this->getViewNameFromTemplate($themeTemplate);

        if (! $viewName || ! $this->viewFactory->exists($viewName)) {
            return $themeTemplate;
        }

        // Create and return the loader file path
        return $this->viewFactory->make($viewName)->makeLoader();
    }

    /**
     * Get view name from template file path.
     *
     * @param  string  $templatePath  The template file path
     * @return string|null The view name or null if it cannot be determined
     */
    protected function getViewNameFromTemplate(string $templatePath): ?string
    {
        $realPath = realpath($templatePath);
        if (! $realPath) {
            return null;
        }

        return $this->templateFinder->getViewNameFromPath($realPath);
    }

    /**
     * Locate the theme's WooCommerce Blade template when available.
     *
     * @param  string  $template  The template name to locate
     * @return string The absolute path to the template, or empty string if not found
     */
    protected function locateThemeTemplate(string $template): string
    {
        // Build the theme template path: woocommerce/single-product.php
        $wcTemplatePath = $this->domainService->getWooCommerceTemplatePath();
        $templateObj = $this->domainService->createTemplate($template);
        $themeTemplate = $wcTemplatePath.$templateObj->getRelativePath($this->domainService->getAllTemplatePaths());

        // Use the template finder to locate the template
        $foundTemplates = $this->templateFinder->locate($themeTemplate);

        // Return the first found template (Blade templates are prioritized in the finder)
        if (! empty($foundTemplates)) {
            return $this->adapter->locateTemplate($foundTemplates);
        }

        return '';
    }
}
