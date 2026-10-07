<?php

declare(strict_types=1);

namespace Pollora\View\Application\UseCases;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Pollora\View\Domain\Contracts\TemplateFinderInterface;

/**
 * Turns a Blade template into a file PHP can include.
 *
 * Included as it is, a `.blade.php` file prints its own source. The loader
 * renders the view instead, with the composers and shared data of the
 * application, as WooCommerce templates already are.
 */
class MakeTemplateIncludableUseCase
{
    public function __construct(
        private readonly TemplateFinderInterface $templateFinder,
        private readonly ViewFactory $viewFactory
    ) {}

    /**
     * @param  string  $templatePath  Template path WordPress resolved
     * @return string The loader of the view, or the path unchanged when it is not a Blade view
     */
    public function execute(string $templatePath): string
    {
        if (! str_ends_with($templatePath, '.blade.php')) {
            return $templatePath;
        }

        $realPath = realpath($templatePath);

        if ($realPath === false) {
            return $templatePath;
        }

        $viewName = trim((string) $this->templateFinder->getViewNameFromPath($realPath), '\\/.');

        if ($viewName === '' || ! $this->viewFactory->exists($viewName)) {
            return $templatePath;
        }

        return $this->viewFactory->make($viewName)->makeLoader();
    }
}
