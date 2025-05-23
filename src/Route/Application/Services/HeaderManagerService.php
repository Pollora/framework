<?php

declare(strict_types=1);

namespace Pollora\Route\Application\Services;

use Pollora\Route\Domain\Contracts\HeaderManagerInterface;

/**
 * Service for managing HTTP headers in responses.
 */
class HeaderManagerService implements HeaderManagerInterface
{
    /**
     * Framework name constant for headers.
     */
    private const FRAMEWORK_NAME = 'Pollora';

    /**
     * Framework header name constant.
     */
    private const FRAMEWORK_HEADER = 'X-Powered-By';

    /**
     * Add identification headers to the response.
     *
     * @param  array<string, string>  $headers  Current headers
     * @return array<string, string> Modified headers
     */
    public function addIdentificationHeaders(array $headers): array
    {
        $headers[self::FRAMEWORK_HEADER] = self::FRAMEWORK_NAME;

        return $headers;
    }

    /**
     * Remove WordPress-specific headers if needed.
     *
     * @param  array<string, string>  $headers  Current headers
     * @param  bool  $isWordPressRoute  Whether this is a WordPress route
     * @param  bool  $isUserLoggedIn  Whether a user is logged in
     * @return array<string, string> Modified headers
     */
    public function cleanupWordPressHeaders(array $headers, bool $isWordPressRoute, bool $isUserLoggedIn): array
    {
        // Only clean headers for non-WordPress routes and anonymous users
        if ($isWordPressRoute || $isUserLoggedIn) {
            return $headers;
        }

        // Remove WordPress-specific headers
        unset($headers['Cache-Control']);
        unset($headers['Expires']);
        unset($headers['Content-Type']);

        return $headers;
    }

    /**
     * Add cache control directives if appropriate.
     *
     * @param  array<string, string>  $headers  Current headers
     * @param  bool  $isUserLoggedIn  Whether a user is logged in
     * @return array<string, string> Modified headers with cache directives
     */
    public function addCacheControlDirectives(array $headers, bool $isUserLoggedIn): array
    {
        // Only add cache directives for anonymous users
        if ($isUserLoggedIn) {
            return $headers;
        }

        // Add cache control directives
        $headers['Cache-Control'] = 'public, must-revalidate, max-age=3600';

        return $headers;
    }
}
