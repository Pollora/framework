<?php

declare(strict_types=1);

namespace Pollora\VersionCheck\Infrastructure\Sources;

/**
 * A JSON GET through WordPress's HTTP API, which honours the site's proxy settings.
 */
class HttpGet
{
    /**
     * @param  array<string, string>  $headers
     */
    public function json(string $url, array $headers = []): mixed
    {
        if (! function_exists('wp_remote_get')) {
            return null;
        }

        $response = wp_remote_get($url, [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json', ...$headers],
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        return json_decode((string) wp_remote_retrieve_body($response), true);
    }
}
