<?php

declare(strict_types=1);

use Pollora\Permalink\Domain\Contracts\UrlNormalizerInterface;
use Pollora\Permalink\Infrastructure\Services\PermalinkManager;

beforeEach(function (): void {
    $this->normalizer = Mockery::mock(UrlNormalizerInterface::class);
    $this->manager = new PermalinkManager($this->normalizer);
});

describe('user trailing slash handling', function (): void {
    it('removes trailing slash from generated URLs', function (): void {
        $this->normalizer->shouldReceive('removeTrailingSlash')
            ->with('/hello-world/')
            ->andReturn('/hello-world');

        expect($this->manager->handleUserTrailingSlash('/hello-world/', 'single'))->toBe('/hello-world');
    });

    it('returns original URL when normalizer returns null', function (): void {
        $this->normalizer->shouldReceive('removeTrailingSlash')
            ->with('/test/')
            ->andReturn(null);

        expect($this->manager->handleUserTrailingSlash('/test/', 'page'))->toBe('/test/');
    });

    it('works for all URL types', function (string $type): void {
        $this->normalizer->shouldReceive('removeTrailingSlash')
            ->with('/url/')
            ->andReturn('/url');

        expect($this->manager->handleUserTrailingSlash('/url/', $type))->toBe('/url');
    })->with(['single', 'page', 'category', 'post_tag', 'author', 'paged', 'feed']);
});

describe('canonical redirect handling', function (): void {
    it('removes trailing slash from canonical URL', function (): void {
        Brain\Monkey\Functions\when('home_url')->justReturn('https://example.com');

        $this->normalizer->shouldReceive('removeTrailingSlash')
            ->with('https://example.com/hello-world/')
            ->andReturn('https://example.com/hello-world');

        expect($this->manager->handleCanonicalRedirect('https://example.com/hello-world/'))->toBe('https://example.com/hello-world');
    });

    it('returns null for null input', function (): void {
        expect($this->manager->handleCanonicalRedirect(null))->toBeNull();
    });

    it('preserves homepage URL with query parameters', function (): void {
        Brain\Monkey\Functions\when('home_url')->justReturn('https://example.com');

        $url = 'https://example.com/?author=1';

        expect($this->manager->handleCanonicalRedirect($url))->toBe($url);
    });

    it('normalizes non-homepage URLs with query parameters', function (): void {
        Brain\Monkey\Functions\when('home_url')->justReturn('https://example.com');

        $this->normalizer->shouldReceive('removeTrailingSlash')
            ->with('https://example.com/blog/?page=2')
            ->andReturn('https://example.com/blog?page=2');

        expect($this->manager->handleCanonicalRedirect('https://example.com/blog/?page=2'))->toBe('https://example.com/blog?page=2');
    });
});

describe('permalink structure update', function (): void {
    it('stores structure without trailing slash', function (): void {
        $stored = null;
        Brain\Monkey\Functions\when('update_option')->alias(function (string $key, string $value) use (&$stored): bool {
            $stored = [$key, $value];

            return true;
        });

        $this->manager->updateStructure('/%postname%/');

        expect($stored)->toBe(['permalink_structure', '/%postname%']);
    });

    it('does not update for empty structure', function (): void {
        $called = false;
        Brain\Monkey\Functions\when('update_option')->alias(function () use (&$called): bool {
            $called = true;

            return true;
        });

        $this->manager->updateStructure('');

        expect($called)->toBeFalse();
    });

    it('does not update for false structure', function (): void {
        $called = false;
        Brain\Monkey\Functions\when('update_option')->alias(function () use (&$called): bool {
            $called = true;

            return true;
        });

        $this->manager->updateStructure(false);

        expect($called)->toBeFalse();
    });
});
