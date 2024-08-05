<?php

declare(strict_types=1);

namespace Pollen\Route\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WordPressAuthorize
{
    public function handle(Request $request, Closure $next, string $capability = 'edit_posts')
    {
        if ($this->isUserAuthorized($capability)) {
            return $next($request);
        }

        throw new HttpException(404);
    }

    private function isUserAuthorized(string $capability): bool
    {
        return is_user_logged_in() && current_user_can($capability);
    }
}
