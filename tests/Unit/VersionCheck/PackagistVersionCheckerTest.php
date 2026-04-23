<?php

declare(strict_types=1);

use Pollora\VersionCheck\Infrastructure\Services\PackagistVersionChecker;

describe('PackagistVersionChecker', function (): void {
    it('returns the currently installed version', function (): void {
        $checker = new PackagistVersionChecker;
        $version = $checker->getCurrentVersion();

        expect($version)->not->toBeNull();
        expect($version)->toBeString();
    });

    it('returns cached version from transient', function (): void {
        Brain\Monkey\Functions\when('get_transient')->justReturn('99.0.0');

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBe('99.0.0');
    });

    it('fetches from packagist when no cache and stores in transient', function (): void {
        Brain\Monkey\Functions\when('get_transient')->justReturn(false);

        $storedVersion = null;
        Brain\Monkey\Functions\when('set_transient')->alias(function ($key, $value, $ttl) use (&$storedVersion): true {
            $storedVersion = $value;

            return true;
        });

        Brain\Monkey\Functions\when('wp_remote_get')->alias(fn (): array => [
            'body' => json_encode([
                'packages' => [
                    'pollora/framework' => [
                        ['version' => 'v13.3.0'],
                        ['version' => 'v13.2.0'],
                    ],
                ],
            ]),
            'response' => ['code' => 200],
        ]);

        Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(fn ($response) => $response['body']);
        Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBe('13.3.0');
        expect($storedVersion)->toBe('13.3.0');
    });

    it('skips dev and pre-release versions', function (): void {
        Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        Brain\Monkey\Functions\when('set_transient')->justReturn(true);

        Brain\Monkey\Functions\when('wp_remote_get')->alias(fn (): array => [
            'body' => json_encode([
                'packages' => [
                    'pollora/framework' => [
                        ['version' => 'dev-main'],
                        ['version' => 'v14.0.0-beta.1'],
                        ['version' => 'v13.4.0-RC1'],
                        ['version' => 'v13.3.0'],
                    ],
                ],
            ]),
            'response' => ['code' => 200],
        ]);

        Brain\Monkey\Functions\when('wp_remote_retrieve_body')->alias(fn ($response) => $response['body']);
        Brain\Monkey\Functions\when('is_wp_error')->justReturn(false);

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBe('13.3.0');
    });

    it('returns null on API error', function (): void {
        Brain\Monkey\Functions\when('get_transient')->justReturn(false);
        Brain\Monkey\Functions\when('is_wp_error')->justReturn(true);
        Brain\Monkey\Functions\when('wp_remote_get')->justReturn(new stdClass);

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBeNull();
    });
});
