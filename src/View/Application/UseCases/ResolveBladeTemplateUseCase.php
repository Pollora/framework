<?php

declare(strict_types=1);

namespace Pollora\View\Application\UseCases;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * Use case for resolving Blade templates from WordPress template paths.
 *
 * This use case handles the core logic of determining if a Blade template
 * should be used instead of a PHP template, and stores the necessary
 * data for rendering.
 */
class ResolveBladeTemplateUseCase
{
    public function __construct(
        private readonly TemplateFinderInterface $templateFinder,
        private readonly ViewFactory $viewFactory
    ) {}

    /**
     * Execute the use case to resolve a Blade template.
     *
     * @param  string  $templatePath  WordPress template file path
     * @return string Template path to use (original or modified)
     */
    public function execute(string $templatePath): string
    {
        $realPath = realpath($templatePath);
        if (in_array($realPath, ['', '0', false], true)) {
            return $templatePath;
        }

        // Get view name from the template file path
        $viewName = $this->templateFinder->getViewNameFromPath($realPath);
        if (in_array($viewName, [null, '', '0'], true)) {
            return $templatePath;
        }

        $viewName = trim($viewName, '\\/.');

        // Check if a Blade template exists for this view
        if (! $this->viewFactory->exists($viewName)) {
            return $templatePath;
        }

        // Return the original template path intentionally.
        // FrontendController resolves Blade views natively via View::make(),
        // so no loader transformation is needed here. WooCommerce templates
        // use their own loader path via WooCommerce::template().
        return $templatePath;
    }
}
