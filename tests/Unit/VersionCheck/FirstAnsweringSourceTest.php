<?php

declare(strict_types=1);

use Pollora\VersionCheck\Domain\Contracts\VersionSource;
use Pollora\VersionCheck\Infrastructure\Sources\FirstAnsweringSource;

/**
 * @param  array<string, string|null>  $latest  Latest version by package
 * @param  array<string, string|null>  $urls  Release URL by package
 */
function versionSourceAnswering(array $latest, array $urls = []): VersionSource
{
    return new readonly class($latest, $urls) implements VersionSource
    {
        public function __construct(private array $latest, private array $urls) {}

        public function latest(string $package): ?string
        {
            return $this->latest[$package] ?? null;
        }

        public function releaseUrl(string $package, string $version): ?string
        {
            return $this->urls[$package] ?? null;
        }
    };
}

describe('FirstAnsweringSource', function (): void {
    it('answers with the first source that knows the latest version', function (): void {
        $source = new FirstAnsweringSource([
            versionSourceAnswering([]),
            versionSourceAnswering(['pollora/framework' => '13.40.1']),
            versionSourceAnswering(['pollora/framework' => '13.0.0']),
        ]);

        expect($source->latest('pollora/framework'))->toBe('13.40.1');
    });

    it('answers null when no source knows the package', function (): void {
        expect((new FirstAnsweringSource([versionSourceAnswering([]), versionSourceAnswering([])]))->latest('acme/x'))->toBeNull()
            ->and((new FirstAnsweringSource([]))->latest('acme/x'))->toBeNull();
    });

    it('points to the first release page a source can give', function (): void {
        $source = new FirstAnsweringSource([
            versionSourceAnswering([]),
            versionSourceAnswering([], ['pollora/framework' => 'https://github.com/Pollora/framework/releases/tag/v13.40.1']),
            versionSourceAnswering([], ['pollora/framework' => 'https://packagist.org/other']),
        ]);

        expect($source->releaseUrl('pollora/framework', '13.40.1'))->toBe('https://github.com/Pollora/framework/releases/tag/v13.40.1');
    });

    it('has no release page when none of the sources has one', function (): void {
        expect((new FirstAnsweringSource([versionSourceAnswering([])]))->releaseUrl('acme/x', '1.0.0'))->toBeNull();
    });
});
