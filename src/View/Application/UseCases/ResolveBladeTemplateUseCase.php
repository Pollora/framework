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
        if (! $realPath) {
            return $templatePath;
        }

        // Get view name from the template file path
        $viewName = $this->templateFinder->getViewNameFromPath($realPath);
        if (! $viewName) {
            return $templatePath;
        }

        $viewName = trim($viewName, '\\/.');

        // Check if a Blade template exists for this view
        if (! $this->viewFactory->exists($viewName)) {
            return $templatePath;
        }

        // For now, return original template path
        // In a future version, we could integrate with FrontendController
        return $templatePath;
    }
}
