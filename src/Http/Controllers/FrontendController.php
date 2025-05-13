<?php

declare(strict_types=1);

namespace Pollora\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\View;
use Pollora\Theme\Domain\Services\TemplateHierarchy;

class FrontendController extends Controller
{
    /**
     * Create a new FrontendController instance.
     */
    public function __construct(
        /**
         * The template hierarchy instance
         */
        private readonly TemplateHierarchy $templateHierarchy
    ) {}

    /**
     * Handle the automatic view assignment for WordPress templates.
     *
     * This method will automatically determine the appropriate view
     * based on WordPress conditional tags and template hierarchy.
     */
    public function handle(): ?View
    {
        global $wp_query;

        // Obtenir la hiérarchie des templates pour la requête actuelle
        $views = $this->templateHierarchy->hierarchy();

        // Vérifier si des vues existent pour chaque template dans la hiérarchie
        foreach ($views as $view) {
            if (ViewFacade::exists($view)) {
                return view($view);
            }
        }

        // Si aucun template n'est trouvé, retourner une vue 404
        $wp_query->set_404();
        abort(404);
    }
}
