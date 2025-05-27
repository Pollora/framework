<?php

declare(strict_types=1);

namespace Pollora\Route\UI\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Pollora\Route\Application\Services\ResolveRouteService;
use Pollora\Route\Application\Services\RouteResolution;
use Pollora\Route\Domain\Services\WordPressContextBuilder;

/**
 * Frontend controller for WordPress route resolution
 *
 * Handles the fallback route resolution using the WordPress template
 * hierarchy and route resolution service.
 */
final class FrontendController extends Controller
{
    public function __construct(
        private readonly ResolveRouteService $resolveRouteService,
        private readonly WordPressContextBuilder $contextBuilder
    ) {}

    /**
     * Handle the fallback route for WordPress template hierarchy
     *
     * This method is called when no explicit Laravel route matches.
     * It uses the route resolution service to determine the appropriate
     * response based on WordPress conditions and template hierarchy.
     */
    public function handle(Request $request)
    {
        // Build context for route resolution
        $context = $this->buildContext($request);

        // Resolve the route
        $resolution = $this->resolveRouteService->execute(
            $request->getPathInfo(),
            $request->getMethod(),
            $context
        );

        // Handle the resolution result
        return $this->handleResolution($resolution, $request);
    }

    /**
     * Handle the route resolution result
     */
    private function handleResolution(RouteResolution $resolution, Request $request)
    {
        // If not resolved, return 404
        if (!$resolution->isResolved()) {
            abort(404);
        }

        // Handle route match with action
        if ($resolution->isRouteMatch() && $resolution->hasAction()) {
            return $resolution->executeAction();
        }

        // Handle template hierarchy resolution
        if ($resolution->isTemplateHierarchy()) {
            $templatePath = $resolution->getTemplatePath();

            if ($templatePath) {
                return view($templatePath);
            }
        }

        // Handle special request response
        if ($resolution->isSpecialRequest()) {
            $response = $resolution->getResponse();

            if ($response !== null) {
                return $response;
            }
        }

        // Fallback to 404
        abort(404);
    }

    /**
     * Build context for route resolution
     */
    private function buildContext(Request $request): array
    {
        $baseContext = [
            'request' => $request,
            'uri' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'query_string' => $request->getQueryString(),
            'headers' => $request->headers->all(),
            'parameters' => array_merge(
                $request->query->all(),
                $request->request->all()
            ),
            'timestamp' => time(),
        ];

        return $this->contextBuilder->buildContext($baseContext);
    }

    /**
     * Get debug information for route resolution
     *
     * This method is useful for debugging routing issues.
     */
    public function debug(Request $request)
    {
        if (!config('app.debug')) {
            abort(404);
        }

        $context = $this->buildContext($request);

        return response()->json(
            $this->resolveRouteService->getDebugInfo(
                $request->getPathInfo(),
                $request->getMethod(),
                $context
            )
        );
    }
}
