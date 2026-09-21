<?php

declare(strict_types=1);

namespace Pollora\Theme\UI\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Pollora\Theme\Application\Services\ThemeAvailability;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Replaces the "View [x] not found" crash with instructions when no theme exists.
 *
 * Only a missing view on a front-end request is taken over, and only while the
 * site has no theme at all: a site running headless, or one whose theme simply
 * lacks one template, keeps the normal exception so real errors stay visible.
 */
final readonly class MissingThemePage
{
    public function __construct(private ThemeAvailability $availability) {}

    /**
     * Render the setup page, or null to let the exception through untouched.
     */
    public function handle(Throwable $e, Request $request): ?Response
    {
        if (! $this->isMissingView($e)) {
            return null;
        }

        if ($this->isBackendRequest($request)) {
            return null;
        }

        if (! $this->availability->isMissing()) {
            return null;
        }

        return $this->render();
    }

    /**
     * Serve the setup page instead of letting a theme-less site answer a 404.
     *
     * The handle() path above only fires when something calls view() and it
     * throws. Since v13.32.0-beta.3 the skeleton no longer declares WordPress
     * routes, so the template hierarchy decides: nothing calls view(), nothing
     * throws, and a site with no theme simply finds no template and answers a
     * bare 404 — the very crash this class exists to replace, wearing a
     * different status code.
     *
     * template_redirect is where WordPress lets a request be taken over before
     * any template is chosen. It runs before Laravel routes the request, and
     * exiting from it is what runWp() already does for robots, favicons, feeds
     * and trackbacks.
     */
    public function interceptFrontEndRequest(): void
    {
        $response = $this->responseForFrontEndRequest();

        if (! $response instanceof Response) {
            return;
        }

        $response->send();

        exit;
    }

    /**
     * The decision behind interceptFrontEndRequest(), separated from the exit
     * so it can be tested: null means let the request carry on untouched.
     */
    public function responseForFrontEndRequest(): ?Response
    {
        if ($this->isNonFrontRequest()) {
            return null;
        }

        if (! $this->availability->isMissing()) {
            return null;
        }

        return $this->render();
    }

    /**
     * Requests that must keep their normal response even with no theme: the
     * admin is where the user goes to fix this, and an API caller wants its
     * status code, not a setup page.
     */
    private function isNonFrontRequest(): bool
    {
        return (function_exists('is_admin') && is_admin())
            || (function_exists('wp_doing_ajax') && wp_doing_ajax())
            || (defined('REST_REQUEST') && REST_REQUEST);
    }

    public function render(): Response
    {
        $html = View::file(
            dirname(__DIR__, 4).'/resources/views/theme-missing.blade.php',
            ['prefix' => $this->commandPrefix()]
        )->render();

        // 503 rather than 500: the site is not broken, it is not set up yet,
        // and search engines should not record this page as the site.
        return response($html, Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Laravel's view factory throws a plain InvalidArgumentException for a
     * missing view, so the message is the only thing that identifies it.
     */
    private function isMissingView(Throwable $e): bool
    {
        return $e instanceof InvalidArgumentException
            && str_starts_with($e->getMessage(), 'View [')
            && str_contains($e->getMessage(), '] not found');
    }

    private function isBackendRequest(Request $request): bool
    {
        $path = '/'.ltrim($request->path(), '/');

        foreach (['/wp-admin', '/wp-login.php', '/wp-json', '/api', '/cms/wp-admin', '/cms/wp-login.php'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return $request->expectsJson();
    }

    /**
     * Commands are copied into the user's own shell, which is outside the
     * container when the project runs on DDEV.
     */
    private function commandPrefix(): string
    {
        return getenv('IS_DDEV_PROJECT') === 'true' ? 'ddev exec ' : '';
    }
}
