<?php

declare(strict_types=1);

use Pollora\Permalink\Domain\Services\UrlNormalizer;
use Pollora\Support\Uri;

beforeEach(function (): void {
    $this->normalizer = new UrlNormalizer(new Uri);
});

describe('trailing slash removal', function (): void {
    it('removes trailing slash from path URL', function (): void {
        expect($this->normalizer->removeTrailingSlash('/hello-world/'))->toBe('/hello-world');
    });

    it('removes trailing slash from full URL', function (): void {
        expect($this->normalizer->removeTrailingSlash('https://example.com/hello-world/'))->toBe('https://example.com/hello-world');
    });

    it('removes trailing slash from full root URL', function (): void {
        expect($this->normalizer->removeTrailingSlash('https://example.com/'))->toBe('https://example.com');
    });

    it('preserves root path as slash', function (): void {
        expect($this->normalizer->removeTrailingSlash('/'))->toBe('/');
    });

    it('returns null for null input', function (): void {
        expect($this->normalizer->removeTrailingSlash(null))->toBeNull();
    });

    it('is idempotent for URLs without trailing slash', function (): void {
        expect($this->normalizer->removeTrailingSlash('/hello-world'))->toBe('/hello-world');
    });

    it('handles pagination URLs', function (): void {
        expect($this->normalizer->removeTrailingSlash('/page/2/'))->toBe('/page/2');
    });

    it('handles feed URLs', function (): void {
        expect($this->normalizer->removeTrailingSlash('/feed/'))->toBe('/feed');
    });

    it('preserves query parameters', function (): void {
        expect($this->normalizer->removeTrailingSlash('/foo/?bar=1'))->toBe('/foo?bar=1');
    });

    it('preserves fragment', function (): void {
        expect($this->normalizer->removeTrailingSlash('/foo/#section'))->toBe('/foo#section');
    });

    it('handles empty string', function (): void {
        expect($this->normalizer->removeTrailingSlash(''))->toBe('/');
    });
});
