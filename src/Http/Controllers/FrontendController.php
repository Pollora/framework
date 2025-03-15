<?php

declare(strict_types=1);

namespace Pollora\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View;
use Pollora\Theme\TemplateHierarchy;

class FrontendController extends Controller
{
    /**
     * The template hierarchy instance
     */
    private TemplateHierarchy $templateHierarchy;

    /**
     * Create a new FrontendController instance.
     */
    public function __construct(TemplateHierarchy $templateHierarchy)
    {
        $this->templateHierarchy = $templateHierarchy;
    }

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

        // Obtenir la hiérarchie des templates pour la requête actuelle
        $views = $this->templateHierarchy->hierarchy();

        // Vérifier si des vues existent pour chaque template dans la hiérarchie
        foreach ($views as $view) {
            if (View::exists($view)) {
                return view($view);
            }
        }

        // Si aucun template n'est trouvé, retourner une vue 404
        $wp_query->set_404();
        abort(404);
    }
}
