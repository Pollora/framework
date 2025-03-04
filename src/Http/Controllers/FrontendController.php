<?php

declare(strict_types=1);

namespace Pollora\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Pollora\Theme\TemplateHierarchy;

class FrontendController extends Controller
{
    /**
     * Handle the automatic view assignment for WordPress templates.
     *
     * This method will automatically determine the appropriate view
     * based on WordPress conditional tags and template hierarchy.
     *
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */
    public function handle()
    {
        global $wp_query;

        $views = TemplateHierarchy::instance()->hierarchy();

        foreach ($views as $view) {
            if (View::exists($view)) {
               return view($view);
            }
        }

        // If no template is found, return a 404 view
        $wp_query->set_404();
        abort(404);
    }

}
