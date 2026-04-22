<?php

declare(strict_types=1);

use Pollora\VersionCheck\Infrastructure\Services\PackagistVersionChecker;

require_once dirname(__DIR__).'/helpers.php';

beforeEach(function (): void {
    setupWordPressMocks();
});

describe('PackagistVersionChecker', function (): void {
    it('returns the currently installed version', function (): void {
        $checker = new PackagistVersionChecker;
        $version = $checker->getCurrentVersion();

        expect($version)->not->toBeNull();
        expect($version)->toBeString();
    });

    it('returns cached version from transient', function (): void {
        setWordPressFunction('get_transient', fn (): string => '99.0.0');

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBe('99.0.0');
    });

    it('fetches from packagist when no cache and stores in transient', function (): void {
        setWordPressFunction('get_transient', fn (): false => false);

        $storedVersion = null;
        setWordPressFunction('set_transient', function ($key, $value, $ttl) use (&$storedVersion): true {
            $storedVersion = $value;

            return true;
        });

        setWordPressFunction('wp_remote_get', fn (): array => [
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

        setWordPressFunction('wp_remote_retrieve_body', fn ($response) => $response['body']);
        setWordPressFunction('is_wp_error', fn (): false => false);

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBe('13.3.0');
        expect($storedVersion)->toBe('13.3.0');
    });

    it('skips dev and pre-release versions', function (): void {
        setWordPressFunction('get_transient', fn (): false => false);
        setWordPressFunction('set_transient', fn (): true => true);

        setWordPressFunction('wp_remote_get', fn (): array => [
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

        setWordPressFunction('wp_remote_retrieve_body', fn ($response) => $response['body']);
        setWordPressFunction('is_wp_error', fn (): false => false);

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBe('13.3.0');
    });

    it('returns null on API error', function (): void {
        setWordPressFunction('get_transient', fn (): false => false);
        setWordPressFunction('is_wp_error', fn (): true => true);
        setWordPressFunction('wp_remote_get', fn (): stdClass => new stdClass);

        $checker = new PackagistVersionChecker;

        expect($checker->getLatestVersion())->toBeNull();
    });
});
