<?php

declare(strict_types=1);

use Pollora\WordPress\AppUrl;

describe('normalize', function (): void {
    it('adds http:// to a URL without a scheme', function (string $given, string $expected): void {
        expect(AppUrl::normalize($given))->toBe($expected);
    })->with([
        'a host' => ['pollora.test', 'http://pollora.test'],
        'a host and a port' => ['localhost:8888', 'http://localhost:8888'],
        'a host with a path' => ['pollora.test/site', 'http://pollora.test/site'],
        'a protocol-relative URL' => ['//pollora.test', 'http://pollora.test'],
        'spaces around' => ['  pollora.test ', 'http://pollora.test'],
    ]);

    it('leaves a URL that has a scheme alone', function (string $url): void {
        expect(AppUrl::normalize($url))->toBe($url);
    })->with(['http://pollora.test', 'https://pollora.test', 'https://pollora.test/', 'HTTPS://pollora.test']);

    it('leaves an empty value alone', function (): void {
        expect(AppUrl::normalize(''))->toBe('');
    });
});

describe('host', function (): void {
    it('finds the host with and without a scheme', function (string $url): void {
        expect(AppUrl::host($url))->toBe('pollora.test');
    })->with(['pollora.test', 'http://pollora.test', 'https://pollora.test:8443/cms', 'pollora.test:8000']);

    it('has no host for an empty value', function (): void {
        expect(AppUrl::host(''))->toBeNull();
    });
});
