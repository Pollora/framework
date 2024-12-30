<?php

declare(strict_types=1);

namespace Pollora\Support;

class URL
{
    public function removeTrailingSlash(?string $url): ?string
    {
        if (empty($url)) {
            return $url;
        }

        $urlParts = parse_url($url);
        $urlParts['path'] = $urlParts['path'] ?? '';
        $urlParts['path'] = rtrim($urlParts['path'], '/');

        return $this->buildUrl($urlParts);
    }

    protected function buildUrl(array $parts): string
    {
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? "?{$parts['query']}" : '';
        $fragment = isset($parts['fragment']) ? "#{$parts['fragment']}" : '';

        return $scheme 
            ? "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}"
            : "{$host}{$port}{$path}{$query}{$fragment}";
    }
}
