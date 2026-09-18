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
