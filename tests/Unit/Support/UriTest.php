<?php

declare(strict_types=1);

use Pollora\Support\Uri;

describe('Uri helper', function (): void {
    it('removes trailing slash from valid urls', function (): void {
        $uri = new Uri;
        expect($uri->removeTrailingSlash('https://example.com/foo/'))->toBe('https://example.com/foo');
        expect($uri->removeTrailingSlash('/foo/bar/'))->toBe('/foo/bar');
    });

    it('handles malformed urls gracefully', function (): void {
        $uri = new Uri;
        $malformed = ':///foo/';
        expect($uri->removeTrailingSlash($malformed))->toBe(':///foo');
    });
});
