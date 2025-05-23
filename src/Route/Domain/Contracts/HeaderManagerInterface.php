<?php

declare(strict_types=1);

namespace Pollora\Route\Domain\Contracts;

/**
 * Interface for HTTP header management services.
 *
 * Defines methods for manipulating response headers.
 */
interface HeaderManagerInterface
{
    /**
     * Add identification headers to the response.
     *
     * @param  array<string, string>  $headers  Current headers
     * @return array<string, string> Modified headers
     */
    public function addIdentificationHeaders(array $headers): array;

    /**
     * Remove WordPress-specific headers if needed.
     *
     * @param  array<string, string>  $headers  Current headers
     * @param  bool  $isWordPressRoute  Whether this is a WordPress route
     * @param  bool  $isUserLoggedIn  Whether a user is logged in
     * @return array<string, string> Modified headers
     */
    public function cleanupWordPressHeaders(array $headers, bool $isWordPressRoute, bool $isUserLoggedIn): array;

    /**
     * Add cache control directives if appropriate.
     *
     * @param  array<string, string>  $headers  Current headers
     * @param  bool  $isUserLoggedIn  Whether a user is logged in
     * @return array<string, string> Modified headers with cache directives
     */
    public function addCacheControlDirectives(array $headers, bool $isUserLoggedIn): array;
}
