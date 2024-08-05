<?php

declare(strict_types=1);

namespace Pollen\Route\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WordPressHeaders
{
    private const FRAMEWORK_NAME = 'Pollen';
    private const FRAMEWORK_HEADER = 'X-Powered-By';
    
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        if (!$response instanceof SymfonyResponse) {
            return $response;
        }

        $this->addFrameworkHeader($response);

        if ($this->shouldCleanupHeaders($request)) {
            $this->removeWordPressHeaders($response);
        }

        if ($this->shouldSetPublicCache()) {
            $response->setPublic();
            $response->headers->addCacheControlDirective('must-revalidate', true);
            $response->headers->addCacheControlDirective('max-age', '3600'); // 1 heure, ajustez selon vos besoins
        }

        return $response;
    }

    private function addFrameworkHeader(SymfonyResponse $response): void
    {
        $response->headers->set(self::FRAMEWORK_HEADER, self::FRAMEWORK_NAME);
    }

    private function shouldCleanupHeaders(Request $request): bool
    {
        return !$request->route()?->hasCondition() &&
            $this->isWordPressFunctionAvailable('is_user_logged_in') &&
            !is_user_logged_in();
    }

    private function removeWordPressHeaders(SymfonyResponse $response): void
    {
        $response->headers->remove('Cache-Control');
        $response->headers->remove('Expires');
        $response->headers->remove('Content-Type');
    }

    private function shouldSetPublicCache(): bool
    {
        return $this->isWordPressFunctionAvailable('is_user_logged_in') && !is_user_logged_in();
    }

    private function isWordPressFunctionAvailable(string $function): bool
    {
        return function_exists($function);
    }
}
