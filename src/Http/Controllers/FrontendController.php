<?php

declare(strict_types=1);

namespace Pollora\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;
use Pollora\TemplateHierarchy\TemplateHierarchy;

class FrontendController extends Controller
{
    /**
     * Create a new FrontendController instance.
     *
     * @param  TemplateHierarchy  $templateHierarchy  Template hierarchy resolver
     */
    public function __construct(
        /**
         * Template hierarchy resolver
         */
        private readonly TemplateHierarchy $templateHierarchy
    ) {}

    /**
     * Handle the automatic view assignment for WordPress templates.
     *
     * This method automatically determines the appropriate view
     * based on WordPress conditional tags and the template hierarchy.
     *
     * @return View|null The resolved view instance or null on failure
     */
    public function handle(): ?View
    {
        global $wp_query;

        // Get the template hierarchy for the current request
        // Use the method that directly returns the template paths
        $views = $this->templateHierarchy->getAllTemplatePaths();

        // Check if a view exists for each template in the hierarchy
        foreach ($views as $view) {
            if (ViewFacade::exists($view)) {
                return view($view);
            }
        }

        // If no template is found, return a 404 view
        $wp_query->set_404();
        abort(404);
    }
}
